<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A user with no row here sees everything, exactly as today — this is
     * purely additive and opt-in. A user with one or more rows is scoped to
     * only the parcels/deeds/statistics belonging to those owners, checked
     * explicitly at every read point (most of this app's listing and
     * dashboard queries are raw SQL, not Eloquent, so a global model scope
     * would silently miss most of them).
     */
    public function up(): void
    {
        Schema::create('user_owner_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'owner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_owner_scopes');
    }
};
