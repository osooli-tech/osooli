<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A CMS-managed legal text (privacy policy / terms of use), edited from the
 * dashboard and served to the public web pages and the mobile API.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $content
 */
class LegalDocument extends Model
{
    public const KEYS = ['privacy', 'terms'];

    protected $fillable = ['key', 'title', 'content'];
}
