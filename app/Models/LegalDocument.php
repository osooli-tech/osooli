<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * A CMS-managed legal text (privacy policy / terms of use), edited from the
 * dashboard and served to the public web pages and the mobile API.
 *
 * Both content columns are purified on write rather than on render. The editor
 * strips tags in the browser, but the field it posts is a plain hidden input —
 * a request that skips the editor would otherwise store anything at all, and
 * these documents are read by anonymous visitors and by the app.
 *
 * @property int $id
 * @property string $key
 * @property string $title_ar
 * @property string|null $title_en
 * @property string $content_ar
 * @property string|null $content_en
 */
class LegalDocument extends Model
{
    public const KEYS = ['privacy', 'terms'];

    /** The languages a document can carry; Arabic is the fallback. */
    public const LOCALES = ['ar', 'en'];

    protected $fillable = ['key', 'title_ar', 'title_en', 'content_ar', 'content_en'];

    protected function contentAr(): Attribute
    {
        return Attribute::set(static fn (?string $value): ?string => self::purify($value));
    }

    protected function contentEn(): Attribute
    {
        return Attribute::set(static fn (?string $value): ?string => self::purify($value));
    }

    /** The title for the given locale, falling back to Arabic. */
    public function titleFor(string $locale): string
    {
        return $locale === 'en' && filled($this->title_en)
            ? $this->title_en
            : $this->title_ar;
    }

    /** The body for the given locale, falling back to Arabic. */
    public function contentFor(string $locale): string
    {
        return $locale === 'en' && filled($this->content_en)
            ? $this->content_en
            : $this->content_ar;
    }

    /** True when the request's language is served by a real translation. */
    public function hasTranslation(string $locale): bool
    {
        return $locale !== 'en' || (filled($this->title_en) && filled($this->content_en));
    }

    private static function purify(?string $value): ?string
    {
        return $value === null || $value === ''
            ? $value
            : Purifier::clean($value, 'legal');
    }
}
