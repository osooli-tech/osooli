# Portal Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separate who signs in (a portal account, one phone) from who owns land (an owner), so one phone can open several owners, one owner can be opened by several phones, and co-owned parcels count by share.

**Architecture:** Two new tables (`portal_accounts`, `portal_account_owners`) sit beside `owners`. The `owner` guard and Sanctum tokens keep authenticating an **Owner** ("the owner being acted for"); the account rides alongside in the session / on the token, and a middleware re-checks the grant on every request. Switching owners is an internal re-login after a grant check. A shared `OwnerHoldings` calculator gives each (owner, parcel) share from the held deed.

**Tech Stack:** Laravel 12, Livewire 4, Sanctum 4, PostgreSQL + PostGIS, PHPUnit 11, Spatie Permission.

**Spec:** `docs/superpowers/specs/2026-11-11-portal-accounts-design.md`

## Global Constraints

- Arabic copy in Modern Standard Arabic; every new string in both `lang/ar` and `lang/en`.
- `declare(strict_types=1);` in every new PHP file; Pint and PHPStan (`phpstan.neon`) stay clean.
- Phone normalisation is `Owner::normalisePhone()` everywhere; never a second rule.
- Every write to `portal_accounts` / `portal_account_owners` goes through `PortalAccessService` and writes an `AuditLog` row.
- No model event on `Owner` grants access. Grants come only from: staff screens, the import, the backfill.
- Invariant: `owners.phone` equals the phone of that owner's `self` account when one exists.
- The middleware fails closed: a portal session or token with no account is signed out (web) / 401 (API).
- A pending grant (`needs_review = true`) and a grant on an archived owner never open anything.
- Existing test **assertions** stay unchanged; only their sign-in setup moves to the new helper.
- Git: work on branch `feature/portal-accounts`; commit at the checkpoints below (the user asked for commits and a push of this branch).

## Review Focus

1. A phone that opens owner A must never reach owner B by URL guessing, switching, or an API token — test in Tasks 10 and 16.
2. Duplicate phones already in production must not widen access after the backfill — test in Task 7.
3. A grant revoked or an account deactivated while signed in stops working on the next request, web and API — test in Tasks 8 and 9.
4. A co-owned parcel is counted once in account totals, never more than 100% — test in Tasks 2 and 11.
5. An import with a typo'd phone that matches another owner must not grant access silently — test in Task 15.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Services/Owner/OwnerHoldings.php` | share of an owner in a parcel, from the held deed |
| `app/Models/PortalAccount.php`, `app/Models/PortalAccountOwner.php` | the two new tables |
| `app/Enums/PortalRelation.php`, `app/Enums/PortalGrantSource.php` | `self`/`delegate`; `migration`/`import`/`staff` |
| `app/Services/Portal/PortalAccessService.php` | every grant / revoke / approve / phone move, with audit |
| `app/Support/Portal/LegacyAccountBackfill.php` | builds accounts from today's owner phones |
| `app/Console/Commands/PortalAccountsAudit.php` | `portal:accounts-audit` dry run |
| `app/Support/Portal/PortalContext.php` | current account, owner, grants; switching |
| `app/Http/Middleware/EnsurePortalAccount.php`, `EnsurePortalCanManage.php` | `portal.account`, `portal.manage` |
| `app/Services/Portal/AccountOverviewService.php` | account-wide totals, per-owner cards, map |
| `app/Http/Controllers/Portal/AccountController.php` | overview page, switch |
| `app/Livewire/Owners/PortalAccessPanel.php` | owner's portal access in the staff owner panel |
| `app/Livewire/PortalAccounts/AccountIndex.php` | staff accounts page + review tab |
| `tests/Concerns/SignsInOwners.php` | `actingAsOwner()`, `apiAsOwner()` test helpers |

---

## Phase 0 — Shares

### Task 1: OwnerHoldings

**Files:**
- Create: `app/Services/Owner/OwnerHoldings.php`
- Test: `tests/Feature/Owner/OwnerHoldingsTest.php`

**Interfaces:**
- Produces:
  - `OwnerHoldings::share(Parcel $parcel, int $ownerId): float`: 0..1. Requires `heldDeed.deedOwners` loaded (loads them if missing).
  - `OwnerHoldings::shares(Parcel $parcel): array<int, float>`: owner_id => share for every owner on the held deed.
  - `OwnerHoldings::RELATIONS = ['heldDeed.deedOwners']`: eager-load list for callers.

- [ ] **Step 1: Write failing tests**
  - `test_a_recorded_share_is_used`: deed owners A=50, B=50 → `share(p, A) === 0.5`.
  - `test_a_missing_share_splits_equally`: three owners, all null → each `1/3` (assertEqualsWithDelta 0.0001).
  - `test_a_share_over_100_is_capped`: 140 → `1.0`.
  - `test_an_owner_only_on_an_old_deed_holds_nothing`: old deed (status Old) has A; held deed has B → `share(p, A) === 0.0`.
  - `test_a_parcel_with_no_held_deed_has_no_shares`: `shares(p) === []`.
- [ ] **Step 2: Run** `php artisan test --filter=OwnerHoldingsTest`: FAIL (class missing).
- [ ] **Step 3: Implement** using `Parcel::heldDeed()`; rules from spec §7 (recorded share clamped 0..100 / 100; null → 1 / count of owners on the held deed).
- [ ] **Step 4: Run** the same filter: PASS.

### Task 2: Weighted figures in statistics, insights and linked parcels

**Files:**
- Modify: `app/Services/Owner/OwnerStatisticsService.php` (`summary()`, `portfolio()`, drop `shareIn()`)
- Modify: `app/Services/Owner/OwnerInsightsService.php` (`row()`: area and value × share)
- Modify: `app/Services/Owner/LinkedParcelsService.php` (`groups()`, `totals()`, `row()`)
- Test: `tests/Feature/Owner/WeightedHoldingsTest.php`

**Interfaces:**
- Consumes: `OwnerHoldings::share()`, `OwnerHoldings::shares()`.
- Produces: `LinkedParcelsService::groups()` keeps its return shape. A co-owned parcel becomes one row **per holder** with `area` and `value` × that holder's share, and a new key `share` (0..1). `totals()['parcels']` counts **distinct** parcel ids.

- [ ] **Step 1: Write failing tests** (fixture: parcel 2018 with held deed area 750, `parcel_price` 6,937,500, owners A/B/C at 33.333; parcel X held 100% by A with an Old deed of area 999 also on A)
  - `test_summary_area_counts_held_deeds_by_share`: A's `summary()['area_total_sqm']` = `round(750*0.33333 + X_area, 2)` (the 999 old deed is not counted).
  - `test_portfolio_value_uses_the_held_deed_share`: A's `portfolio()['value']` = `6937500*0.33333 + X_price`.
  - `test_insights_area_and_value_are_weighted`: `OwnerInsightsService::for(A)['portfolio']['area']` uses 750 × 0.33333 for parcel 2018.
  - `test_linked_groups_show_a_shared_deed_under_each_holder`: parent P is `parent_owner_id` of 2018 → `groups(P)` has three groups (A, B, C), each with the 2018 row and `share` ≈ 0.33333, `area` ≈ 250.
  - `test_linked_totals_count_a_shared_parcel_once`: `totals(P)` = `parcels: 1`, `area` ≈ 750, `value` ≈ 6937500.
- [ ] **Step 2: Run** `php artisan test --filter=WeightedHoldingsTest`: FAIL.
- [ ] **Step 3: Implement.** `summary()` area = Σ over `owner->parcels()` (with `heldDeed`) of `deed_area × share`; `areaExtremes()` unchanged (spec §7.4). `LinkedParcelsService::row()` becomes `rows(Parcel): list<array>` (one per holder). The group label is that holder's name, and a parcel with no held deed stays one row under holder 0.
- [ ] **Step 4: Run** the filter, then `php artisan test tests/Feature/Portal/LinkedParcelsTest.php tests/Feature/OwnerStatisticsServiceTest.php`. Expected: PASS. If an existing assertion encoded the old unweighted or superseded-deed area, update only that number and note it in the commit message; behaviour changed by spec §7.

### Task 3: Weighted portfolio summaries

**Files:**
- Modify: `app/Services/Owner/OwnerPortfolioService.php` (`summary()`, the unassigned summary)
- Test: `tests/Feature/Owner/WeightedHoldingsTest.php` (add)

- [ ] **Step 1: Write failing test** `test_portfolio_summary_is_weighted_by_share`: A puts 2018 in portfolio "عبدالله" → summary `area` ≈ geometry area × 0.33333, `value` ≈ `m_price × geometry area × 0.33333`. Geometry stays the source (spec §7, open question 18).
- [ ] **Step 2: Run**: FAIL.
- [ ] **Step 3: Implement**: compute per-parcel rows in PHP, using `OwnerHoldings::share($parcel, $portfolio->owner_id)`, instead of the SQL SUM.
- [ ] **Step 4: Run** the filter and `tests/Feature/OwnerPortfolioTest.php`, `tests/Feature/OwnerPortfoliosDashboardTest.php`: PASS.
- [ ] **Step 5: Checkpoint commit**: `feat(owners): count co-owned parcels by share`.

---

## Phase 1 — Accounts (no visible change)

### Task 4: Schema and models

**Files:**
- Create: `database/migrations/2026_11_11_100000_create_portal_accounts_tables.php`
- Create: `app/Models/PortalAccount.php`, `app/Models/PortalAccountOwner.php`, `app/Enums/PortalRelation.php`, `app/Enums/PortalGrantSource.php`
- Modify: `app/Models/Owner.php` (`portalGrants(): HasMany`)
- Test: `tests/Feature/Portal/PortalAccountModelTest.php`

**Interfaces:**
- Produces:
  - `portal_accounts`: `id, name string(150) null, phone string(30), phone_normalized string(30) unique, email null, is_active bool default true, session_version unsigned int default 1, last_owner_id FK owners null nullOnDelete, last_login_at timestamp null, timestamps`.
  - `portal_account_owners`: `id, portal_account_id FK cascade, owner_id FK cascade, relation string(10), can_manage bool default true, source string(10), needs_review bool default false, granted_by FK users null nullOnDelete, timestamps, unique(portal_account_id, owner_id), index(owner_id)`.
  - Nullable `portal_account_id` FK (nullOnDelete) on `personal_access_tokens`, `audit_logs`, `parcel_listings`, `listing_inquiries`, `modification_requests`, `parcel_photos`.
  - `PortalAccount`: `grants(): HasMany<PortalAccountOwner>`, `usableGrants(): HasMany` (`needs_review = false` and owner not soft-deleted), `owners(): BelongsToMany` via usable grants, `usableOwnerIds(): list<int>`, `defaultOwner(): ?Owner` (`last_owner_id` if usable, else oldest `self` grant, else oldest grant), `static findByPhone(string $phone): ?PortalAccount` (normalised, active only). It sets `phone_normalized` on saving, like `Owner`.
  - `PortalAccountOwner`: casts `relation` → `PortalRelation`, `source` → `PortalGrantSource`, bools. `account()`, `owner()`.
  - `PortalRelation::Self_ = 'self'`, `PortalRelation::Delegate = 'delegate'`. `PortalGrantSource::Migration|Import|Staff`.
- [ ] **Step 1: Write failing tests**
  - `test_phone_is_normalised_and_unique`: creating `0536664026` then `966536664026` throws `UniqueConstraintViolationException`.
  - `test_usable_grants_skip_pending_and_archived_owners`: grants on A (ok), B (`needs_review`), C (archived) → `usableOwnerIds() === [A]`.
  - `test_default_owner_prefers_last_then_self_then_oldest`.
  - `test_find_by_phone_ignores_inactive_accounts`.
- [ ] **Step 2: Run** `php artisan test --filter=PortalAccountModelTest`: FAIL.
- [ ] **Step 3: Implement** migration and models; `down()` drops the columns and tables.
- [ ] **Step 4: Run** `php artisan migrate:fresh --env=testing` (via the test run) and the filter: PASS.

### Task 5: PortalAccessService

**Files:**
- Create: `app/Services/Portal/PortalAccessService.php`
- Test: `tests/Feature/Portal/PortalAccessServiceTest.php`

**Interfaces:**
- Produces (all in a DB transaction, all write an `AuditLog` with `target_type = 'portal_account'`, `user_id` = staff when given, `portal_account_id`, `owner_id`):
  - `accountForPhone(string $phone, ?string $name = null): PortalAccount`: firstOrCreate by normalised phone.
  - `grant(PortalAccount $a, Owner $o, PortalRelation $relation, bool $canManage, PortalGrantSource $source, ?User $by, bool $needsReview = false): PortalAccountOwner`: upsert on (account, owner). It never downgrades an existing usable grant to pending.
  - `revoke(PortalAccountOwner $grant, ?User $by): void`.
  - `approve(PortalAccountOwner $grant, User $by): void`. Approving a pending `self` grant sets `owners.phone` to the account phone and revokes the owner's previous `self` grant.
  - `setManage(PortalAccountOwner $grant, bool $canManage, User $by): void`.
  - `changeOwnerPhone(Owner $o, ?string $phone, ?User $by): void`: moves the `self` grant and writes `owners.phone` (the invariant). Null phone revokes the `self` grant.
  - `deactivate(PortalAccount $a, User $by)`, `activate(...)`, `rename(PortalAccount $a, ?string $name, User $by)`, `signOutEverywhere(PortalAccount $a, User $by)`: increments `session_version` and deletes the account's tokens.
  - `ownersSharingPhone(string $phone, ?int $exceptOwnerId = null): Collection<Owner>`: owners other accounts or `owners.phone` already tie to this phone. Used by the staff confirm and the import preview.
- [ ] **Step 1: Write failing tests**
  - `test_grant_is_idempotent` (two calls → one row).
  - `test_a_pending_regrant_never_downgrades_a_usable_grant`.
  - `test_change_owner_phone_moves_the_self_grant_and_keeps_the_invariant`: old account loses the grant, new account has `self`, `owners.phone` = new.
  - `test_change_owner_phone_keeps_delegate_grants`.
  - `test_approving_a_pending_self_grant_moves_the_phone`.
  - `test_sign_out_everywhere_bumps_the_version_and_drops_tokens`.
  - `test_every_write_is_audited`: count AuditLog rows with `target_type = 'portal_account'`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run**: PASS.

### Task 6: Test sign-in helper

**Files:**
- Create: `tests/Concerns/SignsInOwners.php`
- Modify: the eight test files that call `actingAs($owner, 'owner')` or `Sanctum::actingAs` (list: `tests/Feature/Listings/ParcelListingTest.php`, `tests/Feature/Portal/LinkedParcelsTest.php`, `tests/Feature/Portal/OwnerPhotosAndPortfoliosTest.php`, `tests/Feature/Portal/PortalAuthTest.php`, `tests/Feature/Portal/PortalParcelsTest.php`, `tests/Feature/Security/EncryptionAtRestTest.php`, `tests/Feature/Security/OwnerSeesOnlyOwnDataTest.php`, `tests/Feature/Security/SecurityHardeningTest.php`, `tests/Feature/Api/OwnerApiTest.php`)

**Interfaces:**
- Produces (trait):
  - `actingAsOwner(Owner $owner, ?PortalAccount $account = null): static`: makes a `self` account from the owner's phone (or a synthetic one when null) through `PortalAccessService`. It logs the owner into the `owner` guard and puts `portal_account_id` and `portal_session_version` in the session.
  - `apiAsOwner(Owner $owner, ?PortalAccount $account = null): static`: creates a real Sanctum token with `portal_account_id` set and calls `withToken()`.
- [ ] **Step 1:** Add the trait, then replace each `actingAs($x, 'owner')` with `actingAsOwner($x)` and each `Sanctum::actingAs($x)` with `apiAsOwner($x)`. Assertions untouched.
- [ ] **Step 2:** Run `php artisan test`. Expected: the same pass count as before Task 6 (the middleware does not exist yet).

### Task 7: Backfill existing owners

**Files:**
- Create: `app/Support/Portal/LegacyAccountBackfill.php`, `app/Console/Commands/PortalAccountsAudit.php`
- Create: `database/migrations/2026_11_11_100100_backfill_portal_accounts.php`
- Test: `tests/Feature/Portal/LegacyAccountBackfillTest.php`

**Interfaces:**
- Produces:
  - `LegacyAccountBackfill::plan(): list<array{phone: string, chosen_owner_id: int, pending_owner_ids: list<int>}>`. The chosen owner is the result of the **legacy query**: `Owner::where('phone_normalized', $p)->first()`, the same as `findOwnerByPhone` today.
  - `LegacyAccountBackfill::run(): array{accounts: int, grants: int, pending: int, tokens_linked: int, tokens_dropped: int}`. It grants `self` (`source = migration`) to the chosen owner, adds pending grants for the rest, links tokens via their owner's account, and deletes tokens whose owner has no account.
  - `portal:accounts-audit` prints `plan()` as a table (phone, chosen owner, pending owners), read-only.
- [ ] **Step 1: Write failing tests**
  - `test_a_unique_phone_gets_one_usable_self_grant`.
  - `test_a_shared_phone_opens_only_the_owner_that_opens_it_today`: two owners with one phone → `usableOwnerIds()` equals `[legacy first()]` and the other is pending.
  - `test_archived_owners_get_no_account`.
  - `test_tokens_are_linked_or_dropped`.
  - `test_running_twice_changes_nothing`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement**; the migration calls `app(LegacyAccountBackfill::class)->run()`, and its `down()` truncates both tables. **Step 4: Run**: PASS.

### Task 8: Web sign-in through the account + middleware

**Files:**
- Create: `app/Support/Portal/PortalContext.php`, `app/Http/Middleware/EnsurePortalAccount.php`
- Modify: `app/Services/Auth/OwnerOtpService.php`, `app/Http/Controllers/Portal/AuthController.php`, `bootstrap/app.php` (aliases), `routes/portal.php` (add `portal.account` to the `auth:owner` group)
- Test: `tests/Feature/Portal/PortalAccountSignInTest.php`

**Interfaces:**
- Produces:
  - `OwnerOtpService::findAccountByPhone(string $phone): ?PortalAccount`: an active account with ≥1 usable grant. `issue(PortalAccount $a): int`, `verify(PortalAccount $a, string $code): bool`. The cache keys become `portal_otp_{account_id}`, and the test code is keyed by the account phone. `findOwnerByPhone()` stays for `LegacyAccountBackfill` only.
  - Session keys: `portal_otp_account_id` (pending), `portal_account_id`, `portal_session_version`.
  - `PortalContext` (scoped singleton): `account(): ?PortalAccount`, `owner(): ?Owner`, `ownerIds(): list<int>`, `grant(): ?PortalAccountOwner` (current owner), `canManage(): bool`, `isSelf(): bool`, `hasMany(): bool`, `signIn(PortalAccount $a, Owner $o): void` (web), `switchTo(Owner $o): void` (throws `AuthorizationException` when not usable).
  - `EnsurePortalAccount` (`portal.account`). Web: no account, inactive account, version mismatch, or no usable grant on any owner → logout + redirect to `portal.login`. Current owner no longer usable → `switchTo(defaultOwner())`. API: see Task 9.
- [ ] **Step 1: Write failing tests**
  - `test_a_single_owner_phone_signs_straight_into_its_owner` (same assertions as today).
  - `test_a_phone_whose_only_grant_is_pending_is_not_registered`.
  - `test_a_deactivated_account_is_signed_out_on_the_next_request`.
  - `test_a_revoked_grant_moves_the_session_to_another_owner_or_out`.
  - `test_sign_out_everywhere_ends_the_web_session`.
  - `test_a_session_without_an_account_is_signed_out`: plain `actingAs($owner, 'owner')`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement**; after `verify`, `signIn($account, $account->defaultOwner())` and set `last_login_at`. **Step 4: Run** the filter and the whole suite: PASS.

### Task 9: API sign-in through the account

**Files:**
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`, `routes/api.php` (add `portal.account` to the `auth:sanctum` group), `app/Http/Middleware/EnsurePortalAccount.php` (API branch), `app/Http/Resources/` (new `PortalAccountResource.php`)
- Test: `tests/Feature/Api/AccountApiTest.php`

**Interfaces:**
- Produces: `verify-otp` response = today's `{token, owner}` + `account: {id, name, phone}` + `owners: [{id, name, relation, can_manage}]`. Tokens carry `portal_account_id`, and a new sign-in deletes **that account's** tokens only. API middleware: token without `portal_account_id`, inactive account, or a current owner no longer usable → 401 `api.unauthenticated`.
- [ ] **Step 1: Write failing tests**: `test_verify_returns_the_owner_list`, `test_a_new_sign_in_revokes_only_this_accounts_tokens`, `test_a_revoked_grant_makes_the_token_401`, `test_a_token_without_an_account_is_401`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run** the filter and `tests/Feature/Api`: PASS.
- [ ] **Step 5: Checkpoint commit**: `feat(portal): sign in through portal accounts`.

---

## Phase 2 — Several owners

### Task 10: Switching and view-only

**Files:**
- Create: `app/Http/Controllers/Portal/AccountController.php`, `app/Http/Middleware/EnsurePortalCanManage.php`
- Modify: `routes/portal.php` (`POST /switch/{owner}` → `portal.switch`; `portal.manage` on: listing store/destroy, interest, photos store, modification-requests store, profile update), `resources/views/portal/layout.blade.php` (owner switcher in the header when `hasMany()`), `app/Providers/AppServiceProvider.php` (compose `portalContext`), `app/Http/Requests/Portal/UpdateProfileRequest.php` + `ProfileController` (a delegate edits only the account `name`/`email`)
- Test: `tests/Feature/Portal/SwitchOwnerTest.php`

**Interfaces:**
- Consumes: `PortalContext::switchTo()`, `canManage()`, `isSelf()`.
- Produces: route `portal.switch`; middleware alias `portal.manage` (403 when `! canManage()`); Blade variable `$portalContext`.
- [ ] **Step 1: Write failing tests**
  - `test_switching_to_a_granted_owner_changes_the_owner_guard_and_regenerates_the_session`.
  - `test_switching_to_an_owner_without_a_grant_is_forbidden`.
  - `test_after_switching_another_owners_parcel_is_still_not_found`: A's parcel URL while acting for B → 404 (Review Focus 1).
  - `test_view_only_cannot_list_upload_request_or_show_interest`: each route → 403.
  - `test_a_delegate_cannot_edit_the_owners_profile`.
  - `test_the_switcher_lists_every_usable_owner_and_hides_pending_ones`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run**: PASS.

### Task 11: Overview page

**Files:**
- Create: `app/Services/Portal/AccountOverviewService.php`, `resources/views/portal/overview.blade.php`
- Modify: `AccountController` (`overview()`, `overviewMap()`), `routes/portal.php` (`GET /overview` → `portal.overview`, `GET /overview/map` → `portal.overview.map`), `AuthController` (after sign-in, `hasMany()` → `portal.overview`), the layout (nav item "نظرة شاملة" when `hasMany()`), `lang/{ar,en}/portal.php`
- Test: `tests/Feature/Portal/AccountOverviewTest.php`

**Interfaces:**
- Consumes: `OwnerHoldings::shares()`.
- Produces: `AccountOverviewService::for(PortalAccount $a, bool $showValue): array{totals: array{parcels: int, area: float, value: float|null}, owners: list<array{owner_id: int, name: string, relation: string, parcels: int, area: float, value: float|null}>}` and `map(PortalAccount $a): array` (GeoJSON FeatureCollection; each feature has `owner_ids: list<int>` and `color_index: int`). Value per parcel = `parcel_price ?? m_price × held deed area` (the `LinkedParcelsService` definition). Each owner card's area and value are × that owner's share. Totals are Σ over **distinct** parcels of figure × (Σ shares of the account's owners on it).
- [ ] **Step 1: Write failing tests** (fixture = the Bin Talib family: نجلاء 1066 @100, سعود الصحافة @100, عبدالله/أحمد/بندر on 2018 @33.333 with `parcel_price` 6,937,500; one account on all five)
  - `test_the_family_account_sees_three_parcels_and_five_cards`.
  - `test_each_brother_sees_parcel_2018_at_a_third`: card value ≈ 2,312,477 (6,937,500 × 0.33333).
  - `test_the_shared_parcel_counts_once_at_full_value`: totals value ≈ 4,560,000 + 65,254,000 + 6,937,500 (within 1).
  - `test_a_partner_outside_the_account_leaves_only_the_insiders_share`: drop بندر's grant → 2018 contributes ≈ 0.66666.
  - `test_values_hide_when_the_show_value_switch_is_off`.
  - `test_a_single_owner_account_is_redirected_to_the_dashboard`.
  - `test_signing_in_with_several_owners_lands_on_the_overview`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement**; the page follows `portal/dashboard.blade.php` styling, and the map reuses the dashboard's map script with features coloured by `color_index`. Clicking a feature posts to `portal.switch` for its first owner and continues to `portal.parcels.show`. **Step 4: Run**: PASS.

### Task 12: Market across the account, and who acted

**Files:**
- Modify: `app/Models/ParcelListing.php` (`scopeVisibleTo(Builder $q, Owner $viewer, array $accountOwnerIds = [])`), `app/Services/Listings/ParcelListingService.php` (`market()`, `marketMap()`, `present()`, `inquire()`, `request()`, `withdraw()`), `app/Http/Controllers/Portal/ListingController.php`, `app/Http/Controllers/Api/V1/ListingController.php`, `ModificationRequestController` (portal + API), `ParcelPhotoController` (portal + API)
- Test: `tests/Feature/Listings/AccountMarketTest.php`

**Interfaces:**
- Consumes: `PortalContext::ownerIds()`, `PortalContext::account()`.
- Produces: listings, inquiries, modification requests and owner photos store `portal_account_id` of the acting account. `present()` marks `is_mine` when the listing owner is in the account.
- [ ] **Step 1: Write failing tests**: `test_a_listing_of_any_owner_in_the_account_is_marked_mine`, `test_interest_in_an_own_account_listing_is_refused`, `test_a_listing_blocked_for_one_owner_is_hidden_from_the_whole_account`, `test_actions_record_the_acting_account`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run** the filter and `tests/Feature/Listings`: PASS.
- [ ] **Step 5: Checkpoint commit**: `feat(portal): one phone, several owners`.

---

## Phase 3 — Staff dashboard

### Task 13: Owner panel and phone sync

**Files:**
- Create: `app/Livewire/Owners/PortalAccessPanel.php`, `resources/views/livewire/owners/portal-access-panel.blade.php`
- Create: `database/migrations/2026_11_11_100200_add_portal_account_permissions.php` (`portal_accounts.view` ← `owners.edit`, `portal_accounts.manage` ← `owners.edit`; same pattern as the listings migration)
- Modify: `app/Livewire/Forms/OwnerForm.php` (`store()`/`update()` route the phone through `PortalAccessService::changeOwnerPhone()`), `app/Livewire/Owners/OwnerFormModal.php` (confirm step when `ownersSharingPhone()` is not empty), `app/Livewire/Owners/OwnerEditForm.php` (same), the owner expand view in `resources/views/livewire/owners/owner-index.blade.php` (mount the panel), `database/seeders/RolesAndPermissionsSeeder.php`, `app/Livewire/Settings/RoleManager.php` (group), `lang/{ar,en}/owners.php`
- Test: `tests/Feature/Owners/PortalAccessPanelTest.php`

**Interfaces:**
- Produces: Livewire `owners.portal-access-panel` with `ownerId` and actions `addPhone(string $phone, string $relation, bool $canManage)`, `revoke(int $grantId)`, `toggleManage(int $grantId)`. A user scoped by `OwnerScope::ownerIds()` sees and acts only on owners in scope.
- [ ] **Step 1: Write failing tests**: `test_staff_see_which_phones_open_the_owner`, `test_adding_a_phone_creates_or_reuses_the_account`, `test_saving_a_new_owner_phone_moves_the_self_grant`, `test_saving_a_phone_that_opens_another_owner_asks_first`, `test_a_user_without_the_permission_is_forbidden`, `test_scoped_staff_cannot_touch_an_owner_outside_scope`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run** the filter and `tests/Feature/OwnerEditTest.php`: PASS.

### Task 14: Accounts page and review tab

**Files:**
- Create: `app/Livewire/PortalAccounts/AccountIndex.php`, `resources/views/livewire/portal-accounts/account-index.blade.php`, `resources/views/portal-accounts/index.blade.php`
- Modify: `routes/web.php` (`GET /portal-accounts` → `portal-accounts.index`, `can:portal_accounts.view`), `app/View/Components/Sidebar.php` (under the group that holds `owners.index`), `lang/{ar,en}/portal_accounts.php` (new)
- Test: `tests/Feature/PortalAccounts/AccountIndexTest.php`

**Interfaces:**
- Produces: tabs `all` / `review`. Actions: `create(string $phone, ?string $name, array $grants)` (`$grants` = list of `{owner_id, relation, can_manage}`), `rename`, `deactivate`, `activate`, `signOutEverywhere`, `approve(int $grantId)`, `reject(int $grantId)`. Whole-account actions are allowed for scoped staff only when every owner of the account is in scope. Owners out of scope show as a count, without names.
- [ ] **Step 1: Write failing tests**: `test_creating_an_office_account_over_five_owners_in_one_step`, `test_the_review_tab_lists_pending_grants_and_approving_one_activates_it`, `test_deactivating_ends_the_accounts_sessions`, `test_scoped_staff_see_out_of_scope_owners_only_as_a_count`, `test_scoped_staff_cannot_deactivate_an_account_reaching_outside_scope`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run**: PASS.
- [ ] **Step 5: Checkpoint commit**: `feat(owners): staff manage portal accounts`.

---

## Phase 4 — Import

### Task 15: Grants from the import file

**Files:**
- Modify: `app/Services/Import/ParcelGeoJsonImporter.php` (`options()` gains `shared_phones: bool`; the owner loop stops writing `phone` / `phone_normalized` itself and calls `PortalAccessService`; the result `warnings` gains the pending phones), `app/Livewire/Imports/ImportWizard.php` + its view (checkbox "اقبل الرقم الواحد لعدة ملاك"; preview section "أرقام تفتح أكثر من مالك"), `database/data/templates/build_gdb_template.py` (`Phone` field guide text), `docs/import-runbook.md`, `lang/{ar,en}/imports.php`
- Test: `tests/Feature/Import/ImportPortalAccountsTest.php`

**Interfaces:**
- Consumes: `PortalAccessService::accountForPhone()`, `grant()`, `ownersSharingPhone()`.
- Produces:
  - `ParcelGeoJsonImporter::sharedPhones(string $path): list<array{phone: string, owners: list<string>}>`: phones that would open more than one owner (file + DB). Used by the wizard preview.
  - Import rule per row with a phone, by case:
    - (a) owner has no phone and the phone opens no one else: write the phone and grant `self` (`source = import`).
    - (b) the phone opens other owners: with `shared_phones` on, same as (a); with it off, a **pending** `self` grant.
    - (c) owner already has a different phone: do **not** write it; add a pending `self` grant for the new phone.
    - (d) same phone as on record: ensure the `self` grant exists.
- [ ] **Step 1: Write failing tests** (build the Bin Talib first file as GeoJSON in the test: five rows, one phone `536664026`, 2018 in three rows at 33.333)
  - `test_with_shared_phones_on_one_account_opens_the_five_owners`.
  - `test_with_shared_phones_off_one_owner_opens_and_four_wait_for_review`.
  - `test_a_phone_differing_from_the_record_is_not_written_and_waits`.
  - `test_a_typo_matching_another_owner_does_not_grant_access`.
  - `test_reimporting_the_same_file_adds_no_grants`.
  - `test_the_preview_lists_the_shared_phone_with_its_owners`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement**; the regenerated `resources/templates/sakuki-gdb-template.zip` needs GDAL ≥ 3.7, so only the builder script text changes here (noted in the runbook). **Step 4: Run** the filter and `tests/Feature/Import`: PASS.
- [ ] **Step 5: Checkpoint commit**: `feat(import): one phone may open several owners`.

---

## Phase 5 — Mobile API

### Task 16: Switch owner and overview over the API

**Files:**
- Create: `app/Http/Controllers/Api/V1/AccountController.php`
- Modify: `routes/api.php` (`POST auth/switch-owner` → `api.auth.switch-owner`, `GET account/overview` → `api.account.overview`, both under `auth:sanctum` + `portal.account`); the API `manage` routes get `portal.manage`
- Test: `tests/Feature/Api/AccountApiTest.php` (add)

**Interfaces:**
- Consumes: `AccountOverviewService::for()`, `PortalContext`.
- Produces: `switch-owner {owner_id}` → `{token, owner}`, deleting the current token. `account/overview` → `AccountOverviewService::for()` output.
- [ ] **Step 1: Write failing tests**: `test_switching_issues_a_token_for_the_other_owner`, `test_switching_to_an_ungranted_owner_is_403`, `test_the_old_token_stops_working_after_a_switch`, `test_overview_matches_the_web_figures`, `test_view_only_token_cannot_post_a_listing`.
- [ ] **Step 2: Run**: FAIL. **Step 3: Implement. Step 4: Run**: PASS.

### Task 17: Whole-suite verification

- [ ] **Step 1:** `php artisan test`: every test passes.
- [ ] **Step 2:** `vendor/bin/pint --test` and `vendor/bin/phpstan analyse --memory-limit=1G`: clean.
- [ ] **Step 3: Checkpoint commit**: `feat(api): switch owners and account overview`. Then push `feature/portal-accounts`.
