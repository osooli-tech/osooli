<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone who signs in to the owner portal: one phone number.
 *
 * What it opens is its grants (PortalAccountOwner). A grant is usable when it
 * is not waiting for review and its owner is not archived. Written only
 * through PortalAccessService.
 *
 * @property int $id
 * @property string|null $name
 * @property string $phone
 * @property string $phone_normalized
 * @property string|null $email
 * @property bool $is_active
 * @property int $session_version
 * @property int|null $last_owner_id
 * @property Carbon|null $last_login_at
 */
class PortalAccount extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'is_active', 'session_version', 'last_owner_id', 'last_login_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'session_version' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The same comparable form as owners.phone_normalized.
        static::saving(function (PortalAccount $account): void {
            $account->phone_normalized = Owner::normalisePhone($account->phone);
        });
    }

    /** The active account signing in with this phone, in any accepted format. */
    public static function findByPhone(string $phone): ?self
    {
        $normalised = Owner::normalisePhone($phone);

        return $normalised === '' ? null : self::query()
            ->where('phone_normalized', $normalised)
            ->where('is_active', true)
            ->first();
    }

    /** @return HasMany<PortalAccountOwner, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(PortalAccountOwner::class);
    }

    /**
     * Grants that open their owner: approved, on an owner not archived.
     *
     * @return HasMany<PortalAccountOwner, $this>
     */
    public function usableGrants(): HasMany
    {
        $relation = $this->hasMany(PortalAccountOwner::class);
        $relation->getQuery()->where('needs_review', false)->whereHas('owner');

        return $relation;
    }

    /**
     * The owners this account opens, in the order they were granted.
     *
     * @return BelongsToMany<Owner, $this>
     */
    public function owners(): BelongsToMany
    {
        $relation = $this->belongsToMany(Owner::class, 'portal_account_owners')->withPivot('id', 'relation', 'can_manage');
        $relation->getQuery()->where('portal_account_owners.needs_review', false)->orderBy('portal_account_owners.id');

        return $relation;
    }

    /** @return list<int> */
    public function usableOwnerIds(): array
    {
        return $this->usableGrants()->orderBy('id')->pluck('owner_id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    public function opens(int $ownerId): bool
    {
        return $this->usableGrants()->where('owner_id', $ownerId)->exists();
    }

    /** @return BelongsTo<Owner, $this> */
    public function lastOwner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'last_owner_id');
    }

    /**
     * Whom to act for on sign-in: the owner last worked on, else the
     * account holder themselves, else the oldest grant.
     */
    public function defaultOwner(): ?Owner
    {
        $usable = $this->usableGrants()->with('owner')->orderBy('id')->get();

        $grant = $usable->firstWhere('owner_id', $this->last_owner_id)
            ?? $usable->first(fn (PortalAccountOwner $g): bool => $g->relation === PortalRelation::Self_)
            ?? $usable->first();

        return $grant?->owner;
    }

    /** A name to show: the one staff gave, else the phone. */
    public function label(): string
    {
        return $this->name ?: $this->phone;
    }
}
