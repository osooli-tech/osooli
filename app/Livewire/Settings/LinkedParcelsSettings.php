<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Models\PortalSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * What a parent owner may see, in the portal, of a parcel held under them
 * whose deed is in someone else's name. The parcel itself is always shown;
 * each switch here adds one more kind of detail.
 */
class LinkedParcelsSettings extends Component
{
    /** @var array<string, bool> switch key => on/off */
    public array $switches = [];

    public function boot(): void
    {
        // On every request to the component, so toggling is never reachable without the permission.
        abort_unless(Auth::user()?->can('roles.manage') === true, 403);
    }

    public function mount(): void
    {
        $this->switches = PortalSetting::linkedParcels();
    }

    public function save(): void
    {
        $values = [];
        foreach (array_keys(PortalSetting::DEFAULTS) as $key) {
            $values[$key] = (bool) ($this->switches[$key] ?? false);
        }

        $setting = PortalSetting::query()->first() ?? new PortalSetting;
        $setting->linked_parcels = $values;
        $setting->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'portal_settings.update',
            'target_type' => 'portal_settings',
            'target_id' => $setting->id,
        ]);

        $this->switches = $values;
        $this->dispatch('toast', type: 'success', message: __('settings.linked.saved'));
    }

    public function render(): View
    {
        return view('livewire.settings.linked-parcels-settings', [
            'options' => array_keys(PortalSetting::DEFAULTS),
        ]);
    }
}
