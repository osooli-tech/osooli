<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Settings\DatabaseSettingsManager;
use App\Models\User;
use App\Support\Database\DatabaseSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DatabaseSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private string $settingsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsFile = sys_get_temp_dir().'/db-settings-page-'.bin2hex(random_bytes(4)).'.enc';
        config(['database.settings_file' => $this->settingsFile]);
        $this->app->forgetInstance(DatabaseSettings::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->settingsFile);

        parent::tearDown();
    }

    public function test_the_page_is_forbidden_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('settings.database'))
            ->assertForbidden();
    }

    public function test_an_administrator_sees_both_databases(): void
    {
        $this->actingAs($this->admin())
            ->get(route('settings.database'))
            ->assertOk()
            ->assertSee('PostgreSQL')
            ->assertSee('MariaDB')
            ->assertSee(__('database_settings.sync_now'));
    }

    public function test_details_that_cannot_connect_are_not_saved(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DatabaseSettingsManager::class)
            ->set('forms.mariadb', ['host' => '127.0.0.1', 'port' => '1', 'database' => 'x', 'username' => 'x', 'password' => 'x'])
            ->call('saveConnection', 'mariadb')
            ->assertHasNoErrors()
            ->assertSet('tests.mariadb.ok', false);

        $this->assertFileDoesNotExist($this->settingsFile);
    }

    public function test_working_details_are_saved_encrypted_and_the_password_is_not_sent_back(): void
    {
        $this->actingAs($this->admin());
        $name = config('database.default');
        $config = config("database.connections.{$name}");

        $form = [
            'host' => (string) $config['host'], 'port' => (string) $config['port'],
            'database' => (string) $config['database'], 'username' => (string) $config['username'],
            'password' => (string) $config['password'],
        ] + ($name === 'pgsql' ? ['sslmode' => 'prefer'] : []);

        Livewire::test(DatabaseSettingsManager::class)
            ->set("forms.{$name}", $form)
            ->call('saveConnection', $name)
            ->assertHasNoErrors()
            ->assertSet("tests.{$name}.ok", true)
            ->assertSet("forms.{$name}.password", '');

        $saved = app(DatabaseSettings::class)->connection($name);
        $this->assertSame($form['database'], $saved['database']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'database.settings_update']);
    }

    public function test_required_fields_and_a_valid_sync_time_are_enforced(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(DatabaseSettingsManager::class)
            ->set('forms.mariadb.host', '')
            ->call('test', 'mariadb')
            ->assertHasErrors(['forms.mariadb.host'])
            ->set('syncTime', '25:99')
            ->call('saveSync')
            ->assertHasErrors(['syncTime']);
    }

    public function test_switching_to_an_unreachable_database_is_refused(): void
    {
        $this->actingAs($this->admin());
        $other = config('database.default') === 'pgsql' ? 'mariadb' : 'pgsql';

        app(DatabaseSettings::class)->saveConnection($other, ['host' => '127.0.0.1', 'port' => '1', 'database' => 'x', 'username' => 'x'], 'x');
        // What the next request's boot would do with the saved details.
        app(DatabaseSettings::class)->apply();

        Livewire::test(DatabaseSettingsManager::class)
            ->call('switchPrimary', $other)
            ->assertDispatched('toast', type: 'error', message: __('database_settings.switch_needs_schema'));

        $this->assertNotSame($other, app(DatabaseSettings::class)->all()['primary'] ?? null);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'database.manage', 'guard_name' => 'web']);
        $user->givePermissionTo('database.manage');

        return $user;
    }
}
