<?php

declare(strict_types=1);

namespace App\Support\Portal;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccountOwner;
use App\Services\Portal\PortalAccessService;
use Illuminate\Support\Facades\DB;

/**
 * Builds portal accounts from the phones owners already have.
 *
 * Before accounts, signing in looked an owner up by phone and took the first
 * row the database returned. A phone shared by several owners therefore
 * opened exactly one of them. This keeps it that way: that one owner gets the
 * phone's account, and the others wait for a member of staff to approve them.
 * No one can open an owner afterwards who could not before.
 *
 * Safe to run again: it only adds what is missing.
 */
class LegacyAccountBackfill
{
    public function __construct(private readonly PortalAccessService $access) {}

    /**
     * What run() would do, one row per phone.
     *
     * @return list<array{phone: string, chosen_owner_id: int, chosen_owner: string, pending_owner_ids: list<int>, pending_owners: list<string>}>
     */
    public function plan(): array
    {
        $phones = Owner::query()
            ->whereNotNull('phone_normalized')
            ->where('phone_normalized', '!=', '')
            ->distinct()
            ->orderBy('phone_normalized')
            ->pluck('phone_normalized');

        $plan = [];

        foreach ($phones as $phone) {
            // The very lookup sign-in used before accounts existed.
            $chosen = Owner::query()->where('phone_normalized', $phone)->first();

            if ($chosen === null) {
                continue;
            }

            $others = Owner::query()
                ->where('phone_normalized', $phone)
                ->whereKeyNot($chosen->id)
                ->orderBy('id')
                ->get(['id', 'name']);

            $plan[] = [
                'phone' => (string) $phone,
                'chosen_owner_id' => (int) $chosen->id,
                'chosen_owner' => (string) $chosen->name,
                'pending_owner_ids' => $others->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                'pending_owners' => $others->pluck('name')->map(fn ($n): string => (string) $n)->all(),
            ];
        }

        return $plan;
    }

    /** @return array{accounts: int, grants: int, pending: int, tokens_linked: int, tokens_dropped: int} */
    public function run(): array
    {
        $result = ['accounts' => 0, 'grants' => 0, 'pending' => 0, 'tokens_linked' => 0, 'tokens_dropped' => 0];

        DB::transaction(function () use (&$result): void {
            foreach ($this->plan() as $row) {
                $chosen = Owner::query()->findOrFail($row['chosen_owner_id']);
                $shared = $row['pending_owner_ids'] !== [];
                $existed = DB::table('portal_accounts')->where('phone_normalized', $row['phone'])->exists();

                $account = $this->access->accountForPhone((string) $chosen->phone, $shared ? null : (string) $chosen->name);
                $result['accounts'] += $existed ? 0 : 1;

                $grant = $this->access->grant($account, $chosen, PortalRelation::Self_, true, PortalGrantSource::Migration, null);
                $result['grants'] += $grant->wasRecentlyCreated ? 1 : 0;

                foreach ($row['pending_owner_ids'] as $ownerId) {
                    $pending = $this->access->grant($account, Owner::query()->findOrFail($ownerId), PortalRelation::Self_, true, PortalGrantSource::Migration, null, needsReview: true);
                    $result['pending'] += $pending->needs_review ? 1 : 0;
                }
            }

            $this->linkTokens($result);
        });

        return $result;
    }

    /**
     * Each app token now names the account it was issued to: the owner's own
     * account. A token whose owner has no account can no longer be honoured.
     *
     * @param  array{accounts: int, grants: int, pending: int, tokens_linked: int, tokens_dropped: int}  $result
     */
    private function linkTokens(array &$result): void
    {
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', (new Owner)->getMorphClass())
            ->whereNull('portal_account_id')
            ->get(['id', 'tokenable_id']);

        foreach ($tokens as $token) {
            $accountId = PortalAccountOwner::query()
                ->where('owner_id', $token->tokenable_id)
                ->where('relation', PortalRelation::Self_->value)
                ->where('needs_review', false)
                ->value('portal_account_id');

            if ($accountId === null) {
                DB::table('personal_access_tokens')->where('id', $token->id)->delete();
                $result['tokens_dropped']++;

                continue;
            }

            DB::table('personal_access_tokens')->where('id', $token->id)->update(['portal_account_id' => $accountId]);
            $result['tokens_linked']++;
        }
    }
}
