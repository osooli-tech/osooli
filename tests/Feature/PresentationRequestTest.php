<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PresentationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_submit_a_presentation_request(): void
    {
        $this->postJson('/presentation-requests', [
            'name' => 'سالم العتيبي',
            'phone' => '0512345678',
            'message' => 'أرغب بمعرفة تفاصيل الباقة الاحترافية',
        ])->assertCreated();

        $this->assertDatabaseHas('presentation_requests', [
            'name' => 'سالم العتيبي',
            'phone' => '0512345678',
            'message' => 'أرغب بمعرفة تفاصيل الباقة الاحترافية',
        ]);
    }

    public function test_the_message_field_is_optional(): void
    {
        $this->postJson('/presentation-requests', [
            'name' => 'سالم العتيبي',
            'phone' => '0512345678',
        ])->assertCreated();

        $this->assertDatabaseHas('presentation_requests', [
            'name' => 'سالم العتيبي',
            'message' => null,
        ]);
    }

    public function test_the_name_is_required(): void
    {
        $this->postJson('/presentation-requests', [
            'phone' => '0512345678',
        ])->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('presentation_requests', 0);
    }

    public function test_the_phone_is_required(): void
    {
        $this->postJson('/presentation-requests', [
            'name' => 'سالم العتيبي',
        ])->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('presentation_requests', 0);
    }

    /** @dataProvider invalidPhoneProvider */
    public function test_the_phone_must_look_like_a_saudi_mobile_number(string $phone): void
    {
        $this->postJson('/presentation-requests', [
            'name' => 'سالم العتيبي',
            'phone' => $phone,
        ])->assertJsonValidationErrors('phone');
    }

    /** @return array<string, array{string}> */
    public static function invalidPhoneProvider(): array
    {
        return [
            'too short' => ['05123'],
            'wrong leading digit' => ['0412345678'],
            'letters' => ['05abcdefgh'],
        ];
    }

    /** @dataProvider validPhoneProvider */
    public function test_accepted_saudi_mobile_number_formats(string $phone): void
    {
        $this->postJson('/presentation-requests', [
            'name' => 'سالم العتيبي',
            'phone' => $phone,
        ])->assertCreated();
    }

    /** @return array<string, array{string}> */
    public static function validPhoneProvider(): array
    {
        return [
            'local with leading zero' => ['0512345678'],
            'international with plus' => ['+966512345678'],
            'international without plus' => ['966512345678'],
        ];
    }

    public function test_a_new_request_is_unread_by_default(): void
    {
        $this->postJson('/presentation-requests', [
            'name' => 'سالم العتيبي',
            'phone' => '0512345678',
        ]);

        $this->assertNull(PresentationRequest::first()->read_at);
    }
}
