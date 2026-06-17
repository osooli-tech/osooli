-- =====================================================================
-- Osooli (أصولي) - Smart Survey & Property Management Platform
-- PostgreSQL 16 + PostGIS 3.5 Database Schema
-- =====================================================================
-- Design notes:
--   - geom columns use SRID 4326 (WGS84 lat/lng) - matches export from
--     client's ArcGIS GeoJSON
--   - parcels uses a self-referencing parent_parcel_id to represent
--     sub-units (apartments) within a building parcel
--   - Ownership is many-to-many via deed_owners (a parcel/deed can have
--     multiple co-owners; ArcGIS represents each co-owner as a separate
--     feature record sharing the same Geo_ID + Deed_No)
--   - "Synced from ArcGIS" tables: parcels, deeds, deed_owners,
--     parcel_boundaries, survey_decisions, parcel_photos (re-imported
--     periodically via sync script)
--   - "Owned by us" tables: owners (contact info), modification_requests,
--     sync_log, users, audit_logs, engineering_offices, geo hierarchy
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS postgis;

-- =====================================================================
-- ENUM TYPES
-- =====================================================================

-- Deed status (DeedStatus domain)
CREATE TYPE deed_status_enum AS ENUM ('محدث', 'قديم');

-- Deed classification (Class domain)
CREATE TYPE deed_class_enum AS ENUM ('زراعي', 'سكني', 'صناعي');

-- Asset type (OwnerType domain - misleading ArcGIS name, actually describes the property type)
CREATE TYPE asset_type_enum AS ENUM ('أرض', 'شقة', 'عمارة', 'فيلا', 'مستودع');

-- Survey decision source (Qrar domain)
CREATE TYPE qrar_source_enum AS ENUM ('بلدي', 'مكتب هندسي', 'بدون');

-- Which type of plan the parcel falls within (FALLin domain)
CREATE TYPE fall_in_enum AS ENUM ('مخطط زراعي', 'مخطط بلدية');

-- Location allocation method (AllocationType domain)
CREATE TYPE allocation_method_enum AS ENUM (
    'محدد بدقة',
    'محدد حسب الموقع العام',
    'لم يتم تحديد الموقع'
);

-- Land transaction type (LandTransactionType domain)
CREATE TYPE land_transaction_enum AS ENUM ('مباعة', 'مؤجرة', 'قيد البيع', 'خاصة');

-- Photo type for parcel photos
CREATE TYPE photo_type_enum AS ENUM ('جوية', 'أرضية');

-- Modification request workflow status
CREATE TYPE modification_request_status_enum AS ENUM (
    'pending',
    'sent_to_arcgis',
    'applied',
    'rejected'
);

-- Dashboard user role (simple flag; full RBAC handled by Spatie Laravel Permission package tables)
CREATE TYPE user_role_enum AS ENUM ('admin', 'viewer');


-- =====================================================================
-- GEOGRAPHIC HIERARCHY (owned by us)
-- countries -> regions -> cities -> districts -> plans
-- =====================================================================

CREATE TABLE countries (
    id          BIGSERIAL PRIMARY KEY,
    name_ar     VARCHAR(150) NOT NULL,
    name_en     VARCHAR(150),
    iso_code    VARCHAR(5),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);

CREATE TABLE regions (
    id          BIGSERIAL PRIMARY KEY,
    country_id  BIGINT NOT NULL REFERENCES countries(id) ON DELETE RESTRICT,
    name_ar     VARCHAR(150) NOT NULL,
    name_en     VARCHAR(150),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
CREATE INDEX idx_regions_country_id ON regions(country_id);

-- "cities" includes every town/governorate-equivalent (e.g. الرياض، الخرج، الدرعية)
-- as siblings under their region - no separate "governorate" table needed
CREATE TABLE cities (
    id          BIGSERIAL PRIMARY KEY,
    region_id   BIGINT NOT NULL REFERENCES regions(id) ON DELETE RESTRICT,
    name_ar     VARCHAR(150) NOT NULL,
    name_en     VARCHAR(150),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
CREATE INDEX idx_cities_region_id ON cities(region_id);

CREATE TABLE districts (
    id          BIGSERIAL PRIMARY KEY,
    city_id     BIGINT NOT NULL REFERENCES cities(id) ON DELETE RESTRICT,
    name_ar     VARCHAR(150) NOT NULL,
    name_en     VARCHAR(150),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
CREATE INDEX idx_districts_city_id ON districts(city_id);

CREATE TABLE plans (
    id          BIGSERIAL PRIMARY KEY,
    plan_no     VARCHAR(50) NOT NULL,          -- Plan_No من ArcGIS
    district_id BIGINT REFERENCES districts(id) ON DELETE RESTRICT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
CREATE INDEX idx_plans_district_id ON plans(district_id);
CREATE UNIQUE INDEX uq_plans_plan_no ON plans(plan_no);


-- =====================================================================
-- OWNERS (owned by us - contact info not present in ArcGIS)
-- =====================================================================

CREATE TABLE owners (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    national_id VARCHAR(50),                   -- Woner_ID من ArcGIS
    phone       VARCHAR(30),
    email       VARCHAR(255),
    whatsapp    VARCHAR(30),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
-- يسمح بتعدد NULL، لكن لو موجود لازم يكون فريد (للبحث عند المزامنة)
CREATE UNIQUE INDEX uq_owners_national_id ON owners(national_id) WHERE national_id IS NOT NULL;


-- =====================================================================
-- ENGINEERING OFFICES (owned by us)
-- المكاتب الهندسية التي تنفذ الرفع المساحي
-- =====================================================================

CREATE TABLE engineering_offices (
    id          BIGSERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    license_no  VARCHAR(100),
    phone       VARCHAR(30),
    email       VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);


-- =====================================================================
-- PARCELS (synced from ArcGIS)
-- القطعة الجغرافية. parent_parcel_id يمثل الوحدات الفرعية (شقق) داخل
-- عمارة - صف بدون parent = أرض/عمارة أصلية (لها geom من ArcGIS)،
-- صف بـ parent معبأ = شقة (geom = NULL، تُدخل يدوياً)
-- =====================================================================

CREATE TABLE parcels (
    id                  BIGSERIAL PRIMARY KEY,
    parcel_no           VARCHAR(50),                -- Parcel من ArcGIS
    geo_id              VARCHAR(100) NOT NULL,      -- Geo_ID = Parcel-Plan_No، مفتاح المزامنة
    plan_id             BIGINT REFERENCES plans(id) ON DELETE RESTRICT,
    parent_parcel_id    BIGINT REFERENCES parcels(id) ON DELETE CASCADE,
    geom                geometry(MultiPolygon, 4326), -- NULL للشقق/الوحدات الفرعية
    asset_type          asset_type_enum,            -- OwnerType من ArcGIS
    land_transaction    land_transaction_enum,
    allocation_method   allocation_method_enum,
    fall_in             fall_in_enum,
    source_gdb_id       BIGINT,                     -- OBJECTID تمثيلي من ArcGIS
    last_synced_at      TIMESTAMP,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP
);

CREATE UNIQUE INDEX uq_parcels_geo_id ON parcels(geo_id);
CREATE INDEX idx_parcels_plan_id ON parcels(plan_id);
CREATE INDEX idx_parcels_parent_parcel_id ON parcels(parent_parcel_id);
CREATE INDEX idx_parcels_source_gdb_id ON parcels(source_gdb_id);
-- فهرس مكاني - أساسي لاستعلامات GeoServer/Mapbox
CREATE INDEX idx_parcels_geom ON parcels USING GIST(geom);


-- =====================================================================
-- DEEDS (synced from ArcGIS)
-- صكوك القطعة - 1:N (تاريخ الصكوك: قديم/محدث)
-- =====================================================================

CREATE TABLE deeds (
    id              BIGSERIAL PRIMARY KEY,
    parcel_id       BIGINT NOT NULL REFERENCES parcels(id) ON DELETE CASCADE,
    deed_no         VARCHAR(100),               -- Deed_No
    deed_date_hijri VARCHAR(10),                -- نص هجري مثل '1435-03-08'
    deed_status     deed_status_enum,
    deed_class      deed_class_enum,
    deed_area       NUMERIC(14, 2),             -- مساحة الصك (Area/Survey_Area من ArcGIS)
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP
);
CREATE INDEX idx_deeds_parcel_id ON deeds(parcel_id);
CREATE INDEX idx_deeds_deed_no ON deeds(deed_no);


-- =====================================================================
-- DEED_OWNERS (synced from ArcGIS - pivot table)
-- ملكية مشتركة: كل صك ممكن له أكثر من مالك. ArcGIS يمثل كل مالك
-- كسجل (Feature) مستقل بنفس Geo_ID/Deed_No - السكربت يجمعهم هنا.
-- نسبة الملكية (ownership_share) غير متوفرة كحقل منظم في ArcGIS
-- (موجودة كنص داخل وثيقة الصك) - تُترك NULL، تُعبأ يدوياً إن لزم.
-- =====================================================================

CREATE TABLE deed_owners (
    id                  BIGSERIAL PRIMARY KEY,
    deed_id             BIGINT NOT NULL REFERENCES deeds(id) ON DELETE CASCADE,
    owner_id            BIGINT NOT NULL REFERENCES owners(id) ON DELETE RESTRICT,
    ownership_share     NUMERIC(5, 2),          -- نسبة % - NULL إذا غير معروفة
    source_gdb_id       BIGINT,                 -- OBJECTID لسجل هذا المالك في ArcGIS
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP
);
CREATE INDEX idx_deed_owners_deed_id ON deed_owners(deed_id);
CREATE INDEX idx_deed_owners_owner_id ON deed_owners(owner_id);
CREATE UNIQUE INDEX uq_deed_owners_deed_owner ON deed_owners(deed_id, owner_id);


-- =====================================================================
-- PARCEL_BOUNDARIES (synced from ArcGIS)
-- حدود/أبعاد القطعة + المساحة حسب الطبيعة (مقاسة)
-- =====================================================================

CREATE TABLE parcel_boundaries (
    id                      BIGSERIAL PRIMARY KEY,
    parcel_id               BIGINT NOT NULL REFERENCES parcels(id) ON DELETE CASCADE,
    n_border                VARCHAR(255),
    s_border                VARCHAR(255),
    e_border                VARCHAR(255),
    w_border                VARCHAR(255),
    n_dim                   NUMERIC(10, 2),
    s_dim                   NUMERIC(10, 2),
    e_dim                   NUMERIC(10, 2),
    w_dim                   NUMERIC(10, 2),
    measured_area           NUMERIC(14, 2),     -- المساحة حسب الطبيعة (محسوبة من الأبعاد)
    survey_date             VARCHAR(10),        -- تاريخ الرفع المساحي - ينتظر من ArcGIS (nullable حالياً)
    engineering_office_id   BIGINT REFERENCES engineering_offices(id) ON DELETE SET NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP
);
CREATE UNIQUE INDEX uq_parcel_boundaries_parcel_id ON parcel_boundaries(parcel_id);
CREATE INDEX idx_parcel_boundaries_eng_office ON parcel_boundaries(engineering_office_id);


-- =====================================================================
-- SURVEY_DECISIONS (synced from ArcGIS)
-- القرارات المساحية - 1:N (قطعة ممكن لها أكثر من قرار بمرور الوقت)
-- =====================================================================

CREATE TABLE survey_decisions (
    id          BIGSERIAL PRIMARY KEY,
    parcel_id   BIGINT NOT NULL REFERENCES parcels(id) ON DELETE CASCADE,
    qrar_source qrar_source_enum,
    qrar_no     VARCHAR(100),       -- رقم القرار
    report_no   VARCHAR(100),
    folder      VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
CREATE INDEX idx_survey_decisions_parcel_id ON survey_decisions(parcel_id);


-- =====================================================================
-- PARCEL_PHOTOS (synced from ArcGIS)
-- صور القطعة - 1:N (روابط، الصور نفسها على Drive/Object Storage)
-- =====================================================================

CREATE TABLE parcel_photos (
    id          BIGSERIAL PRIMARY KEY,
    parcel_id   BIGINT NOT NULL REFERENCES parcels(id) ON DELETE CASCADE,
    photo_url   VARCHAR(500) NOT NULL,
    photo_type  photo_type_enum,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP
);
CREATE INDEX idx_parcel_photos_parcel_id ON parcel_photos(parcel_id);


-- =====================================================================
-- USERS (owned by us)
-- مستخدمو لوحة التحكم (Admin/Viewer) - مختلفين عن owners (ملاك القطع)
-- ملاحظة: جداول RBAC الكاملة (roles, permissions, model_has_roles...)
-- تُنشأ تلقائياً عبر Spatie Laravel Permission package migrations
-- =====================================================================

CREATE TABLE users (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    password        VARCHAR(255) NOT NULL,
    phone           VARCHAR(30),            -- للـ OTP عبر Unifonic
    role            user_role_enum NOT NULL DEFAULT 'viewer',
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at   TIMESTAMP,
    last_login_ip   VARCHAR(45),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP
);
CREATE UNIQUE INDEX uq_users_email ON users(email);


-- =====================================================================
-- MODIFICATION_REQUESTS (owned by us)
-- طلبات تعديل من الملاك - تُرسل لفريق Al-Esnad ليطبقوها في ArcGIS
-- =====================================================================

CREATE TABLE modification_requests (
    id              BIGSERIAL PRIMARY KEY,
    parcel_id       BIGINT NOT NULL REFERENCES parcels(id) ON DELETE CASCADE,
    requested_by    BIGINT NOT NULL REFERENCES owners(id) ON DELETE RESTRICT,
    field_name      VARCHAR(100) NOT NULL,
    old_value       TEXT,
    new_value       TEXT,
    status          modification_request_status_enum NOT NULL DEFAULT 'pending',
    notes           TEXT,
    resolved_at     TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP
);
CREATE INDEX idx_modification_requests_parcel_id ON modification_requests(parcel_id);
CREATE INDEX idx_modification_requests_requested_by ON modification_requests(requested_by);
CREATE INDEX idx_modification_requests_status ON modification_requests(status);


-- =====================================================================
-- SYNC_LOG (owned by us)
-- سجل عمليات المزامنة من ArcGIS
-- =====================================================================

CREATE TABLE sync_log (
    id                  BIGSERIAL PRIMARY KEY,
    sync_started_at    TIMESTAMP NOT NULL,
    sync_finished_at   TIMESTAMP,
    records_imported   INTEGER DEFAULT 0,
    records_updated    INTEGER DEFAULT 0,
    status              VARCHAR(20) NOT NULL,   -- success / failed / partial
    notes               TEXT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- AUDIT_LOGS (owned by us - immutable)
-- سجل تدقيق: تسجيل الدخول، التنزيلات، التصدير... لا يُعدَّل أبداً
-- =====================================================================

CREATE TABLE audit_logs (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(50) NOT NULL,      -- login / logout / download / export / view
    target_type VARCHAR(50),               -- parcel / deed / document ...
    target_id   BIGINT,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
