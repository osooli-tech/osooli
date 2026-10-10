<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use App\Support\NationalId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts owners.national_id at rest.
 *
 * The number moves into ciphertext, and a keyed fingerprint of it goes into
 * national_id_hash. Ciphertext differs on every write, so the "one owner per
 * national id" rule moves with it: the unique index now sits on the
 * fingerprint, which is identical for identical numbers.
 *
 * Both directions are safe to re-run: a value already in the target form is
 * left as it is.
 */
return new class extends Migration
{
    private const OLD_UNIQUE = 'uq_owners_national_id';

    private const NEW_UNIQUE = 'uq_owners_national_id_hash';

    public function up(): void
    {
        $this->refuseDuplicates();

        if (! Schema::hasColumn('owners', 'national_id_hash')) {
            Schema::table('owners', fn (Blueprint $table) => $table->string('national_id_hash', 64)->nullable());
        }

        // The old index compares the values themselves, which ciphertext defeats.
        PortableSchema::dropIndex('owners', self::OLD_UNIQUE);
        Schema::table('owners', fn (Blueprint $table) => $table->text('national_id')->nullable()->change());

        foreach (DB::table('owners')->whereNotNull('national_id')->orderBy('id')->get(['id', 'national_id']) as $owner) {
            DB::table('owners')->where('id', $owner->id)
                ->update(NationalId::columns(NationalId::decrypt($owner->national_id)));
        }

        // An older national id kept in the archive is the same secret.
        foreach ($this->archivedIds()->get(['id', 'value']) as $row) {
            DB::table('archived_values')->where('id', $row->id)
                ->update(['value' => NationalId::encrypt(NationalId::decrypt($row->value))]);
        }

        // Unique where present; both PostgreSQL and MariaDB admit many NULLs.
        Schema::table('owners', fn (Blueprint $table) => $table->unique('national_id_hash', self::NEW_UNIQUE));
    }

    public function down(): void
    {
        PortableSchema::dropIndex('owners', self::NEW_UNIQUE);

        foreach (DB::table('owners')->whereNotNull('national_id')->orderBy('id')->get(['id', 'national_id']) as $owner) {
            DB::table('owners')->where('id', $owner->id)->update(['national_id' => NationalId::decrypt($owner->national_id)]);
        }

        foreach ($this->archivedIds()->get(['id', 'value']) as $row) {
            DB::table('archived_values')->where('id', $row->id)->update(['value' => NationalId::decrypt($row->value)]);
        }

        Schema::table('owners', function (Blueprint $table): void {
            $table->string('national_id', 50)->nullable()->change();
            $table->dropColumn('national_id_hash');
        });
        Schema::table('owners', fn (Blueprint $table) => $table->unique('national_id', self::OLD_UNIQUE));
    }

    /**
     * Two rows that only differ in spacing or digit script are the same number
     * once normalised. They would collide on the new index, so stop before
     * touching anything and name them — merging owners is a human decision.
     */
    private function refuseDuplicates(): void
    {
        $seen = [];
        $clashes = [];

        foreach (DB::table('owners')->whereNotNull('national_id')->orderBy('id')->get(['id', 'national_id']) as $owner) {
            $number = NationalId::normalise(NationalId::decrypt($owner->national_id));
            if ($number === null) {
                continue;
            }
            if (isset($seen[$number])) {
                $clashes[] = "owners #{$seen[$number]} and #{$owner->id}";
            }
            $seen[$number] ??= $owner->id;
        }

        if ($clashes !== []) {
            throw new RuntimeException('The same national id is on more than one owner: '.implode('; ', $clashes).'. Resolve these before encrypting.');
        }
    }

    private function archivedIds(): Builder
    {
        return DB::table('archived_values')->where('record_table', 'owners')->where('field', 'national_id')->whereNotNull('value');
    }
};
