<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Covers the two things that make these documents risky: they are written as
 * raw HTML by an editor, and they are read by anonymous visitors and by the app.
 */
class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    private LegalDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests render real pages, and the layout pulls its assets
        // through Vite. CI does not build them, and the manifest is not what
        // is under test here.
        $this->withoutVite();

        $this->document = LegalDocument::create([
            'key' => 'privacy',
            'title_ar' => 'سياسة الخصوصية',
            'title_en' => 'Privacy Policy',
            'content_ar' => '<h2>مقدمة</h2><p>نص عربي</p>',
            'content_en' => '<h2>Introduction</h2><p>English text</p>',
        ]);
    }

    public function test_hostile_markup_is_stripped_before_it_is_stored(): void
    {
        $this->document->update([
            'content_ar' => '<h2>عنوان</h2>'
                .'<script>alert(1)</script>'
                .'<img src=x onerror=alert(2)>'
                .'<a href="javascript:alert(3)">رابط</a>'
                .'<iframe src="//evil"></iframe>'
                .'<p onclick="steal()">نص</p>',
        ]);

        $stored = $this->document->fresh()->content_ar;

        foreach (['<script', 'onerror', 'javascript:', '<iframe', 'onclick'] as $vector) {
            $this->assertStringNotContainsString($vector, $stored, "{$vector} survived purification");
        }

        // What the editor legitimately produces must still come through.
        $this->assertStringContainsString('<h2>عنوان</h2>', $stored);
        $this->assertStringContainsString('نص', $stored);
    }

    public function test_purification_covers_the_english_body_too(): void
    {
        $this->document->update(['content_en' => '<p>ok</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script', $this->document->fresh()->content_en);
    }

    public function test_the_public_page_is_reachable_without_signing_in(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertSee('سياسة الخصوصية', false);
    }

    public function test_the_public_page_follows_the_session_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/privacy-policy')
            ->assertOk()
            ->assertSee('English text', false);
    }

    public function test_the_api_follows_the_accept_language_header(): void
    {
        $this->getJson('/api/v1/legal/privacy', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.title', 'Privacy Policy');

        $this->getJson('/api/v1/legal/privacy', ['Accept-Language' => 'ar-SA,ar;q=0.9'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.title', 'سياسة الخصوصية');
    }

    public function test_an_untranslated_document_falls_back_to_arabic_and_says_so(): void
    {
        $this->document->update(['title_en' => null, 'content_en' => null]);

        $this->getJson('/api/v1/legal/terms-does-not-exist')->assertStatus(404);

        $this->getJson('/api/v1/legal/privacy', ['Accept-Language' => 'en'])
            ->assertOk()
            // The reader asked for English but is served Arabic — the response
            // reports what it actually carries so the app can label it.
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.title', 'سياسة الخصوصية');
    }

    public function test_an_emptied_editor_does_not_count_as_a_translation(): void
    {
        $this->actingAsAdmin()
            ->post('/settings/legal/privacy', [
                'title_ar' => 'سياسة الخصوصية',
                'content_ar' => '<p>نص عربي</p>',
                'title_en' => '',
                // Quill posts markup, not an empty string, for an empty editor.
                'content_en' => '<p><br></p>',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($this->document->fresh()->content_en);
    }

    public function test_editing_requires_the_roles_manage_permission(): void
    {
        $this->post('/settings/legal/privacy', [
            'title_ar' => 'مخترق',
            'content_ar' => '<p>x</p>',
        ])->assertRedirect('/login');

        $this->assertSame('سياسة الخصوصية', $this->document->fresh()->title_ar);
    }

    private function actingAsAdmin(): static
    {
        $user = User::create([
            'name' => 'مدير',
            'email' => 'admin@legal.test',
            'password' => bcrypt('password'),
            // EnsureUserIsActive rejects the request without this.
            'is_active' => true,
        ]);

        Permission::findOrCreate('roles.manage', 'web');
        $user->givePermissionTo('roles.manage');

        return $this->actingAs($user);
    }
}
