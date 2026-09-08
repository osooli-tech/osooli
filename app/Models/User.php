<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $last_login_ip
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Login looks a user up by exact email match, and Postgres string
     * equality is case-sensitive — an account saved as "Name@x.com" could
     * never sign in by typing "name@x.com", the way almost everyone types
     * an email. Normalising here, once, covers every write path (admin
     * create/edit, seeders, factories) rather than trusting each of them
     * to remember it individually.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::lower(trim($value)),
        );
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * The owners this user is restricted to, if any. An empty relation
     * means unrestricted (sees every owner/parcel) — see App\Support\OwnerScope.
     *
     * @return BelongsToMany<Owner, $this>
     */
    public function scopedOwners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'user_owner_scopes');
    }

    /**
     * Fixed OTP code for this user, if the whitelisted test account is configured
     * for it — never returns a value in production, regardless of config.
     */
    public function fixedTestOtp(): ?string
    {
        $testEmail = config('auth.otp.test_email');
        $testCode = config('auth.otp.test_code');

        if (app()->environment('production') || ! $testEmail || ! $testCode) {
            return null;
        }

        return strcasecmp($this->email, $testEmail) === 0 ? (string) $testCode : null;
    }
}
