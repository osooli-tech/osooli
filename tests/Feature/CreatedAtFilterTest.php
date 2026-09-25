<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Owners\OwnerIndex;
use App\Livewire\Reference\ReferenceIndex;
use App\Models\Country;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreatedAtFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-01-10 09:00'));
        Owner::create(['name' => 'مالك يناير']);

        $this->travelTo(Carbon::parse('2026-03-05 23:30'));
        Owner::create(['name' => 'مالك مارس']);

        $this->travelBack();
    }

    public function test_the_range_narrows_the_list_and_includes_both_end_days(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(OwnerIndex::class)
            ->assertSee('مالك يناير')->assertSee('مالك مارس')
            ->set('createdFrom', '2026-03-05')
            ->assertSee('مالك مارس')->assertDontSee('مالك يناير')
            ->set('createdFrom', '')
            ->set('createdTo', '2026-01-10')
            ->assertSee('مالك يناير')->assertDontSee('مالك مارس')
            ->call('clearFilters')
            ->assertSee('مالك يناير')->assertSee('مالك مارس');
    }

    public function test_a_malformed_date_is_ignored_rather_than_breaking_the_query(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(OwnerIndex::class)
            ->set('createdFrom', '2026-02-30')
            ->set('createdTo', "'; drop table owners; --")
            ->assertOk()
            ->assertSee('مالك يناير')->assertSee('مالك مارس');
    }

    public function test_the_column_header_sorts_newest_first_then_flips(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        // The list's own order is by name, which puts March first.
        Livewire::test(OwnerIndex::class)
            ->assertSeeInOrder(['مالك مارس', 'مالك يناير'])
            ->call('sortByCreated')
            ->assertSet('createdSort', 'desc')
            ->assertSeeInOrder(['مالك مارس', 'مالك يناير'])
            ->call('sortByCreated')
            ->assertSet('createdSort', 'asc')
            ->assertSeeInOrder(['مالك يناير', 'مالك مارس']);
    }

    public function test_reference_tabs_filter_by_date_added_too(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(Permission::findOrCreate('reference.view', 'web'));
        $this->actingAs($viewer);

        $this->travelTo(Carbon::parse('2026-01-01'));
        Country::create(['name_ar' => 'دولة قديمة']);
        $this->travelTo(Carbon::parse('2026-06-01'));
        Country::create(['name_ar' => 'دولة حديثة']);
        $this->travelBack();

        Livewire::test(ReferenceIndex::class)
            ->call('selectTab', 'countries')
            ->set('createdFrom', '2026-05-01')
            ->assertSee('دولة حديثة')->assertDontSee('دولة قديمة');
    }
}
