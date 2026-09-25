<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Reference\ReferenceIndex;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReferenceFiltersTest extends TestCase
{
    use RefreshDatabase;

    private City $riyadh;

    private City $jeddah;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'المملكة العربية السعودية']);
        $central = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة الرياض', 'name_en' => 'Riyadh']);
        $west = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة مكة المكرمة', 'name_en' => 'Makkah']);
        $this->riyadh = City::create(['region_id' => $central->id, 'name_ar' => 'الرياض', 'name_en' => 'Riyadh']);
        $this->jeddah = City::create(['region_id' => $west->id, 'name_ar' => 'جدة', 'name_en' => 'Jeddah']);

        // «النرجس» in both cities: the label must say which is which.
        District::create(['city_id' => $this->riyadh->id, 'name_ar' => 'النرجس', 'name_en' => 'An Narjis']);
        District::create(['city_id' => $this->jeddah->id, 'name_ar' => 'النرجس']);
        $malqa = District::create(['city_id' => $this->riyadh->id, 'name_ar' => 'الملقا', 'name_en' => 'Al Malqa']);
        Plan::create(['plan_no' => 'P-1', 'district_id' => $malqa->id]);
    }

    public function test_the_picker_searches_by_name_labels_the_parent_and_narrows_by_parent(): void
    {
        $this->actingAs($this->viewer());

        $this->getJson(route('reference.options', 'districts').'?q=النرجس')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['label' => 'النرجس — الرياض'])
            ->assertJsonFragment(['label' => 'النرجس — جدة']);

        $this->getJson(route('reference.options', 'districts').'?q=narjis&parent='.$this->riyadh->id)
            ->assertOk()
            ->assertExactJson([['id' => District::where('name_en', 'An Narjis')->value('id'), 'label' => 'النرجس — الرياض']]);

        $this->getJson(route('reference.options', 'nonsense'))->assertNotFound();
    }

    public function test_the_picker_needs_the_reference_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->getJson(route('reference.options', 'cities'))
            ->assertForbidden();
    }

    public function test_the_filters_narrow_each_tab(): void
    {
        $this->actingAs($this->viewer());

        $page = Livewire::test(ReferenceIndex::class)->call('selectTab', 'districts');
        $page->assertSee('النرجس')->assertSee('الملقا');

        $page->set('filterCity', (string) $this->jeddah->id)
            ->assertSee('النرجس')->assertDontSee('الملقا');

        $page->call('clearFilters')->set('filterUsage', 'used')
            ->assertSee('الملقا')->assertDontSee('النرجس');

        // Picking a region clears a city chosen under another one.
        $page->set('filterCity', (string) $this->riyadh->id)
            ->set('filterRegion', (string) $this->jeddah->region_id)
            ->assertSet('filterCity', '');
    }

    public function test_plans_can_be_filtered_by_city(): void
    {
        $this->actingAs($this->viewer());

        Livewire::test(ReferenceIndex::class)
            ->set('filterCity', (string) $this->jeddah->id)
            ->assertDontSee('P-1')
            ->set('filterCity', (string) $this->riyadh->id)
            ->assertSee('P-1');
    }

    private function viewer(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['reference.view', 'reference.create', 'reference.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['reference.view', 'reference.create', 'reference.edit']);

        return $user;
    }
}
