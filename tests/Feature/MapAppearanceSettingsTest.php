<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MapAppearanceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MapAppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_defaults_when_nothing_is_saved(): void
    {
        $this->assertSame(MapAppearanceSetting::DEFAULTS, MapAppearanceSetting::current());
    }

    public function test_a_user_without_the_permission_cannot_change_colours(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->patchJson(route('map-colors.update'), ['parcels_fill' => '#111111'])
            ->assertForbidden();

        $this->assertSame(MapAppearanceSetting::DEFAULTS['parcels_fill'], MapAppearanceSetting::current()['parcels_fill']);
    }

    public function test_an_authorised_user_can_change_a_base_colour(): void
    {
        $response = $this->actingAs($this->admin())
            ->patchJson(route('map-colors.update'), ['parcels_fill' => '#111111']);

        $response->assertOk();
        $this->assertSame('#111111', MapAppearanceSetting::current()['parcels_fill']);
        // Untouched keys keep their default, not just whatever was posted.
        $this->assertSame(MapAppearanceSetting::DEFAULTS['parcels_outline'], MapAppearanceSetting::current()['parcels_outline']);
    }

    public function test_an_authorised_user_can_override_one_colour_by_stop(): void
    {
        $this->actingAs($this->admin())
            ->patchJson(route('map-colors.update'), [
                'colour_modes' => ['asset_type' => ['فيلا' => '#222222']],
            ])
            ->assertOk();

        $current = MapAppearanceSetting::current();
        $this->assertSame('#222222', $current['colour_modes']['asset_type']['فيلا']);
        $this->assertSame(MapAppearanceSetting::DEFAULTS['colour_modes']['asset_type']['أرض'], $current['colour_modes']['asset_type']['أرض']);
    }

    public function test_an_invalid_hex_colour_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->patchJson(route('map-colors.update'), ['parcels_fill' => 'not-a-colour'])
            ->assertUnprocessable();

        $this->assertSame(MapAppearanceSetting::DEFAULTS['parcels_fill'], MapAppearanceSetting::current()['parcels_fill']);
    }

    public function test_an_unknown_colour_mode_value_is_silently_dropped(): void
    {
        $this->actingAs($this->admin())
            ->patchJson(route('map-colors.update'), [
                'colour_modes' => ['asset_type' => ['غير موجود' => '#333333']],
            ])
            ->assertOk();

        $this->assertArrayNotHasKey('غير موجود', MapAppearanceSetting::current()['colour_modes']['asset_type']);
    }

    public function test_an_empty_payload_resets_everything_to_default(): void
    {
        MapAppearanceSetting::replaceOverrides(['parcels_fill' => '#111111']);
        $this->assertSame('#111111', MapAppearanceSetting::current()['parcels_fill']);

        $this->actingAs($this->admin())
            ->patchJson(route('map-colors.update'), [])
            ->assertOk();

        $this->assertSame(MapAppearanceSetting::DEFAULTS, MapAppearanceSetting::current());
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);

        Permission::findOrCreate('roles.manage', 'web');
        $user->givePermissionTo('roles.manage');

        return $user;
    }
}
