# Edit Owner — Design

**Date:** 2026-08-07
**Status:** Approved defaults (user unavailable; recommended options applied)

## Goal

Let dashboard users correct an owner's details (name, national ID, phone, email,
WhatsApp) from the Owners page. Edit only — owners are created by GDB imports,
so no create or delete.

## Decisions

- **Fields:** all five Owner columns are editable. Phone doubles as the owner's
  mobile-app login identifier, so the field carries a hint noting that.
- **Access:** gated behind a new `owners.edit` Spatie permission, mirroring
  `users.edit`. Granted to `super_admin` and `manager`; not to `engineer`.
- **Pattern:** modal edit inside the existing `OwnerIndex` Livewire component,
  copied from the proven `UserIndex` modal (form props, validation, audit log,
  toast). No new routes or controllers.

## Changes

1. `database/seeders/RolesAndPermissionsSeeder.php` — add `owners.edit` to
   `PERMISSIONS` (roles pick it up through existing `except`/`null` configs).
2. `app/Livewire/Settings/RoleManager.php` — new `owners` group in `GROUPS`.
3. `app/Livewire/Owners/OwnerIndex.php` — modal state (`showEditModal`,
   `editingId`), form props for the five fields, `openEdit()`, `save()`.
   Server-side `owners.edit` authorization on both actions (Livewire actions
   are client-callable, so blade-only `@can` is not enough). Validation:
   name required; national_id nullable, unique ignoring self (matches the
   partial unique index); phone/whatsapp max 30; email nullable email.
   On save: `AuditLog` entry (`edit_owner` / target `owner`), success toast.
4. `resources/views/livewire/owners/owner-index.blade.php` — edit icon button
   in the actions column behind `@can('owners.edit')`; modal markup mirroring
   the users modal.
5. Lang (`ar` + `en`): `owners.php` (edit, edit_title, whatsapp, phone_hint,
   save, cancel, saved), `permissions.php` (`owners.edit`), `settings.php`
   (group + perm labels), `audit_logs.php` (`edit_owner` action label).

## Error handling

- Validation errors render inline per field, as in the users modal.
- Duplicate national ID is caught by the unique rule before hitting the
  partial unique index.
- 403 if a user without `owners.edit` invokes the Livewire actions directly.

## Testing

- Feature test: authorized user edits an owner (fields persisted, audit row
  written); unauthorized user gets 403; duplicate national_id rejected;
  clearing nullable fields stores NULL.
- Manual: `php artisan db:seed --class=RolesAndPermissionsSeeder` re-run adds
  the permission idempotently (`firstOrCreate` + `syncPermissions`).
