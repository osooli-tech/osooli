<?php

declare(strict_types=1);

use App\Support\Database\Dialect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teaches the QGIS editing layer about archiving.
 *
 * Without this the layer becomes a way around it in both directions: archived
 * parcels and deeds keep appearing on the map, and deleting a row from QGIS
 * still erases the parcel and everything cascading off it — the exact loss
 * archiving exists to prevent. A rule the dashboard enforces and the surveying
 * tool ignores is not a rule.
 *
 * The view and its three INSTEAD OF triggers are rebuilt as a unit because the
 * SELECT gains predicates and the DELETE function changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A view for ArcGIS/QGIS on the PostGIS database; MariaDB has no counterpart.
        if (! Dialect::isPostgres()) {
            return;
        }

        // DELETE now archives. It also stamps archived_by from the mapping set
        // by the application; when QGIS connects directly there is no Laravel
        // user to name, so the column is left null rather than guessed at.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.parcels_full_delete()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $BODY$
            BEGIN
                UPDATE deeds   SET deleted_at = NOW() WHERE parcel_id = OLD.id AND deleted_at IS NULL;
                UPDATE parcels SET deleted_at = NOW() WHERE id        = OLD.id AND deleted_at IS NULL;

                -- parcel_boundaries has no archiving of its own; it stays
                -- attached to the archived parcel and comes back with it.
                RETURN OLD;
            END;
            $BODY$;
        SQL);

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
                -- The deed predicate rides on the JOIN, not in WHERE: in WHERE
                -- it would turn this LEFT JOIN into an inner one and drop every
                -- parcel that has no live deed from the layer entirely.
                LEFT JOIN deeds d ON d.parcel_id = p.id AND d.deleted_at IS NULL
            WHERE p.deleted_at IS NULL
            ORDER BY p.id,
                     CASE WHEN d.deed_status = 'محدث' THEN 0 ELSE 1 END,
                     d.id DESC;
        SQL);

        foreach (self::TRIGGERS as $trigger => $event) {
            $function = 'parcels_full_'.strtolower($event);

            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON public.parcels_full");
            DB::statement("CREATE TRIGGER {$trigger}
                INSTEAD OF {$event} ON public.parcels_full
                FOR EACH ROW
                EXECUTE FUNCTION public.{$function}()");
        }
    }

    /**
     * Restores the pre-archiving shape: the view without the archiving
     * predicates, and a DELETE that really deletes.
     */
    public function down(): void
    {
        // A view for ArcGIS/QGIS on the PostGIS database; MariaDB has no counterpart.
        if (! Dialect::isPostgres()) {
            return;
        }

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

        DB::statement('DROP VIEW IF EXISTS public.parcels_full');

        DB::statement(<<<'SQL'
            CREATE VIEW public.parcels_full AS
            SELECT DISTINCT ON (p.id)
                   p.id, p.parcel_no, p.geo_id, p.plan_id, p.parent_parcel_id,
                   p.asset_type, p.land_transaction, p.allocation_method, p.fall_in,
                   p.m_price, p.parcel_price, p.created_at, p.updated_at,
                   pl.plan_no, pl.district_id,
                   pb.id AS boundary_id, pb.n_border, pb.s_border, pb.e_border, pb.w_border,
                   pb.n_dim, pb.s_dim, pb.e_dim, pb.w_dim, pb.measured_area,
                   pb.matches_deed, pb.survey_date, pb.engineering_office_id,
                   d.id AS deed_id, d.deed_no, d.deed_date_hijri, d.deed_area,
                   d.deed_status, d.deed_class,
                   p.geom
            FROM parcels p
                LEFT JOIN plans pl ON pl.id = p.plan_id
                LEFT JOIN parcel_boundaries pb ON pb.parcel_id = p.id
                LEFT JOIN deeds d ON d.parcel_id = p.id
            ORDER BY p.id,
                     CASE WHEN d.deed_status = 'محدث' THEN 0 ELSE 1 END,
                     d.id DESC;
        SQL);

        foreach (self::TRIGGERS as $trigger => $event) {
            $function = 'parcels_full_'.strtolower($event);

            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON public.parcels_full");
            DB::statement("CREATE TRIGGER {$trigger}
                INSTEAD OF {$event} ON public.parcels_full
                FOR EACH ROW
                EXECUTE FUNCTION public.{$function}()");
        }
    }

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
};
