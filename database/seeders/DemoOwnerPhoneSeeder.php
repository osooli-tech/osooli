<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Owner;
use Illuminate\Database\Seeder;

/**
 * Assigns sequential demo phone numbers (0511111111, 0511111112, …) to
 * owners that have none, so the mobile app can log in with the fixed
 * testing OTP. Owners that already have a phone are left untouched.
 *
 * Usage: php artisan db:seed --class=DemoOwnerPhoneSeeder
 */
class DemoOwnerPhoneSeeder extends Seeder
{
    public function run(): void
    {
        $next = 511111111;

        $taken = Owner::whereNotNull('phone')->pluck('phone')->all();

        Owner::whereNull('phone')
            ->orderBy('id')
            ->each(function (Owner $owner) use (&$next, $taken): void {
                do {
                    $phone = '0'.$next;
                    $next++;
                } while (in_array($phone, $taken, true));

                $owner->update(['phone' => $phone]);
                $this->command?->info("Owner {$owner->id} ({$owner->name}): {$phone}");
            });
    }
}
