<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccountOwner;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * On the owners page: which phones open this owner in the portal, and adding,
 * limiting or taking one away.
 */
class PortalAccessPanel extends Component
{
    #[Locked]
    public int $ownerId;

    public string $phone = '';

    public string $relation = 'delegate';

    public bool $canManage = true;

    public function mount(int $ownerId): void
    {
        $this->ownerId = $ownerId;
        $this->owner();
    }

    public function addPhone(PortalAccessService $access): void
    {
        $this->authorizeManage();

        $this->validate([
            'phone' => ['required', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
            'relation' => ['required', 'in:self,delegate'],
        ], [], ['phone' => __('portal_accounts.phone')]);

        $access->grant(
            $access->accountForPhone($this->phone),
            $this->owner(),
            PortalRelation::from($this->relation),
            $this->canManage,
            PortalGrantSource::Staff,
            $this->user(),
        );

        $this->reset('phone');
        $this->dispatch('owner-updated');
    }

    public function revoke(int $grantId, PortalAccessService $access): void
    {
        $access->revoke($this->grant($grantId), $this->user());
    }

    public function toggleManage(int $grantId, PortalAccessService $access): void
    {
        $grant = $this->grant($grantId);
        $access->setManage($grant, ! $grant->can_manage, $this->user());
    }

    public function render(): View
    {
        return view('livewire.owners.portal-access-panel', [
            'grants' => PortalAccountOwner::query()->with('account')->where('owner_id', $this->ownerId)->orderBy('id')->get(),
            'mayManage' => $this->user()->can('portal_accounts.manage'),
        ]);
    }

    private function grant(int $grantId): PortalAccountOwner
    {
        $this->authorizeManage();

        return PortalAccountOwner::query()->where('owner_id', $this->ownerId)->findOrFail($grantId);
    }

    /** The owner, refused when outside the signed-in user's scope. */
    private function owner(): Owner
    {
        abort_unless($this->user()->can('portal_accounts.view'), 403);

        $scope = OwnerScope::ownerIds($this->user());
        abort_unless($scope === null || in_array($this->ownerId, $scope, true), 403);

        return Owner::query()->findOrFail($this->ownerId);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->user()->can('portal_accounts.manage'), 403);
        $this->owner();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
