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
     * The engineering and property services the platform covers.
     *
     * Items with a `route` link to a real content page. `route` is null only
     * for services that have no page yet ("خدمات البلدية") — shown inert
     * rather than as a link that goes nowhere.
     *
     * @var array<int, array{label: string, icon: string, route: string|null, soon: bool}>
     */
    public array $serviceItems = [
        ['label' => 'nav.services_survey_request', 'icon' => 'straighten', 'route' => 'services.survey-request', 'soon' => false],
        ['label' => 'nav.services_engineering_design', 'icon' => 'architecture', 'route' => 'services.engineering-design', 'soon' => false],
        ['label' => 'nav.services_solar_energy', 'icon' => 'solar_power', 'route' => 'services.solar-energy', 'soon' => false],
        ['label' => 'nav.services_valuation', 'icon' => 'assessment', 'route' => 'services.valuation', 'soon' => true],
        ['label' => 'nav.services_investment', 'icon' => 'trending_up', 'route' => 'services.investment', 'soon' => true],
        ['label' => 'nav.services_municipal', 'icon' => 'apartment', 'route' => null, 'soon' => false],
    ];

    public function render(): View
    {
        return view('components.sidebar');
    }
}
