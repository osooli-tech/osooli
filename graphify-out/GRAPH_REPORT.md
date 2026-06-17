# Graph Report - .  (2026-06-15)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 220 nodes · 184 edges · 50 communities (46 shown, 4 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 1 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `01a1d8f5`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]

## God Nodes (most connected - your core abstractions)
1. `Osooli Project — Context Summary` - 12 edges
2. `🏠 Osooli — Real Estate Platform` - 10 edges
3. `🚀 Installation` - 9 edges
4. `require` - 8 edges
5. `require-dev` - 8 edges
6. `Osooli (أصولي) — DB Schema Handoff لـ Laravel Implementation` - 8 edges
7. `User` - 6 edges
8. `scripts` - 6 edges
9. `TestCase` - 6 edges
10. `3.3 أهم القرارات الهيكلية (لماذا التصميم هكذا)` - 6 edges

## Surprising Connections (you probably didn't know these)
- `ExampleTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/ExampleTest.php → tests/TestCase.php
- `ExampleTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Unit/ExampleTest.php → tests/TestCase.php

## Import Cycles
- None detected.

## Communities (50 total, 4 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.09
Nodes (22): autoload-dev, psr-4, description, extra, laravel, keywords, dont-discover, license (+14 more)

### Community 1 - "Community 1"
Cohesion: 0.11
Nodes (17): 10. Flutter / UI Design Status, 11. Key Constraints to Remember, 1. Project Overview, 2. Phase 1 Scope (CLIENT REQUIREMENTS — final, agreed), 3. Final Technology Stack (decided — no more comparisons needed), 4. Environment Setup Status (Windows), 5. Real Client GDB Data — ANALYZED (GDB.rar → Osooli.gdb), 6. CRITICAL — Data Workflow Clarification (changes everything) (+9 more)

### Community 2 - "Community 2"
Cohesion: 0.12
Nodes (15): dependencies, alpinejs, devDependencies, autoprefixer, axios, concurrently, laravel-vite-plugin, postcss (+7 more)

### Community 3 - "Community 3"
Cohesion: 0.14
Nodes (13): 1.1 المشروع, 1.2 نطاق المرحلة 1 (نهائي ومتفق عليه), 1.3 الـ Stack النهائي (محسوم - لا مزيد من المقارنات), 1.4 قيود يجب تذكرها دائماً, 4.1 قواعد التنظيف عند الاستيراد, (Handoff كامل لـ Claude Code - يبدأ من هنا), Osooli (أصولي) — ملخص شامل للمشروع + قاعدة البيانات, الجزء 1: نظرة عامة على المشروع (+5 more)

### Community 4 - "Community 4"
Cohesion: 0.14
Nodes (13): Compile frontend assets (in a separate terminal), 🤝 Contributing, 🗄️ Database Viewer (pgAdmin 4), 📦 Installed Packages, JavaScript (npm), 📄 License, 🏠 Osooli — Real Estate Platform, PHP (Composer) (+5 more)

### Community 5 - "Community 5"
Cohesion: 0.17
Nodes (11): 1. التقنيات المؤكدة (لا حاجة لمراجعتها), 2. ملف Schema الجاهز, 3. ملخص التصميم النهائي (17 جدول), 4. أهم القرارات الهيكلية (السياق الكامل), 5. خطوات Laravel التالية (المقترحة لـ Claude Code), 6. أسئلة مفتوحة (لا تعيق العمل، لكن للمتابعة مع Al-Esnad), 7. ملفات مرجعية من هذه الجلسة, Osooli (أصولي) — DB Schema Handoff لـ Laravel Implementation (+3 more)

### Community 6 - "Community 6"
Cohesion: 0.27
Nodes (6): Authenticatable, HasFactory, User, Notifiable, Seeder, DatabaseSeeder

### Community 7 - "Community 7"
Cohesion: 0.20
Nodes (10): 3.1 الهيكل الجغرافي (owned by us), 3.2 الجداول الأساسية, 3.3 أهم القرارات الهيكلية (لماذا التصميم هكذا), 3.4 ENUM Types (بقيم حقيقية مؤكدة من ArcGIS Domains), التاريخ الهجري (`deed_date_hijri`), الجزء 3: تصميم قاعدة البيانات — النهائي (17 جدول), القطعة والوحدات الفرعية (`parent_parcel_id`), المساحات الثلاثة (لا تخلط بينها) (+2 more)

### Community 8 - "Community 8"
Cohesion: 0.28
Nodes (4): BaseTestCase, ExampleTest, TestCase, ExampleTest

### Community 9 - "Community 9"
Cohesion: 0.22
Nodes (9): 1. Clone the repository, 2. Install PHP dependencies, 3. Install JavaScript dependencies, 4. Set up environment file, 5. Configure your `.env` file, 6. Create the PostgreSQL database, 7. Run database migrations, 8. (Optional) Seed the database (+1 more)

### Community 10 - "Community 10"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 11 - "Community 11"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 12 - "Community 12"
Cohesion: 0.33
Nodes (6): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd

### Community 13 - "Community 13"
Cohesion: 0.47
Nodes (3): UserFactory, Factory, static

### Community 14 - "Community 14"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

## Knowledge Gaps
- **111 isolated node(s):** `Controller`, `$schema`, `name`, `type`, `description` (+106 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `require-dev` connect `Community 10` to `Community 0`?**
  _High betweenness centrality (0.013) - this node is a cross-community bridge._
- **Why does `config` connect `Community 11` to `Community 0`?**
  _High betweenness centrality (0.011) - this node is a cross-community bridge._
- **What connects `Controller`, `$schema`, `name` to the rest of the system?**
  _111 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.08695652173913043 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.1111111111111111 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.125 - nodes in this community are weakly interconnected._
- **Should `Community 3` be split into smaller, more focused modules?**
  _Cohesion score 0.14285714285714285 - nodes in this community are weakly interconnected._