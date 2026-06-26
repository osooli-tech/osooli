<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Owner extends Model
{
    protected $fillable = ['name', 'national_id', 'phone', 'email', 'whatsapp'];

    public function deeds(): BelongsToMany
    {
        return $this->belongsToMany(Deed::class, 'deed_owners')
            ->withPivot('ownership_share', 'source_gdb_id')
            ->withTimestamps();
    }

    public function deedOwners(): HasMany
    {
        return $this->hasMany(DeedOwner::class);
    }

    public function modificationRequests(): HasMany
    {
        return $this->hasMany(ModificationRequest::class, 'requested_by');
    }
}
