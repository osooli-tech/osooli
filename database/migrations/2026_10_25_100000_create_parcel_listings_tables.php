<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Parcels an owner offers for sale. Other owners see an approved one as a
 * shape, an area and a place, with the phone the seller left — never the
 * price, the deed or the seller's name. Staff approve each listing first and
 * may hide it from chosen owners.
 *
 * The two permissions go to every role that already handles owners' change
 * requests, the same kind of review. Adds only; roles edited by hand keep
 * their edits.
 */
return new class extends Migration
{
    /** @var array<string, string> new permission => the one that brings it */
    private const PERMISSIONS = [
        'listings.view' => 'modification_requests.view',
        'listings.manage' => 'modification_requests.manage',
    ];

    public function up(): void
    {
        Schema::create('parcel_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->string('contact_phone', 20);
            $table->string('status', 20)->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['parcel_id', 'status']);
        });

        Schema::create('parcel_listing_blocks', function (Blueprint $table): void {
            $table->foreignId('parcel_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->primary(['parcel_listing_id', 'owner_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $from) {
            $permission = Permission::findOrCreate($name, 'web');

            if (! Permission::query()->where('name', $from)->where('guard_name', 'web')->exists()) {
                continue;
            }

            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                if ($role->hasPermissionTo($from)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_listing_blocks');
        Schema::dropIfExists('parcel_listings');
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
