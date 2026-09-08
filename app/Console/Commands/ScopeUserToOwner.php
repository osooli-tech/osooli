<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Console\Command;

class ScopeUserToOwner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scope-user-to-owner {email} {national_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restrict a dashboard user to only see one owner\'s parcels, deeds, and statistics';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $owner = Owner::where('national_id', $this->argument('national_id'))->first();

        if ($owner === null) {
            $this->error("No owner found with national_id {$this->argument('national_id')}.");

            return self::FAILURE;
        }

        $user->scopedOwners()->syncWithoutDetaching([$owner->id]);

        $this->info("{$user->email} is now restricted to {$owner->name} ({$owner->national_id}).");
        $this->line('Run app:unscope-user to remove every restriction from this user.');

        return self::SUCCESS;
    }
}
