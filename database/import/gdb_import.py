#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
gdb_import.py — استيراد بيانات GDB/GeoJSON إلى PostgreSQL/PostGIS
الموقع : database/import/gdb_import.py

التشغيل:
  python database/import/gdb_import.py
  python database/import/gdb_import.py --source path/to/file.geojson

المتطلبات:
  pip install fiona psycopg2-binary

يدعم مصدرين:
  • ESRI File Geodatabase (.gdb)  — يحوّل CRS تلقائياً إذا لزم
  • GeoJSON (.geojson / .json)    — من ArcGIS "Features to JSON" أو أي مصدر آخر
"""

import sys
import os
import json
import argparse
from datetime import datetime, timezone
from collections import defaultdict

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

import fiona
from fiona.transform import transform_geom
import psycopg2

# ─── الإعداد ────────────────────────────────────────────────────────────────────

DEFAULT_SOURCE = r'D:\Downloads\GDB_extracted\GDB\Osooli.gdb'

DB = {
    'host':     '127.0.0.1',
    'port':     5432,
    'dbname':   'sakuki_db',
    'user':     'postgres',
    'password': 'root',
    'options':  '-c client_encoding=UTF8',
}

GDB_LAYER = 'Osooli'          # الطبقة الافتراضية — يمكن تجاوزها بـ --layer
SRC_CRS   = 'EPSG:32638'
DST_CRS   = 'EPSG:4326'

# مكتب هندسي افتراضي يُربط بكل قطعة مستوردة لحين توفر بيانات حقيقية لكل مكتب
DEFAULT_ENGINEERING_OFFICE = 'مكتب الإسناد العالمي للاستشارات الهندسية'

# التسلسل الجغرافي الافتراضي لاستيعاب الأحياء القادمة من حقل District.
# الأحياء (العمارية / الوصيل / الدرعية) تقع في محافظة الدرعية بمنطقة الرياض.
DEFAULT_COUNTRY = 'المملكة العربية السعودية'
DEFAULT_REGION  = 'منطقة الرياض'
DEFAULT_CITY    = 'الدرعية'

# ─── مساعدات ────────────────────────────────────────────────────────────────────

def load_features(path: str, layer: str = GDB_LAYER) -> list:
    """
    يقرأ GDB أو GeoJSON ويُرجع list من:
      {'geometry': dict, 'properties': dict}
    يُحوّل CRS إلى 4326 تلقائياً إذا كان GDB بـ 32638.
    """
    ext = os.path.splitext(path)[1].lower()

    if os.path.isdir(path) or ext == '.gdb':
        print(f"  نوع المصدر : GDB (طبقة: {layer})")
        with fiona.open(path, layer=layer) as src:
            crs  = src.crs
            raw  = list(src)
        needs_proj = crs and '32638' in str(crs)
        print(f"  CRS أصلي   : {crs} {'← سيُحوَّل إلى 4326' if needs_proj else ''}")
        out = []
        for f in raw:
            geom = dict(f['geometry'])
            if needs_proj:
                geom = dict(transform_geom(SRC_CRS, DST_CRS, f['geometry']))
            out.append({'geometry': geom, 'properties': dict(f['properties'])})
        return out

    if ext in ('.geojson', '.json'):
        print(f"  نوع المصدر : GeoJSON")
        with open(path, encoding='utf-8') as f:
            gj = json.load(f)
        return [{'geometry': feat['geometry'], 'properties': feat['properties']}
                for feat in gj['features']]

    raise ValueError(f"تنسيق غير مدعوم: {path}")


def parse_hijri(value):
    """
    يُحوّل Deed_Date إلى 'YYYY-MM-DD' بدون تحويل تقويم.

    • string (fiona / GDB):      '1435-03-08T00:00:00+00:00'  →  [:10]
    • int/float (ArcGIS GeoJSON): epoch_ms (سالب)  →  decode datetime
    """
    if value is None:
        return None
    if isinstance(value, str):
        return value[:10]
    if isinstance(value, (int, float)):
        dt = datetime.fromtimestamp(value / 1000, tz=timezone.utc)
        return dt.strftime('%Y-%m-%d')
    return None


def v(val):
    """يُرجع None إذا كانت القيمة فارغة أو None"""
    if val is None or val == '':
        return None
    return val


# قيم الـ enum بالترتيب — الكود في GDB يقابل الترتيب (1 = الأول). مطابقة لتعريفات
# قاعدة البيانات في create_enum_types.
# مرجع الأكواد موثّق في docs/gdb-coded-domains.md (من domains الخاصة بالـ GDB).
# يجب مزامنة أي تعديل هنا مع app/Console/Commands/ImportParcelsGeoJson.php
ENUM_VALUES = {
    'asset_type':        ['أرض', 'شقة', 'عمارة', 'فيلا', 'مستودع'],
    'land_transaction':  ['مباعة', 'مؤجرة', 'قيد البيع', 'خاصة'],
    'deed_status':       ['محدث', 'قديم'],
    'deed_class':        ['زراعي', 'سكني', 'صناعي'],
    'qrar_source':       ['بلدي', 'مكتب هندسي', 'بدون'],
    'allocation_method': ['محدد بدقة', 'محدد حسب الموقع العام', 'لم يتم تحديد الموقع'],
    'fall_in':           ['مخطط زراعي', 'مخطط بلدية'],
}


def code_to_enum(field: str, code):
    """يحوّل كود رقمي (1، 2، ...) إلى قيمة enum بالترتيب. يُرجع None إذا فارغ أو خارج النطاق."""
    code = v(code)
    if code is None:
        return None
    try:
        idx = int(float(code))
    except (ValueError, TypeError):
        return None
    values = ENUM_VALUES.get(field)
    if not values or idx < 1 or idx > len(values):
        return None
    return values[idx - 1]


def ensure_geo_chain(cur):
    """
    يضمن وجود دولة ← منطقة ← مدينة افتراضية ويُرجع city_id.
    تُنشأ مرة واحدة لاستيعاب الأحياء القادمة من حقل District.
    """
    cur.execute("SELECT id FROM countries WHERE name_ar = %s", (DEFAULT_COUNTRY,))
    row = cur.fetchone()
    if row:
        country_id = row[0]
    else:
        cur.execute(
            "INSERT INTO countries (name_ar, created_at, updated_at) VALUES (%s, NOW(), NOW()) RETURNING id",
            (DEFAULT_COUNTRY,)
        )
        country_id = cur.fetchone()[0]

    cur.execute("SELECT id FROM regions WHERE name_ar = %s", (DEFAULT_REGION,))
    row = cur.fetchone()
    if row:
        region_id = row[0]
    else:
        cur.execute(
            "INSERT INTO regions (country_id, name_ar, created_at, updated_at) VALUES (%s, %s, NOW(), NOW()) RETURNING id",
            (country_id, DEFAULT_REGION)
        )
        region_id = cur.fetchone()[0]

    cur.execute("SELECT id FROM cities WHERE name_ar = %s", (DEFAULT_CITY,))
    row = cur.fetchone()
    if row:
        city_id = row[0]
    else:
        cur.execute(
            "INSERT INTO cities (region_id, name_ar, created_at, updated_at) VALUES (%s, %s, NOW(), NOW()) RETURNING id",
            (region_id, DEFAULT_CITY)
        )
        city_id = cur.fetchone()[0]

    return city_id


def get_or_create_district(cur, name, city_id):
    """find-or-create حي داخل المدينة الافتراضية ويُرجع district_id (أو None)."""
    name = v(name)
    if not name:
        return None
    name = str(name).strip()
    cur.execute("SELECT id FROM districts WHERE name_ar = %s AND city_id = %s", (name, city_id))
    row = cur.fetchone()
    if row:
        return row[0]
    cur.execute(
        "INSERT INTO districts (city_id, name_ar, created_at, updated_at) VALUES (%s, %s, NOW(), NOW()) RETURNING id",
        (city_id, name)
    )
    return cur.fetchone()[0]


# ─── الاستيراد ──────────────────────────────────────────────────────────────────

def run(source_path: str, layer: str = GDB_LAYER):
    started_at = datetime.now()

    # ── 1. الاتصال بقاعدة البيانات ──────────────────────────────────────────
    print("الاتصال بقاعدة البيانات...")
    try:
        conn = psycopg2.connect(**DB)
        conn.autocommit = False
        cur  = conn.cursor()
        print(f"  ✓ {DB['dbname']}@{DB['host']}")
    except Exception as e:
        sys.exit(f"✗ فشل الاتصال: {e}")

    # المكتب الهندسي الافتراضي — يُربط بكل قطعة لحين توفر بيانات حقيقية لكل مكتب
    cur.execute("SELECT id FROM engineering_offices WHERE name = %s", (DEFAULT_ENGINEERING_OFFICE,))
    office_row = cur.fetchone()
    if office_row:
        default_office_id = office_row[0]
    else:
        cur.execute("""
            INSERT INTO engineering_offices (name, created_at, updated_at)
            VALUES (%s, NOW(), NOW())
            RETURNING id
        """, (DEFAULT_ENGINEERING_OFFICE,))
        default_office_id = cur.fetchone()[0]

    # التسلسل الجغرافي الافتراضي (دولة ← منطقة ← مدينة) لاستيعاب الأحياء
    default_city_id = ensure_geo_chain(cur)

    # ── 2. قراءة المصدر ─────────────────────────────────────────────────────
    print(f"\nقراءة البيانات...")
    print(f"  المسار     : {source_path}")
    try:
        features = load_features(source_path, layer)
        print(f"  ✓ {len(features)} قطعة")
    except Exception as e:
        conn.close()
        sys.exit(f"✗ فشل القراءة: {e}")

    # ── 3. تجميع co-ownership بـ (Geo_ID, Deed_No) ─────────────────────────
    # قطعة واحدة قد تكون ملكية مشتركة = نفس Geo_ID + Deed_No مع أكثر من مالك
    groups: dict[tuple, list] = defaultdict(list)
    for feat in features:
        p   = feat['properties']
        key = (p['Geo_ID'], p.get('Deed_No'))
        groups[key].append(feat)

    print(f"  {len(groups)} مجموعة (Geo_ID, Deed_No)")

    multi_owner_groups = {k: len(v) for k, v in groups.items() if len(v) > 1}
    if multi_owner_groups:
        print(f"  ⚠ ملكية مشتركة ({len(multi_owner_groups)} مجموعة):")
        for key, cnt in multi_owner_groups.items():
            print(f"      {key[0]} / {key[1]} — {cnt} ملاك")

    # ── 4. استيراد ──────────────────────────────────────────────────────────
    stats = {
        'inserted': 0, 'updated': 0,
        'deeds': 0, 'owners_new': 0,
        'boundaries': 0, 'decisions': 0,
        'errors': 0,
    }

    print(f"\nبدء الاستيراد...")

    for idx, ((geo_id, deed_no), group) in enumerate(groups.items(), 1):

        # Savepoint لكل مجموعة — إذا فشلت واحدة نكمل الباقي
        cur.execute("SAVEPOINT sp_group")

        try:
            lead = group[0]          # Feature المرجع للبيانات المشتركة
            p    = lead['properties']

            # ── 4a. District + Plan (find-or-create) ─────────────────────
            district_id = get_or_create_district(cur, p.get('District'), default_city_id)

            plan_no = str(p['Plan_No']).strip()
            # COALESCE يحافظ على district_id سابق إن وُجد ولا يمسحه بـ NULL
            cur.execute("""
                INSERT INTO plans (plan_no, district_id, created_at, updated_at)
                VALUES (%s, %s, NOW(), NOW())
                ON CONFLICT (plan_no) DO UPDATE SET
                    district_id = COALESCE(EXCLUDED.district_id, plans.district_id),
                    updated_at  = NOW()
            """, (plan_no, district_id))
            cur.execute("SELECT id FROM plans WHERE plan_no = %s", (plan_no,))
            plan_id = cur.fetchone()[0]

            # ── 4b. Parcel (upsert on geo_id) ───────────────────────────
            parcel_no = str(p['Parcel']).strip() if p.get('Parcel') else None
            m_price      = float(p['M_price'])      if p.get('M_price')      else None
            parcel_price = float(p['Parcel_price']) if p.get('Parcel_price') else None
            asset_type       = code_to_enum('asset_type',       p.get('Owner_Type'))
            land_transaction = code_to_enum('land_transaction', p.get('Land_Trasaction'))
            allocation_method = code_to_enum('allocation_method', p.get('Allocation_Method'))
            fall_in          = code_to_enum('fall_in',          p.get('Fall_In'))

            cur.execute("""
                INSERT INTO parcels (parcel_no, geo_id, plan_id, m_price, parcel_price,
                                     asset_type, land_transaction, allocation_method, fall_in,
                                     created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                ON CONFLICT (geo_id) DO UPDATE SET
                    parcel_no         = EXCLUDED.parcel_no,
                    plan_id           = EXCLUDED.plan_id,
                    m_price           = EXCLUDED.m_price,
                    parcel_price      = EXCLUDED.parcel_price,
                    asset_type        = EXCLUDED.asset_type,
                    land_transaction  = EXCLUDED.land_transaction,
                    allocation_method = EXCLUDED.allocation_method,
                    fall_in           = EXCLUDED.fall_in,
                    updated_at        = NOW()
                RETURNING id, (xmax = 0) AS is_new
            """, (parcel_no, geo_id, plan_id, m_price, parcel_price,
                  asset_type, land_transaction, allocation_method, fall_in))
            row        = cur.fetchone()
            parcel_id  = row[0]
            is_new     = row[1]

            # تحديث الجيومتري — ST_Multi يضمن MultiPolygon دائماً
            cur.execute("""
                UPDATE parcels
                SET geom = ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(%s)), 4326)
                WHERE id = %s
            """, (json.dumps(lead['geometry']), parcel_id))

            if is_new:
                stats['inserted'] += 1
            else:
                stats['updated'] += 1

            # ── 4c. Deed ─────────────────────────────────────────────────
            deed_status = code_to_enum('deed_status', p.get('Deed_Status'))
            deed_class  = code_to_enum('deed_class',  p.get('Deed_Class'))

            cur.execute(
                "SELECT id FROM deeds WHERE parcel_id = %s AND deed_no = %s LIMIT 1",
                (parcel_id, v(deed_no))
            )
            existing_deed = cur.fetchone()

            if not existing_deed:
                cur.execute("""
                    INSERT INTO deeds
                        (parcel_id, deed_no, deed_date_hijri, deed_area, deed_status, deed_class, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
                    RETURNING id
                """, (
                    parcel_id,
                    v(deed_no),
                    parse_hijri(p.get('Deed_Date')),
                    float(p['Area']) if p.get('Area') else None,
                    deed_status,
                    deed_class,
                ))
                deed_id = cur.fetchone()[0]
                stats['deeds'] += 1
            else:
                deed_id = existing_deed[0]
                cur.execute("""
                    UPDATE deeds SET deed_status = %s, deed_class = %s, updated_at = NOW()
                    WHERE id = %s
                """, (deed_status, deed_class, deed_id))

            # ── 4d. Owners + deed_owners (مالك لكل feature في المجموعة) ─
            for feat in group:
                fp          = feat['properties']
                national_id = str(fp['Woner_ID']).strip() if fp.get('Woner_ID') else None
                name        = fp['Name'].strip()          if fp.get('Name')     else 'غير معروف'

                if national_id:
                    cur.execute("""
                        INSERT INTO owners (name, national_id, created_at, updated_at)
                        VALUES (%s, %s, NOW(), NOW())
                        ON CONFLICT (national_id) WHERE national_id IS NOT NULL
                        DO UPDATE SET name = EXCLUDED.name, updated_at = NOW()
                        RETURNING id, (xmax = 0) AS is_new
                    """, (name, national_id))
                else:
                    cur.execute("""
                        INSERT INTO owners (name, national_id, created_at, updated_at)
                        VALUES (%s, NULL, NOW(), NOW())
                        RETURNING id, TRUE AS is_new
                    """, (name,))

                owner_row = cur.fetchone()
                owner_id  = owner_row[0]
                if owner_row[1]:
                    stats['owners_new'] += 1

                # ربط الصك بالمالك
                cur.execute("""
                    INSERT INTO deed_owners (deed_id, owner_id, created_at, updated_at)
                    VALUES (%s, %s, NOW(), NOW())
                    ON CONFLICT (deed_id, owner_id) DO NOTHING
                """, (deed_id, owner_id))

            # ── 4e. Parcel Boundaries ────────────────────────────────────
            # measured_area = NULL (لم تُقَس ميدانياً بعد)
            # engineering_office_id = المكتب الافتراضي عند الإدراج فقط — لا يُستبدل تعيين يدوي لاحق
            cur.execute("""
                INSERT INTO parcel_boundaries (
                    parcel_id,
                    n_border, s_border, e_border, w_border,
                    n_dim, s_dim, e_dim, w_dim,
                    measured_area, engineering_office_id,
                    created_at, updated_at
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NULL, %s, NOW(), NOW())
                ON CONFLICT (parcel_id) DO UPDATE SET
                    n_border               = EXCLUDED.n_border,
                    s_border               = EXCLUDED.s_border,
                    e_border               = EXCLUDED.e_border,
                    w_border               = EXCLUDED.w_border,
                    n_dim                  = EXCLUDED.n_dim,
                    s_dim                  = EXCLUDED.s_dim,
                    e_dim                  = EXCLUDED.e_dim,
                    w_dim                  = EXCLUDED.w_dim,
                    engineering_office_id  = COALESCE(parcel_boundaries.engineering_office_id, EXCLUDED.engineering_office_id),
                    updated_at             = NOW()
            """, (
                parcel_id,
                v(p.get('N_Border')), v(p.get('S_Border')),
                v(p.get('E_Border')), v(p.get('W_Border')),
                float(p['N_Dim'])  if p.get('N_Dim')  else None,
                float(p['S_DIM']) if p.get('S_DIM')  else None,   # انتبه: S_DIM كابيتال
                float(p['E_Dim'])  if p.get('E_Dim')  else None,
                float(p['W_Dim'])  if p.get('W_Dim')  else None,
                default_office_id,
            ))
            stats['boundaries'] += 1

            # ── 4f. Survey Decision ──────────────────────────────────────
            # نُدرج فقط إذا وُجد Folder. Qrar = كود جهة القرار (qrar_source)، وليس رقم القرار.
            if p.get('Folder'):
                qrar_source = code_to_enum('qrar_source', p.get('Qrar'))
                report_no   = str(p['Report_No']) if p.get('Report_No') else None
                cur.execute(
                    "SELECT id FROM survey_decisions WHERE parcel_id = %s LIMIT 1",
                    (parcel_id,)
                )
                existing_decision = cur.fetchone()
                if not existing_decision:
                    cur.execute("""
                        INSERT INTO survey_decisions
                            (parcel_id, qrar_no, report_no, qrar_source, folder, created_at, updated_at)
                        VALUES (%s, NULL, %s, %s, %s, NOW(), NOW())
                    """, (parcel_id, report_no, qrar_source, p['Folder']))
                    stats['decisions'] += 1
                else:
                    cur.execute("""
                        UPDATE survey_decisions
                        SET qrar_source = %s, report_no = COALESCE(%s, report_no), updated_at = NOW()
                        WHERE id = %s
                    """, (qrar_source, report_no, existing_decision[0]))

            cur.execute("RELEASE SAVEPOINT sp_group")

            label  = '🆕' if is_new else '🔄'
            owners = len(group)
            print(f"  [{idx:02d}/{len(groups)}] {label} {geo_id:<12} صك:{deed_no:<10} "
                  f"ملاك:{owners}")

        except Exception as e:
            cur.execute("ROLLBACK TO SAVEPOINT sp_group")
            stats['errors'] += 1
            print(f"  [{idx:02d}/{len(groups)}] ✗ {geo_id}: {e}")

    # ── 5. تسجيل في sync_log ────────────────────────────────────────────────
    finished_at = datetime.now()
    duration    = (finished_at - started_at).total_seconds()
    status      = 'success' if stats['errors'] == 0 else 'partial'

    cur.execute("""
        INSERT INTO sync_log
            (sync_started_at, sync_finished_at, records_imported,
             records_updated, status, notes, created_at)
        VALUES (%s, %s, %s, %s, %s, %s, NOW())
    """, (
        started_at,
        finished_at,
        stats['inserted'],
        stats['updated'],
        status,
        f"src={os.path.basename(source_path)} | "
        f"new={stats['inserted']} upd={stats['updated']} "
        f"deeds={stats['deeds']} owners={stats['owners_new']} "
        f"err={stats['errors']} | {duration:.1f}s",
    ))

    conn.commit()
    cur.close()
    conn.close()

    # ── 6. ملخص نهائي ───────────────────────────────────────────────────────
    ok = stats['inserted'] + stats['updated']
    print(f"""
┌──────────────────────────────────────────┐
│  {'✅ اكتمل' if stats['errors']==0 else '⚠ اكتمل مع أخطاء'}                              │
├──────────────────────────────────────────┤
│  قطع جديدة       {stats['inserted']:>4}                     │
│  قطع محدّثة      {stats['updated']:>4}                     │
│  صكوك مُدرجة     {stats['deeds']:>4}                     │
│  ملاك جدد        {stats['owners_new']:>4}                     │
│  حدود قطع        {stats['boundaries']:>4}                     │
│  قرارات مساحية   {stats['decisions']:>4}                     │
│  أخطاء           {stats['errors']:>4}                     │
│  الوقت           {duration:>5.1f}s                   │
├──────────────────────────────────────────┤
│  سُجِّل في sync_log ✓                    │
└──────────────────────────────────────────┘""")

    if stats['errors'] > 0:
        sys.exit(1)


# ─── Entry Point ────────────────────────────────────────────────────────────────

if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='استيراد GDB/GeoJSON → PostgreSQL/PostGIS'
    )
    parser.add_argument(
        '--source', '-s',
        default=DEFAULT_SOURCE,
        help=f'مسار ملف GDB أو GeoJSON (افتراضي: {DEFAULT_SOURCE})'
    )
    parser.add_argument(
        '--layer', '-l',
        default=GDB_LAYER,
        help=f'اسم الطبقة داخل GDB (افتراضي: {GDB_LAYER})'
    )
    args = parser.parse_args()
    run(args.source, args.layer)
