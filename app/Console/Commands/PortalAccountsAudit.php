<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Portal\LegacyAccountBackfill;
use Illuminate\Console\Command;

/**
 * Shows, without changing anything, which owner each phone will open once
 * portal accounts are built — and which owners sharing that phone will wait
 * for review. Run it before migrating.
 */
class PortalAccountsAudit extends Command
{
    protected $signature = 'portal:accounts-audit {--shared : Only phones shared by several owners}';

    protected $description = 'List the portal account each owner phone becomes (read-only)';

    public function handle(LegacyAccountBackfill $backfill): int
    {
        $plan = $backfill->plan();

        if ($this->option('shared')) {
            $plan = array_values(array_filter($plan, fn (array $row): bool => $row['pending_owner_ids'] !== []));
        }

        $this->table(
            ['Phone', 'Opens', 'Waits for review'],
            array_map(fn (array $row): array => [
                $row['phone'],
                $row['chosen_owner'].' (#'.$row['chosen_owner_id'].')',
                implode('، ', $row['pending_owners']),
            ], $plan),
        );

        $shared = count(array_filter($plan, fn (array $row): bool => $row['pending_owner_ids'] !== []));
        $this->info(count($plan).' phone(s), '.$shared.' shared by several owners.');

        return self::SUCCESS;
    }
}
