<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard\OwnerPortfolios;
use App\Models\Owner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerPortfoliosDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_with_no_portfolios(): void
    {
        Livewire::test(OwnerPortfolios::class)->assertOk();
    }

    public function test_it_renders_with_a_few_portfolios(): void
    {
        $owner = Owner::create(['name' => 'مالك تجريبي']);
        $owner->portfolios()->create(['name' => 'مشروع أ']);
        $owner->portfolios()->create(['name' => 'مشروع ب']);

        Livewire::test(OwnerPortfolios::class)
            ->assertOk()
            ->assertSee('مشروع أ')
            ->assertSee('مشروع ب');
    }

    /**
     * Regression test: the component caps the list at 12 and shows a
     * "showing X of Y" note past that — a Blade view with two sibling root
     * elements in that branch (a grid div plus the note as a second root
     * element) is exactly what triggered Livewire's "missing root tag"
     * error in production, since the empty-list branch's single div masked
     * it in every other scenario tested until real data crossed 12.
     */
    public function test_it_renders_when_more_than_the_display_limit_exist(): void
    {
        $owner = Owner::create(['name' => 'مالك تجريبي']);

        for ($i = 1; $i <= 13; $i++) {
            $owner->portfolios()->create(['name' => "مشروع {$i}"]);
        }

        Livewire::test(OwnerPortfolios::class)
            ->assertOk()
            ->assertSee(__('dashboard.owner_portfolios_view_all'));
    }
}
