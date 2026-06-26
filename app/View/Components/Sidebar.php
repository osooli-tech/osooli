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
            'route' => 'users.index',
            'label' => 'nav.users',
            'icon' => 'manage_accounts',
            'permission' => 'users.view',
        ],
        [
            'route' => 'settings.index',
            'label' => 'nav.settings',
            'icon' => 'settings',
            'permission' => 'roles.manage',
        ],
    ];

    public function render(): View
    {
        return view('components.sidebar');
    }
}
