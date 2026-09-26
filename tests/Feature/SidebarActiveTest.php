<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * One sidebar entry lights up per page — the page's own, even when another
 * entry's routes share its prefix (/imports and /imports/gdb).
 */
class SidebarActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_pages_own_entry_is_active(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['imports.run', 'imports.create'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['imports.run', 'imports.create']);
        $this->actingAs($user);

        foreach ([route('imports.gdb') => 'imports/gdb', route('imports.index') => 'imports'] as $url => $expected) {
            $html = (string) $this->get($url)->assertOk()->getContent();

            preg_match_all('/<a href="([^"]+)"[^>]*aria-current=page/', $html, $active);
            $this->assertCount(1, $active[1], "one active entry on {$url}");
            $this->assertStringEndsWith($expected, $active[1][0]);
        }
    }
}
