<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Sidebar extends Component
{
    /**
     * The sidebar, in titled groups. Import and export each get a group of
     * their own, and every entry there carries a `hint` naming the file it
     * takes or gives, because the three import screens are easy to confuse
     * by name alone. A group whose entries the user may not see is hidden.
     *
     * @var array<int, array{title: string|null, items: array<int, array{route: string, label: string, icon: string, permission: string|null, hint?: string}>}>
     */
    public array $navGroups = [
        [
            'title' => null,
            'items' => [
                ['route' => 'dashboard', 'label' => 'nav.dashboard', 'icon' => 'grid_view', 'permission' => null],
            ],
        ],
        [
            'title' => 'nav.group_parcels',
            'items' => [
                ['route' => 'parcels.index', 'label' => 'nav.parcels', 'icon' => 'map', 'permission' => 'parcels.view'],
                ['route' => 'parcels.placement', 'label' => 'nav.placement', 'icon' => 'wrong_location', 'permission' => 'parcels.placement'],
                ['route' => 'owners.index', 'label' => 'nav.owners', 'icon' => 'group', 'permission' => 'parcels.view'],
            ],
        ],
        [
            'title' => 'nav.group_documents',
            'items' => [
                ['route' => 'documents.index', 'label' => 'nav.documents', 'icon' => 'folder_open', 'permission' => 'documents.download'],
                ['route' => 'archive.index', 'label' => 'nav.archive', 'icon' => 'inventory_2', 'permission' => 'archive.view'],
            ],
        ],
        [
            'title' => 'nav.group_import',
            'items' => [
                ['route' => 'imports.index', 'label' => 'nav.import_geojson', 'hint' => 'nav.import_geojson_hint', 'icon' => 'upload_file', 'permission' => 'imports.run'],
                ['route' => 'imports.gdb', 'label' => 'nav.import_gdb', 'hint' => 'nav.import_gdb_hint', 'icon' => 'database', 'permission' => 'imports.create'],
                ['route' => 'documents.split', 'label' => 'nav.import_split_pdf', 'hint' => 'nav.import_split_pdf_hint', 'icon' => 'splitscreen', 'permission' => 'documents.split'],
            ],
        ],
        [
            'title' => 'nav.group_export',
            'items' => [
                ['route' => 'exports.index', 'label' => 'nav.export_deeds', 'hint' => 'nav.export_deeds_hint', 'icon' => 'file_export', 'permission' => 'exports.bulk'],
            ],
        ],
        [
            'title' => 'nav.group_map_reference',
            'items' => [
                ['route' => 'map-layers.index', 'label' => 'nav.map_layers', 'icon' => 'stacks', 'permission' => 'map_layers.manage'],
                ['route' => 'reference.index', 'label' => 'nav.reference', 'icon' => 'dataset', 'permission' => 'reference.view'],
            ],
        ],
        [
            'title' => 'nav.group_requests',
            'items' => [
                ['route' => 'modification-requests.index', 'label' => 'nav.modification_requests', 'icon' => 'edit_note', 'permission' => 'modification_requests.view'],
                ['route' => 'presentation-requests.index', 'label' => 'nav.presentation_requests', 'icon' => 'connect_without_contact', 'permission' => 'presentation_requests.view'],
            ],
        ],
        [
            'title' => 'nav.group_admin',
            'items' => [
                ['route' => 'users.index', 'label' => 'nav.users', 'icon' => 'manage_accounts', 'permission' => 'users.view'],
                ['route' => 'audit-logs.index', 'label' => 'nav.audit_logs', 'icon' => 'history', 'permission' => 'audit_logs.view'],
                ['route' => 'settings.index', 'label' => 'nav.settings', 'icon' => 'settings', 'permission' => 'roles.manage'],
            ],
        ],
    ];

    /**
     * The engineering and property services the platform covers. Every item
     * now has a real content page; `soon` only marks the two not yet launched
     * (valuation, investment) with a badge — it does not affect linking.
     *
     * @var array<int, array{label: string, icon: string, route: string|null, soon: bool}>
     */
    public array $serviceItems = [
        ['label' => 'nav.services_survey_request', 'icon' => 'straighten', 'route' => 'services.survey-request', 'soon' => false],
        ['label' => 'nav.services_engineering_design', 'icon' => 'architecture', 'route' => 'services.engineering-design', 'soon' => false],
        ['label' => 'nav.services_solar_energy', 'icon' => 'solar_power', 'route' => 'services.solar-energy', 'soon' => false],
        ['label' => 'nav.services_valuation', 'icon' => 'assessment', 'route' => 'services.valuation', 'soon' => true],
        ['label' => 'nav.services_investment', 'icon' => 'trending_up', 'route' => 'services.investment', 'soon' => true],
        ['label' => 'nav.services_municipal', 'icon' => 'apartment', 'route' => 'services.municipal', 'soon' => false],
    ];

    public function render(): View
    {
        return view('components.sidebar');
    }
}
