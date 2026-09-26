<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Archive\ArchiveIndex;
use App\Models\ArchivedValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The corrected-values tab says what each row is — which record, which
 * field, the value before and now — and every page carries its "what is
 * this page?" note.
 */
class ArchiveValuesExplainedTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_corrected_value_reads_as_record_field_before_and_now(): void
    {
        $plan = DB::table('plans')->insertGetId(['plan_no' => '8487', 'created_at' => now(), 'updated_at' => now()]);
        $parcel = DB::table('parcels')->insertGetId(['geo_id' => 'BUTAYN-05', 'parcel_no' => '5', 'plan_id' => $plan, 'created_at' => now(), 'updated_at' => now()]);
        $deed = DB::table('deeds')->insertGetId(['parcel_id' => $parcel, 'deed_no' => '410100000135', 'deed_date_hijri' => '1445-08-25', 'created_at' => now(), 'updated_at' => now()]);
        $old = DB::table('plans')->insertGetId(['plan_no' => 'بدون', 'created_at' => now(), 'updated_at' => now()]);

        ArchivedValue::create(['record_table' => 'deeds', 'record_id' => $deed, 'field' => 'deed_date_hijri', 'value' => '0445-08-25', 'reason' => 'سنة هجرية ناقصة الرقم الأول']);
        ArchivedValue::create(['record_table' => 'parcels', 'record_id' => $parcel, 'field' => 'plan_id', 'value' => (string) $old, 'reason' => 'القطعة بلا مخطط']);

        $user = User::factory()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'archive.view', 'guard_name' => 'web']);
        $user->givePermissionTo('archive.view');
        $this->actingAs($user);
        app()->setLocale('ar');

        Livewire::test(ArchiveIndex::class)
            ->call('switchTab', 'values')
            ->assertSee('الصك 410100000135 — القطعة BUTAYN-05')
            ->assertSee('تاريخ الصك')
            ->assertSee('0445-08-25')
            ->assertSee('1445-08-25')
            // An id is shown as what it points at: the plan's number.
            ->assertSee('بدون')
            ->assertSee('8487')
            ->assertSee(route('parcels.show', $parcel))
            ->set('category', 'deed')
            ->assertSee('0445-08-25')
            ->assertDontSee('القطعة بلا مخطط');
    }

    public function test_pages_explain_themselves(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'archive.view', 'guard_name' => 'web']);
        $user->givePermissionTo('archive.view');
        app()->setLocale('ar');

        $this->actingAs($user)->get(route('archive.index'))
            ->assertOk()
            ->assertSee('ما هذه الصفحة؟')
            ->assertSee(__('help.pages.archive_index.what'));

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(__('help.pages.dashboard.what'));
    }
}
