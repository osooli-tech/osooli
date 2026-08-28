<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Sidebar extends Component
{
    /** @var array<int, array{route: string, label: string, icon: string, permission: string|null}> */
    public array $navItems = [
        [
            'route' => 'dashboard',
            'label' => 'nav.dashboard',
            'icon' => 'grid_view',
            'permission' => null,
        ],
        [
            'route' => 'parcels.index',
            'label' => 'nav.parcels',
            'icon' => 'map',
            'permission' => 'parcels.view',
        ],
        [
            'route' => 'owners.index',
            'label' => 'nav.owners',
            'icon' => 'group',
            'permission' => 'parcels.view',
        ],
        [
            'route' => 'survey-decisions.index',
            'label' => 'nav.survey_decisions',
            'icon' => 'fact_check',
            'permission' => 'parcels.view',
        ],
        [
            'route' => 'documents.index',
            'label' => 'nav.documents',
            'icon' => 'folder_open',
            'permission' => 'documents.download',
        ],
        [
            'route' => 'modification-requests.index',
            'label' => 'nav.modification_requests',
            'icon' => 'edit_note',
            'permission' => 'modification_requests.view',
        ],
        [
            'route' => 'presentation-requests.index',
            'label' => 'nav.presentation_requests',
            'icon' => 'connect_without_contact',
            'permission' => 'presentation_requests.view',
        ],
        [
            'route' => 'users.index',
            'label' => 'nav.users',
            'icon' => 'manage_accounts',
            'permission' => 'users.view',
        ],
        [
            'route' => 'audit-logs.index',
            'label' => 'nav.audit_logs',
            'icon' => 'history',
            'permission' => 'audit_logs.view',
        ],
        [
            'route' => 'settings.index',
            'label' => 'nav.settings',
            'icon' => 'settings',
            'permission' => 'roles.manage',
        ],
        [
            'route' => 'profile.index',
            'label' => 'nav.profile',
            'icon' => 'account_circle',
            'permission' => null,
        ],
    ];

    /**
     * The engineering and property services the platform is planned to cover.
     *
     * Listed but not linked: none of them has a screen or a data source yet.
     * They are here so the shape of the finished product is visible — showing
     * them as inert rather than as links that go nowhere is the honest form.
     *
     * @var array<int, array{label: string, icon: string}>
     */
    public array $serviceItems = [
        ['label' => 'nav.services_survey_request', 'icon' => 'straighten'],
        ['label' => 'nav.services_deeds', 'icon' => 'description'],
        ['label' => 'nav.services_survey_decisions', 'icon' => 'fact_check'],
        ['label' => 'nav.services_engineering_design', 'icon' => 'architecture'],
        ['label' => 'nav.services_municipal', 'icon' => 'apartment'],
        ['label' => 'nav.services_energy', 'icon' => 'bolt'],
        ['label' => 'nav.services_gis', 'icon' => 'public'],
        ['label' => 'nav.services_investment', 'icon' => 'trending_up'],
        ['label' => 'nav.services_legal', 'icon' => 'gavel'],
        ['label' => 'nav.services_other', 'icon' => 'more_horiz'],
        ['label' => 'nav.services_marketplace', 'icon' => 'storefront'],
    ];

    public function render(): View
    {
        return view('components.sidebar');
    }
}
