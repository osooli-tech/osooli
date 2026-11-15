<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PortalPermission;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The account that signed in to the app: the phone, not an owner.
 *
 * @mixin PortalAccount
 */
class PortalAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            // person | company. A company goes by its own name and is always an agent for its owners.
            'kind' => $this->kind ?? PortalAccount::KIND_PERSON,
            'cr_number' => $this->cr_number,
        ];
    }

    /**
     * The owners the account may act for, in the order they were granted,
     * each with what the account may do for them — the same answers the
     * routes give, so the app hides what would only come back 403.
     *
     * @return list<array{id: int, name: string, relation: string, can_manage: bool, permissions: array<string, bool>}>
     */
    public static function ownersOf(PortalAccount $account): array
    {
        return $account->usableGrants()->with('owner:id,name')->orderBy('id')->get()
            ->map(function (PortalAccountOwner $grant) use ($account): array {
                $grant->setRelation('account', $account);

                return [
                    'id' => (int) $grant->owner_id,
                    'name' => (string) $grant->owner?->name,
                    'relation' => $grant->relation->value,
                    'can_manage' => $grant->can_manage,
                    'permissions' => self::permissionsOf($grant),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Mirrors EnsurePortalCanManage: each PortalPermission by allows(),
     * `interest` by can_manage (portal.manage), `edit_profile` by isSelf() (portal.manage:self).
     *
     * @return array<string, bool>
     */
    private static function permissionsOf(PortalAccountOwner $grant): array
    {
        $permissions = [];
        foreach (PortalPermission::cases() as $permission) {
            $permissions[$permission->value] = $grant->allows($permission);
        }

        return $permissions + [
            'interest' => (bool) $grant->can_manage,
            'edit_profile' => $grant->isSelf(),
        ];
    }
}
