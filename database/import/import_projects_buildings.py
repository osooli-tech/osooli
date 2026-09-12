#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
import_projects_buildings.py — استيراد طبقتي Project و Building من GDB إلى
PostgreSQL/PostGIS كطبقة تعريف/عرض بس (بدون علاقة بجدول parcels — راجع
migration الجدولين للسبب).

الموقع : database/import/import_projects_buildings.py

التشغيل:
  python database/import/import_projects_buildings.py --source path/to/GDB.gdb

المتطلبات:
  pip install fiona psycopg2-binary
"""

import sys
import os
import argparse

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

import fiona
from fiona.transform import transform_geom
import psycopg2
import json

# القيم الافتراضية لتطوير محلي فقط. للاتصال بأي قاعدة أخرى (إنتاج مثلاً) مرّر
# متغيرات البيئة بنفس أسماء Laravel — لا كلمات سر ولا مضيفات إنتاج تُكتب هنا:
#   DB_HOST=... DB_PORT=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... \
#   DB_SSLMODE=require python database/import/import_projects_buildings.py ...
DB = {
    'host':     os.environ.get('DB_HOST', '127.0.0.1'),
    'port':     int(os.environ.get('DB_PORT', '5432')),
    'dbname':   os.environ.get('DB_DATABASE', 'sakuki_db'),
    'user':     os.environ.get('DB_USERNAME', 'postgres'),
    'password': os.environ.get('DB_PASSWORD', 'root'),
    'options':  '-c client_encoding=UTF8',
    'sslmode':  os.environ.get('DB_SSLMODE', 'prefer'),
}

SRC_CRS = 'EPSG:32638'
DST_CRS = 'EPSG:4326'

LAYER_TO_TABLE = {
    'Project': 'projects',
    'Building': 'buildings',
}


def v(val):
    """يُرجع None إذا كانت القيمة فارغة أو None"""
    if val is None or val == '':
        return None
    return val


def import_layer(cur, source_path, layer, table, encoding):
    open_kwargs = {'layer': layer}
    if encoding and encoding.lower() != 'none':
        open_kwargs['encoding'] = encoding

    with fiona.open(source_path, **open_kwargs) as src:
        crs = src.crs
        needs_proj = crs and '32638' in str(crs)
        features = list(src)

    print(f"  {layer}: {len(features)} عنصر — CRS: {crs} {'← سيُحوَّل إلى 4326' if needs_proj else ''}")

    inserted = 0
    for feat in features:
        p = feat['properties']
        geom = dict(feat['geometry'])
        if needs_proj:
            geom = dict(transform_geom(SRC_CRS, DST_CRS, feat['geometry']))

        cur.execute(
            f"""
            INSERT INTO {table} (name, code, area, length, geom, created_at, updated_at)
            VALUES (%s, %s, %s, %s, ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(%s)), 4326), NOW(), NOW())
            """,
            (
                v(p.get('Name')),
                v(p.get('Code')),
                float(p['SHAPE_Area']) if p.get('SHAPE_Area') else None,
                float(p['SHAPE_Length']) if p.get('SHAPE_Length') else None,
                json.dumps(geom),
            )
        )
        inserted += 1

    return inserted


def run(source_path: str, encoding: str):
    print("الاتصال بقاعدة البيانات...")
    try:
        conn = psycopg2.connect(**DB)
        conn.autocommit = False
        cur = conn.cursor()
        print(f"  ✓ {DB['dbname']}@{DB['host']}")
    except Exception as e:
        sys.exit(f"✗ فشل الاتصال: {e}")

    print("\nحذف السجلات القديمة (استيراد يستبدل، لا يراكم) ...")
    cur.execute("DELETE FROM buildings")
    cur.execute("DELETE FROM projects")

    print("\nقراءة واستيراد الطبقات...")
    total = 0
    try:
        for layer, table in LAYER_TO_TABLE.items():
            total += import_layer(cur, source_path, layer, table, encoding)
    except Exception as e:
        conn.rollback()
        conn.close()
        sys.exit(f"✗ فشل الاستيراد: {e}")

    conn.commit()
    cur.close()
    conn.close()

    print(f"""
┌──────────────────────────────────────────┐
│  ✅ اكتمل — {total} عنصر مستورد            │
└──────────────────────────────────────────┘""")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='استيراد طبقتي Project و Building → PostgreSQL/PostGIS'
    )
    parser.add_argument('--source', '-s', required=True, help='مسار ملف GDB')
    parser.add_argument(
        '--encoding', '-e',
        default='none',
        help='ترميز حقول النص في GDB (افتراضي: none — UTF-8 أصلي). مرّر "cp1256" لو احتاج.'
    )
    args = parser.parse_args()
    run(args.source, args.encoding)
