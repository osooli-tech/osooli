<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Notifications\NotificationBell;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\PresentationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_zero_when_there_is_nothing_new(): void
    {
        Livewire::actingAs($this->admin())
            ->test(NotificationBell::class)
            ->assertSee('0');
    }

    public function test_it_counts_unread_presentation_requests(): void
    {
        PresentationRequest::create(['name' => 'سالم', 'phone' => '0512345678']);
        PresentationRequest::create(['name' => 'فهد', 'phone' => '0587654321']);

        Livewire::actingAs($this->admin())
            ->test(NotificationBell::class)
            ->assertSee('2');
    }

    public function test_it_does_not_count_presentation_requests_already_read(): void
    {
        PresentationRequest::create(['name' => 'سالم', 'phone' => '0512345678', 'read_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(NotificationBell::class)
            ->assertSee('0');
    }

    public function test_it_counts_pending_modification_requests(): void
    {
        $this->createModificationRequest();

        Livewire::actingAs($this->admin())
            ->test(NotificationBell::class)
            ->assertSee('1');
    }

    public function test_polling_dispatches_a_sound_event_when_the_count_increases(): void
    {
        $component = Livewire::actingAs($this->admin())->test(NotificationBell::class);

        PresentationRequest::create(['name' => 'سالم', 'phone' => '0512345678']);

        $component->call('poll')->assertDispatched('play-notification-sound');
    }

    public function test_polling_does_not_dispatch_a_sound_event_when_the_count_is_unchanged(): void
    {
        PresentationRequest::create(['name' => 'سالم', 'phone' => '0512345678']);

        $component = Livewire::actingAs($this->admin())->test(NotificationBell::class);

        $component->call('poll')->assertNotDispatched('play-notification-sound');
    }

    private function createModificationRequest(): ModificationRequest
    {
        $parcel = Parcel::create(['geo_id' => 'P-'.uniqid()]);
        $owner = Owner::create(['name' => 'مالك']);

        return ModificationRequest::create([
            'parcel_id' => $parcel->id,
            'requested_by' => $owner->id,
            'field_name' => 'asset_type',
            'new_value' => 'سكني',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'مدير',
            'email' => 'admin@bell.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }
}
