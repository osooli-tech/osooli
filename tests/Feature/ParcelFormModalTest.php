<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Parcels\ParcelFormModal;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * geo_id is the join key every GDB sync matches a parcel by, and
 * source_gdb_id/last_synced_at are pure sync bookkeeping — none of the three
 * may be changed by hand from this form once a row exists, no matter what a
 * request submits, or the next sync silently orphans the parcel's history.
 */
class ParcelFormModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_parcel_sets_its_geo_id(): void
    {
        Livewire::actingAs($this->editor())
            ->test(ParcelFormModal::class)
            ->call('openCreate')
            ->set('form.geoId', 'geo-new-1')
            ->call('save');

        $this->assertDatabaseHas('parcels', ['geo_id' => 'geo-new-1']);
    }

    public function test_editing_a_parcel_cannot_change_its_geo_id(): void
    {
        $parcel = $this->makeParcel(['geo_id' => 'geo-original', 'source_gdb_id' => 42]);

        Livewire::actingAs($this->editor())
            ->test(ParcelFormModal::class)
            ->call('openEdit', $parcel->id)
            ->set('form.geoId', 'geo-tampered')
            ->call('save');

        $this->assertDatabaseHas('parcels', ['id' => $parcel->id, 'geo_id' => 'geo-original']);
    }

    public function test_editing_a_parcel_cannot_change_its_sync_bookkeeping(): void
    {
        $parcel = $this->makeParcel([
            'geo_id' => 'geo-2',
            'source_gdb_id' => 42,
            'last_synced_at' => '2026-01-01 00:00:00',
        ]);

        Livewire::actingAs($this->editor())
            ->test(ParcelFormModal::class)
            ->call('openEdit', $parcel->id)
            ->set('form.sourceGdbId', '999')
            ->set('form.lastSyncedAt', '2026-06-01T00:00')
            ->call('save');

        $parcel->refresh();
        $this->assertSame(42, $parcel->source_gdb_id);
        $this->assertSame('2026-01-01 00:00:00', $parcel->last_synced_at?->format('Y-m-d H:i:s'));
    }

    public function test_editing_a_parcel_still_saves_its_other_fields(): void
    {
        $parcel = $this->makeParcel(['geo_id' => 'geo-3']);

        Livewire::actingAs($this->editor())
            ->test(ParcelFormModal::class)
            ->call('openEdit', $parcel->id)
            ->set('form.parcelNo', '77')
            ->set('form.mPrice', '1500')
            ->call('save');

        $this->assertDatabaseHas('parcels', ['id' => $parcel->id, 'parcel_no' => '77', 'm_price' => 1500]);
    }

    private function makeParcel(array $attributes = []): Parcel
    {
        return Parcel::create(array_merge(['geo_id' => 'geo-'.uniqid()], $attributes));
    }

    private function editor(): User
    {
        $user = User::create([
            'name' => 'مهندس',
            'email' => 'engineer@sakuki.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        Permission::findOrCreate('parcels.create', 'web');
        Permission::findOrCreate('parcels.edit', 'web');
        $user->givePermissionTo(['parcels.create', 'parcels.edit']);

        return $user;
    }
}
