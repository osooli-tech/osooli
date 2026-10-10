"""
Builds the Geodatabase template offered on the GDB import screen:
resources/templates/sakuki-gdb-template.zip, holding Sakuki_Template.gdb.

    # with the Python that ships GDAL (QGIS / OSGeo4W shell):
    python database/data/templates/build_gdb_template.py

Needs GDAL 3.7 or later (OpenFileGDB writing with field aliases and coded
value domains). Run it again whenever the importer learns a field
(app/Services/Import/ParcelGeoJsonImporter.php): the field list below is the
importer's, one to one.

What is inside:
  Parcels      polygons in EPSG:32638 (WGS 84 / UTM 38N, as the client's
               files are), one feature per owner of a deed — a deed with two
               owners is two features with the same Geo_ID and Deed_No.
               Every field has an Arabic alias; every fixed-choice field a
               coded value domain, so ArcGIS offers the list.
  Field_Guide  a table: each field, its alias, what it holds, and its codes.
"""

import os
import shutil
import sys
import tempfile
import zipfile
from pathlib import Path

from osgeo import gdal, ogr, osr

gdal.UseExceptions()

ROOT = Path(__file__).resolve().parents[3]
OUT = ROOT / 'resources' / 'templates' / 'sakuki-gdb-template.zip'

# Coded value domains: name, description, values in code order (1, 2, …).
# The order is the importer's ENUM_VALUES — the code is the position.
DOMAINS = {
    'DeedStatus': ('حالة الصك', ['محدث', 'قديم']),
    'Class': ('تصنيف الصك', ['زراعي', 'سكني', 'صناعي']),
    'OwnerType': ('نوع العقار', ['أرض', 'شقة', 'عمارة', 'فيلا', 'مستودع']),
    'LandTransactionType': ('نوع التعامل', ['مباعة', 'مؤجرة', 'قيد البيع', 'خاصة']),
    'AllocationType': ('طريقة التحديد', ['محدد بدقة', 'محدد حسب الموقع العام', 'لم يتم تحديد الموقع']),
    'FALLIn': ('يقع الصك ضمن', ['مخطط زراعي', 'مخطط بلدية', 'طلبات احكام', 'حجة استحكام', 'مخطط', 'الصك']),
    'Qrar': ('جهة القرار المساحي', ['بلدي', 'مكتب هندسي', 'بدون']),
}

S, R, I = ogr.OFTString, ogr.OFTReal, ogr.OFTInteger

# name, type, width, alias, domain, what it holds
FIELDS = [
    ('Geo_ID', S, 50, 'رقم GEO (مطلوب)', None, 'المعرّف الفريد للقطعة، ولا يتكرر. غالبًا «القطعة-المخطط» مثل 131-623.'),
    ('Parcel', S, 30, 'رقم القطعة', None, 'رقم القطعة كما في المخطط.'),
    ('Plan_No', S, 50, 'رقم المخطط', None, 'رقم المخطط المعتمد. اتركه فارغًا أو اكتب «بدون» إن لم يكن للقطعة مخطط.'),
    ('District', S, 150, 'الحي', None, 'اسم الحي. يُطابَق باسمه أو بموقع القطعة عند الاستيراد.'),
    ('Parent_Geo_ID', S, 50, 'رقم GEO للعقار الأم', None, 'للوحدات داخل عقار، كالشقة داخل عمارة: رقم GEO للعمارة.'),
    ('Parent_Owner_ID', S, 20, 'هوية المالك الأساسي', None, 'رقم هوية المالك الذي تبقى القطعة تحته في البوابة وإن كان صكها باسم غيره (كأرض وهبها لابنه). اتركه فارغًا إن بيعت.'),
    ('Deed_No', S, 30, 'رقم الصك', None, 'رقم الصك. القطعة بلا صك يُترك فارغًا.'),
    ('Deed_Date', S, 10, 'تاريخ الصك (هجري)', None, 'بالتقويم الهجري بصيغة YYYY-MM-DD، مثل 1442-04-21.'),
    ('Area', R, 0, 'مساحة الصك (م²)', None, 'المساحة المذكورة في الصك بالمتر المربع.'),
    ('Deed_Status', I, 0, 'حالة الصك', 'DeedStatus', None),
    ('Deed_Class', I, 0, 'تصنيف الصك', 'Class', None),
    ('Owner_Type', I, 0, 'نوع العقار', 'OwnerType', None),
    ('Land_Trasaction', I, 0, 'نوع التعامل', 'LandTransactionType', None),
    ('Allocation_Method', I, 0, 'طريقة التحديد', 'AllocationType', None),
    ('Fall_In', I, 0, 'يقع الصك ضمن', 'FALLIn', None),
    ('M_price', R, 0, 'سعر المتر (ريال)', None, 'السعر التقديري للمتر المربع بالريال.'),
    ('Parcel_price', R, 0, 'القيمة التقديرية (ريال)', None, 'قيمة القطعة كاملة بالريال.'),
    ('Real_Estate_portfolio', S, 100, 'المحفظة العقارية', None, 'اسم محفظة المالك التي توضع فيها القطعة (اختياري).'),
    ('Name', S, 150, 'اسم المالك', None, 'اسم المالك كاملًا. لكل مالك في الصك عنصر مستقل بنفس Geo_ID ورقم الصك.'),
    ('Woner_ID', S, 10, 'رقم هوية المالك', None, 'رقم الهوية أو السجل، 10 أرقام. به يُطابَق المالك مع الموجود.'),
    ('Phone', S, 20, 'جوال المالك', None, 'رقم سعودي، مثل 0501234567. به يدخل المالك بوابة الملاك.'),
    ('Email', S, 100, 'بريد المالك', None, 'اختياري.'),
    ('Share', R, 0, 'نسبة الملكية (%)', None, 'حصة المالك في الصك من 0 إلى 100. مجموع حصص الصك الواحد 100.'),
    ('N_Border', S, 150, 'الحد الشمالي', None, 'نص الحد، مثل «شارع عرض 15م» أو «قطعة 132».'),
    ('S_Border', S, 150, 'الحد الجنوبي', None, None),
    ('E_Border', S, 150, 'الحد الشرقي', None, None),
    ('W_Border', S, 150, 'الحد الغربي', None, None),
    ('N_Dim', R, 0, 'الطول الشمالي (م)', None, 'بالمتر.'),
    ('S_DIM', R, 0, 'الطول الجنوبي (م)', None, None),
    ('E_Dim', R, 0, 'الطول الشرقي (م)', None, None),
    ('W_Dim', R, 0, 'الطول الغربي (م)', None, None),
    ('N_Border_2', S, 150, 'الحد الشمالي (حسب الطبيعة)', None, 'مجموعة ثانية اختيارية للحدود كما على الطبيعة؛ تُختار المجموعة عند الاستيراد.'),
    ('S_Border_2', S, 150, 'الحد الجنوبي (حسب الطبيعة)', None, None),
    ('E_Border_2', S, 150, 'الحد الشرقي (حسب الطبيعة)', None, None),
    ('W_Border_2', S, 150, 'الحد الغربي (حسب الطبيعة)', None, None),
    ('N_Dim_2', R, 0, 'الطول الشمالي (حسب الطبيعة)', None, None),
    ('S_Dim_2', R, 0, 'الطول الجنوبي (حسب الطبيعة)', None, None),
    ('E_Dim_2', R, 0, 'الطول الشرقي (حسب الطبيعة)', None, None),
    ('W_Dim_2', R, 0, 'الطول الغربي (حسب الطبيعة)', None, None),
    ('Survey_Area', R, 0, 'المساحة المقاسة (م²)', None, 'المساحة من الرفع المساحي.'),
    ('Survey_Date', S, 10, 'تاريخ الرفع المساحي (هجري)', None, 'YYYY-MM-DD بالهجري.'),
    ('Qrar', I, 0, 'جهة القرار المساحي', 'Qrar', None),
    ('Qrar_No', S, 50, 'رقم القرار المساحي', None, 'رقم القرار نفسه.'),
    ('Report_No', S, 50, 'رقم التقرير', None, None),
    ('Folder', S, 50, 'رقم المجلد', None, 'رقم ملف الأرشيف الورقي.'),
]

# Two sample deeds: one on a parcel with two owners (two features), one
# with a single owner. Coordinates in UTM 38N, near Al-Ammariyah.
SAMPLES = [
    {
        'ring': [(650000, 2745000), (650040, 2745000), (650040, 2745030), (650000, 2745030)],
        'common': {
            'Geo_ID': '131-623', 'Parcel': '131', 'Plan_No': '623', 'District': 'العمارية',
            'Deed_No': '310101000001', 'Deed_Date': '1442-04-21', 'Area': 1200.0,
            'Deed_Status': 1, 'Deed_Class': 2, 'Owner_Type': 1, 'Land_Trasaction': 4,
            'Allocation_Method': 1, 'Fall_In': 2, 'M_price': 850.0, 'Parcel_price': 1020000.0,
            'Real_Estate_portfolio': 'أراضي العمارية',
            'N_Border': 'شارع عرض 15م', 'S_Border': 'قطعة 132', 'E_Border': 'قطعة 129', 'W_Border': 'ممر مشاة 6م',
            'N_Dim': 40.0, 'S_DIM': 40.0, 'E_Dim': 30.0, 'W_Dim': 30.0,
            'Survey_Area': 1198.5, 'Survey_Date': '1445-02-10',
            'Qrar': 2, 'Qrar_No': '4501234', 'Report_No': 'R-2024-17', 'Folder': '12',
        },
        'owners': [
            {'Name': 'مالك تجريبي أول', 'Woner_ID': '1000000001', 'Phone': '0500000001', 'Email': 'owner1@example.com', 'Share': 50.0},
            {'Name': 'مالك تجريبي ثانٍ', 'Woner_ID': '1000000002', 'Phone': '0500000002', 'Share': 50.0},
        ],
    },
    {
        'ring': [(650060, 2745000), (650090, 2745000), (650090, 2745030), (650060, 2745030)],
        'common': {
            'Geo_ID': '133-623', 'Parcel': '133', 'Plan_No': '623', 'District': 'العمارية',
            'Deed_No': '310101000002', 'Deed_Date': '1443-09-03', 'Area': 900.0,
            'Deed_Status': 1, 'Deed_Class': 2, 'Owner_Type': 1, 'Land_Trasaction': 3,
            'Allocation_Method': 1, 'Fall_In': 2, 'M_price': 800.0,
            'N_Border': 'شارع عرض 15م', 'S_Border': 'قطعة 134', 'E_Border': 'قطعة 131', 'W_Border': 'قطعة 135',
            'N_Dim': 30.0, 'S_DIM': 30.0, 'E_Dim': 30.0, 'W_Dim': 30.0,
            'Qrar': 1,
        },
        'owners': [
            {'Name': 'مالك تجريبي ثالث', 'Woner_ID': '1000000003', 'Phone': '0500000003', 'Share': 100.0},
        ],
    },
]


def main() -> None:
    work = Path(tempfile.mkdtemp())
    gdb = work / 'Sakuki_Template.gdb'
    driver = ogr.GetDriverByName('OpenFileGDB')
    ds = driver.CreateDataSource(str(gdb))

    for name, (description, values) in DOMAINS.items():
        domain = ogr.CreateCodedFieldDomain(
            name, description, ogr.OFTInteger, ogr.OFSTNone,
            {i + 1: value for i, value in enumerate(values)},
        )
        if not ds.AddFieldDomain(domain):
            sys.exit(f'could not add domain {name}')

    srs = osr.SpatialReference()
    srs.ImportFromEPSG(32638)
    layer = ds.CreateLayer('Parcels', srs, ogr.wkbMultiPolygon, ['LAYER_ALIAS=القطع والصكوك'])
    for name, kind, width, alias, domain, _ in FIELDS:
        field = ogr.FieldDefn(name, kind)
        if width:
            field.SetWidth(width)
        field.SetAlternativeName(alias)
        if domain:
            field.SetDomainName(domain)
        layer.CreateField(field)

    for sample in SAMPLES:
        ring = ogr.Geometry(ogr.wkbLinearRing)
        for x, y in sample['ring'] + [sample['ring'][0]]:
            ring.AddPoint_2D(x, y)
        polygon = ogr.Geometry(ogr.wkbPolygon)
        polygon.AddGeometry(ring)
        shape = ogr.Geometry(ogr.wkbMultiPolygon)
        shape.AddGeometry(polygon)

        for owner in sample['owners']:
            feature = ogr.Feature(layer.GetLayerDefn())
            for key, value in {**sample['common'], **owner}.items():
                feature.SetField(key, value)
            feature.SetGeometry(shape)
            layer.CreateFeature(feature)

    guide = ds.CreateLayer('Field_Guide', None, ogr.wkbNone, ['LAYER_ALIAS=دليل الحقول'])
    for name, alias in (('Field', 'الحقل'), ('Alias', 'الاسم بالعربية'), ('Holds', 'ماذا يحمل'), ('Codes', 'القيم المسموحة')):
        field = ogr.FieldDefn(name, ogr.OFTString)
        field.SetWidth(400)
        field.SetAlternativeName(alias)
        guide.CreateField(field)
    for name, _, _, alias, domain, holds in FIELDS:
        row = ogr.Feature(guide.GetLayerDefn())
        row.SetField('Field', name)
        row.SetField('Alias', alias)
        row.SetField('Holds', holds or '')
        if domain:
            row.SetField('Codes', '، '.join(f'{i + 1} = {v}' for i, v in enumerate(DOMAINS[domain][1])))
        guide.CreateFeature(row)

    ds = None  # flush

    OUT.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
        for root, _, files in os.walk(gdb):
            for f in files:
                path = Path(root) / f
                z.write(path, path.relative_to(work).as_posix())
    shutil.rmtree(work, ignore_errors=True)
    print(f'{OUT} ({OUT.stat().st_size:,} bytes)')


if __name__ == '__main__':
    main()
