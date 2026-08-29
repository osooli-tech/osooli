<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\PresentationRequests\RequestIndex;
use App\Models\PresentationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PresentationRequestsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_requires_the_view_permission(): void
    {
        $this->withoutVite();

        $this->get('/presentation-requests')->assertRedirect('/login');
    }

    public function test_an_authorised_admin_can_see_the_page(): void
    {
        $this->withoutVite();

        $this->actingAsAdmin()
            ->get('/presentation-requests')
            ->assertOk();
    }

    public function test_it_lists_requests_newest_first(): void
    {
        $older = PresentationRequest::create(['name' => 'قديم', 'phone' => '0512345678']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = PresentationRequest::create(['name' => 'جديد', 'phone' => '0587654321']);

        Livewire::actingAs($this->admin())
            ->test(RequestIndex::class)
            ->assertSeeInOrder(['جديد', 'قديم']);
    }

    public function test_opening_the_page_marks_requests_as_read(): void
    {
        $request = PresentationRequest::create(['name' => 'سالم', 'phone' => '0512345678']);
        $this->assertNull($request->read_at);

        Livewire::actingAs($this->admin())
            ->test(RequestIndex::class);

        $this->assertNotNull($request->fresh()->read_at);
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'مدير',
            'email' => 'admin@presentation.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        Permission::findOrCreate('presentation_requests.view', 'web');
        $user->givePermissionTo('presentation_requests.view');

        return $user;
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin());
    }
}
