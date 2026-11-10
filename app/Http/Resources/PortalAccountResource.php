<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
        ];
    }

    /**
     * The owners the account may act for, in the order they were granted.
     *
     * @return list<array{id: int, name: string, relation: string, can_manage: bool}>
     */
    public static function ownersOf(PortalAccount $account): array
    {
        return $account->usableGrants()->with('owner:id,name')->orderBy('id')->get()
            ->map(fn (PortalAccountOwner $grant): array => [
                'id' => (int) $grant->owner_id,
                'name' => (string) $grant->owner?->name,
                'relation' => $grant->relation->value,
                'can_manage' => $grant->can_manage,
            ])
            ->values()
            ->all();
    }
}
