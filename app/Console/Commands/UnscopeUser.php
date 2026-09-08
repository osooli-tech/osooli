<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UnscopeUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:unscope-user {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove every owner restriction from a user — they see everything again';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $count = $user->scopedOwners()->count();
        $user->scopedOwners()->detach();

        $this->info("Removed {$count} restriction(s) from {$user->email}. They now see everything.");

        return self::SUCCESS;
    }
}
