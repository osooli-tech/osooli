<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings the hand-applied `parcels_full` view into version control.
 *
 * The view and its INSTEAD OF triggers were created directly on the Neon
 * database in July 2026 so ArcGIS Pro could edit parcel, boundary and deed
 * attributes as one flat layer. That turned out not to work — ArcGIS Pro
 * refuses to edit any query layer containing joins, regardless of what the
 * database allows — so nothing in this application reads or writes the view.
 * It is reproduced here so a fresh database matches the live one and the
 * definition stops living only in a pgAdmin session.
 *
 * Brought up to date with three columns the original definition never had:
 * `parcels.m_price` and `parcels.parcel_price` (added after the view was
 * written) and `parcel_boundaries.matches_deed` (added a week before it, but
 * missed). Nothing else in the schema has changed under the view since —
 * `plans` and `deeds` are untouched, and `projects` / `buildings` are a
 * deliberately parcel-independent display layer. New `fall_in_enum` values
 * need no change here; the view passes the enum column straight through.
 *
 * Two defects in the original were fixed rather than documented, because the
 * live data proved they bite. `deeds.parcel_id` is not unique: 30 parcels
 * carry both a superseded paper deed (`3/276`-style) and its 12-digit
 * electronic replacement, which made the view return 323 rows for 293
 * parcels, and made the UPDATE trigger — keyed on `WHERE parcel_id` — write
 * the edited values over both deeds. The view is now `DISTINCT ON (p.id)`,
 * preferring the `محدث` deed and falling back to the newest, and the trigger
 * writes to `WHERE id = OLD.deed_id`, the single deed the row represents.
 *
 * Known rough edges, kept as-is because nothing depends on this view and
 * changing them would alter behaviour rather than document it:
 * - Superseded deeds are no longer visible through this view at all. It is an
 *   editing surface, not a history of ownership — read `deeds` for that.
 * - INSERT only creates a boundary row when `n_border` is given, so an insert
 *   carrying just `measured_area` or `matches_deed` drops those values.
 * - UPDATE has an upsert for deeds but not for boundaries: editing boundary
 *   fields on a parcel that has no boundary row silently does nothing.
 * - `plan_no`, `district_id`, `boundary_id` and `deed_id` are read-only: they
 *   come from the joined rows and no trigger writes them back. Editing them
 *   through the view is silently discarded — set `plan_id` instead.
 * - The view exposes every parcel. It bypasses the per-user owner scoping in
 *   `user_owner_scopes`, so it must not back any user-facing query.
 */
return new class extends Migration
{
    /**
     * Trigger name => the INSTEAD OF event it fires on.
     *
     * @var array<string, string>
     */
    private const TRIGGERS = [
        'trg_parcels_full_insert' => 'INSERT',
        'trg_parcels_full_update' => 'UPDATE',
        'trg_parcels_full_delete' => 'DELETE',
    ];

    public function up(): void
    {
        // Functions first — the triggers below reference them by name.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.parcels_full_insert()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $BODY$
            BEGIN
                INSERT INTO parcels (parcel_no, geo_id, plan_id, parent_parcel_id,
                                     asset_type, land_transaction, allocation_method,
                                     fall_in, m_price, parcel_price, geom)
                VALUES (NEW.parcel_no, NEW.geo_id, NEW.plan_id, NEW.parent_parcel_id,
                        NEW.asset_type, NEW.land_transaction, NEW.allocation_method,
                        NEW.fall_in, NEW.m_price, NEW.parcel_price, NEW.geom)
                RETURNING id INTO NEW.id;

                IF NEW.n_border IS NOT NULL THEN
                    INSERT INTO parcel_boundaries (parcel_id, n_border, s_border, e_border, w_border,
                                                   n_dim, s_dim, e_dim, w_dim, measured_area,
                                                   matches_deed, survey_date, engineering_office_id)
                    VALUES (NEW.id, NEW.n_border, NEW.s_border, NEW.e_border, NEW.w_border,
                            NEW.n_dim, NEW.s_dim, NEW.e_dim, NEW.w_dim, NEW.measured_area,
                            NEW.matches_deed, NEW.survey_date, NEW.engineering_office_id);
                END IF;

                IF NEW.deed_no IS NOT NULL THEN
                    INSERT INTO deeds (parcel_id, deed_no, deed_date_hijri, deed_area,
                                       deed_status, deed_class)
                    VALUES (NEW.id, NEW.deed_no, NEW.deed_date_hijri, NEW.deed_area,
                            NEW.deed_status, NEW.deed_class);
                END IF;

                RETURN NEW;
            END;
            $BODY$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.parcels_full_update()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $BODY$
            BEGIN
                UPDATE parcels SET
                    parcel_no         = NEW.parcel_no,
                    geo_id            = NEW.geo_id,
                    plan_id           = NEW.plan_id,
                    parent_parcel_id  = NEW.parent_parcel_id,
                    asset_type        = NEW.asset_type,
                    land_transaction  = NEW.land_transaction,
                    allocation_method = NEW.allocation_method,
                    fall_in           = NEW.fall_in,
                    m_price           = NEW.m_price,
                    parcel_price      = NEW.parcel_price,
                    geom              = NEW.geom,
                    updated_at        = NOW()
                WHERE id = OLD.id;

                UPDATE parcel_boundaries SET
                    n_border              = NEW.n_border,
                    s_border              = NEW.s_border,
                    e_border              = NEW.e_border,
                    w_border              = NEW.w_border,
                    n_dim                 = NEW.n_dim,
                    s_dim                 = NEW.s_dim,
                    e_dim                 = NEW.e_dim,
                    w_dim                 = NEW.w_dim,
                    measured_area         = NEW.measured_area,
                    matches_deed          = NEW.matches_deed,
                    survey_date           = NEW.survey_date,
                    engineering_office_id = NEW.engineering_office_id,
                    updated_at            = NOW()
                WHERE parcel_id = OLD.id;

                -- Write back to the one deed this row represents, not to every
                -- deed the parcel owns. 30 parcels carry both a superseded paper
                -- deed and its electronic replacement; `WHERE parcel_id` would
                -- silently overwrite the older one on every edit.
                IF OLD.deed_id IS NOT NULL THEN
                    UPDATE deeds SET
                        deed_no         = NEW.deed_no,
                        deed_date_hijri = NEW.deed_date_hijri,
                        deed_area       = NEW.deed_area,
                        deed_status     = NEW.deed_status,
                        deed_class      = NEW.deed_class,
                        updated_at      = NOW()
                    WHERE id = OLD.deed_id;
                ELSIF NEW.deed_no IS NOT NULL THEN
                    INSERT INTO deeds (parcel_id, deed_no, deed_date_hijri, deed_area,
                                       deed_status, deed_class)
                    VALUES (OLD.id, NEW.deed_no, NEW.deed_date_hijri, NEW.deed_area,
                            NEW.deed_status, NEW.deed_class);
                END IF;

                RETURN NEW;
            END;
            $BODY$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.parcels_full_delete()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $BODY$
            BEGIN
                DELETE FROM deeds             WHERE parcel_id = OLD.id;
                DELETE FROM parcel_boundaries WHERE parcel_id = OLD.id;
                DELETE FROM parcels           WHERE id        = OLD.id;
                RETURN OLD;
            END;
            $BODY$;
        SQL);

        // DROP + CREATE, not CREATE OR REPLACE: replacing a view may only append
        // columns at the end, and `m_price` / `parcel_price` / `matches_deed`
        // belong next to the fields they relate to. Dropping the view takes its
        // own triggers with it; they are recreated below. RESTRICT (the default)
        // is deliberate — if something ever does depend on the view, fail loudly.
        DB::statement('DROP VIEW IF EXISTS public.parcels_full');

        DB::statement(<<<'SQL'
            CREATE VIEW public.parcels_full AS
            SELECT DISTINCT ON (p.id)
                   p.id,
                   p.parcel_no,
                   p.geo_id,
                   p.plan_id,
                   p.parent_parcel_id,
                   p.asset_type,
                   p.land_transaction,
                   p.allocation_method,
                   p.fall_in,
                   p.m_price,
                   p.parcel_price,
                   p.created_at,
                   p.updated_at,
                   pl.plan_no,
                   pl.district_id,
                   pb.id AS boundary_id,
                   pb.n_border,
                   pb.s_border,
                   pb.e_border,
                   pb.w_border,
                   pb.n_dim,
                   pb.s_dim,
                   pb.e_dim,
                   pb.w_dim,
                   pb.measured_area,
                   pb.matches_deed,
                   pb.survey_date,
                   pb.engineering_office_id,
                   d.id AS deed_id,
                   d.deed_no,
                   d.deed_date_hijri,
                   d.deed_area,
                   d.deed_status,
                   d.deed_class,
                   p.geom
            FROM parcels p
                LEFT JOIN plans pl ON pl.id = p.plan_id
                LEFT JOIN parcel_boundaries pb ON pb.parcel_id = p.id
                LEFT JOIN deeds d ON d.parcel_id = p.id
            -- One row per parcel, carrying the deed that is actually in force:
            -- the updated one, falling back to the most recently added. Matches
            -- Parcel::latestDeed() in the application.
            ORDER BY p.id,
                     CASE WHEN d.deed_status = 'محدث' THEN 0 ELSE 1 END,
                     d.id DESC;
        SQL);

        // DROP + CREATE rather than CREATE OR REPLACE TRIGGER, which needs PG 14+.
        foreach (self::TRIGGERS as $trigger => $event) {
            $function = 'parcels_full_'.strtolower($event);

            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON public.parcels_full");
            DB::statement("CREATE TRIGGER {$trigger}
                INSTEAD OF {$event} ON public.parcels_full
                FOR EACH ROW
                EXECUTE FUNCTION public.{$function}()");
        }
    }

    public function down(): void
    {
        // Dropping the view takes its triggers with it.
        DB::statement('DROP VIEW IF EXISTS public.parcels_full');

        foreach (self::TRIGGERS as $event) {
            DB::statement('DROP FUNCTION IF EXISTS public.parcels_full_'.strtolower($event).'()');
        }
    }
};
