<?php

declare(strict_types=1);

namespace App\Livewire\PortalAccounts;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Staff manage who signs in to the owner portal: the accounts, the owners
 * each opens, and the grants waiting for review.
 *
 * Staff limited to some owners (user_owner_scopes) see only accounts reaching
 * those owners; owners outside their scope appear as a count, and an account
 * reaching outside their scope cannot be renamed, stopped or signed out.
 */
class AccountIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'all';

    public string $search = '';

    public string $newPhone = '';

    public string $newName = '';

    /** @var list<int> */
    public array $newOwnerIds = [];

    public string $newRelation = 'delegate';

    public bool $newCanManage = true;

    public string $ownerSearch = '';

    /** @var array<int, string> account id => name being edited */
    public array $renaming = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(PortalAccessService $access): void
    {
        $this->authorizeManage();

        $this->validate([
            'newPhone' => ['required', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
            'newName' => ['nullable', 'string', 'max:150'],
            'newOwnerIds' => ['required', 'array', 'min:1'],
            'newOwnerIds.*' => ['integer'],
            'newRelation' => ['required', 'in:self,delegate'],
        ], ['newOwnerIds.required' => __('portal_accounts.no_owners'), 'newOwnerIds.min' => __('portal_accounts.no_owners')], [
            'newPhone' => __('portal_accounts.phone'),
            'newName' => __('portal_accounts.name'),
        ]);

        $owners = Owner::query()->whereKey($this->newOwnerIds)->get()->filter(fn (Owner $o): bool => $this->inScope((int) $o->id));
        $account = $access->accountForPhone($this->newPhone, $this->newName !== '' ? $this->newName : null);
        $relation = PortalRelation::from($this->newRelation);

        foreach ($owners as $owner) {
            $access->grant($account, $owner, $relation, $this->newCanManage, PortalGrantSource::Staff, $this->user());
        }

        $this->reset('newPhone', 'newName', 'newOwnerIds', 'ownerSearch');
        $this->dispatch('toast', message: __('portal_accounts.created'), type: 'success');
    }

    public function startRename(int $accountId): void
    {
        $this->renaming[$accountId] = (string) PortalAccount::findOrFail($accountId)->name;
    }

    public function rename(int $accountId, PortalAccessService $access): void
    {
        $account = $this->wholeAccount($accountId);
        $access->rename($account, mb_substr($this->renaming[$accountId] ?? '', 0, 150), $this->user());
        unset($this->renaming[$accountId]);
    }

    public function deactivate(int $accountId, PortalAccessService $access): void
    {
        $access->deactivate($this->wholeAccount($accountId), $this->user());
    }

    public function activate(int $accountId, PortalAccessService $access): void
    {
        $access->activate($this->wholeAccount($accountId), $this->user());
    }

    public function signOutEverywhere(int $accountId, PortalAccessService $access): void
    {
        $access->signOutEverywhere($this->wholeAccount($accountId), $this->user());
    }

    public function approve(int $grantId, PortalAccessService $access): void
    {
        $access->approve($this->grantInScope($grantId), $this->user());
    }

    public function reject(int $grantId, PortalAccessService $access): void
    {
        $access->revoke($this->grantInScope($grantId), $this->user());
    }

    public function render(): View
    {
        abort_unless($this->user()->can('portal_accounts.view'), 403);

        $scope = OwnerScope::ownerIds($this->user());

        $accounts = PortalAccount::query()
            ->with(['grants' => fn ($q) => $q->with('owner:id,name')->orderBy('id')])
            ->when($scope !== null, fn (Builder $q) => $q->whereHas('grants', fn (Builder $g) => $g->whereIn('owner_id', $scope)))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', '%'.$this->search.'%')
                ->orWhere('phone_normalized', 'like', '%'.Owner::normalisePhone($this->search).'%')))
            ->orderByDesc('id')
            ->paginate(20);

        $pending = PortalAccountOwner::query()
            ->with(['account', 'owner:id,name'])
            ->where('needs_review', true)
            ->when($scope !== null, fn (Builder $q) => $q->whereIn('owner_id', $scope))
            ->orderBy('id')
            ->get();

        return view('livewire.portal-accounts.account-index', [
            'accounts' => $accounts,
            'pending' => $pending,
            'scope' => $scope,
            'ownerChoices' => $this->ownerChoices($scope),
            'canManage' => $this->user()->can('portal_accounts.manage'),
        ]);
    }

    /**
     * Owners to pick for a new account: matching the search, plus those
     * already picked so they stay on screen.
     *
     * @param  list<int>|null  $scope
     * @return Collection<int, Owner>
     */
    private function ownerChoices(?array $scope): Collection
    {
        if ($this->ownerSearch === '' && $this->newOwnerIds === []) {
            return collect();
        }

        return Owner::query()
            ->when($scope !== null, fn (Builder $q) => $q->whereKey($scope))
            ->where(fn (Builder $q) => $q
                ->when($this->ownerSearch !== '', fn (Builder $s) => $s->whereLike('name', '%'.$this->ownerSearch.'%'))
                ->orWhereIn('id', $this->newOwnerIds === [] ? [0] : $this->newOwnerIds))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name'])
            ->toBase();
    }

    private function inScope(int $ownerId): bool
    {
        $scope = OwnerScope::ownerIds($this->user());

        return $scope === null || in_array($ownerId, $scope, true);
    }

    /** An account whose every owner is in the user's scope; actions on the whole account need that. */
    private function wholeAccount(int $accountId): PortalAccount
    {
        $this->authorizeManage();
        $account = PortalAccount::query()->findOrFail($accountId);

        foreach ($account->grants()->pluck('owner_id') as $ownerId) {
            abort_unless($this->inScope((int) $ownerId), 403);
        }

        return $account;
    }

    private function grantInScope(int $grantId): PortalAccountOwner
    {
        $this->authorizeManage();
        $grant = PortalAccountOwner::query()->findOrFail($grantId);
        abort_unless($this->inScope((int) $grant->owner_id), 403);

        return $grant;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->user()->can('portal_accounts.manage'), 403);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
