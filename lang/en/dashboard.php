<?php

declare(strict_types=1);

return [
    // Page
    'title' => 'Dashboard',
    'welcome' => 'Welcome, :name',

    // Section headings
    'section_kpi' => 'Key Performance Indicators',
    'section_charts' => 'Distributions & Charts',
    'section_operational' => 'Operational Data',

    // ── Section 1: KPI cards ────────────────────────────────────
    'total_parcels' => 'Total Parcels',
    'total_deeds' => 'Total Deeds',
    'total_area' => 'Total Area',
    'avg_area' => 'Average Parcel Area',
    'max_min_area' => 'Largest / Smallest Parcel',
    'min_area_label' => 'Smallest',
    'total_plans' => 'Total Plans',
    'total_owners' => 'Total Owners',
    'multi_owner_deeds' => 'Multi-Owner Deeds',
    'pending_requests' => 'Pending Modification Requests',
    'updated_deeds' => 'Updated Deeds',
    'non_updated_deeds' => 'Non-Updated Deeds',
    'active_alerts' => 'Active Alerts',
    'top_owner' => 'Top Owner by Holdings',
    'deeds' => 'deeds',
    'area_unit_sqm' => 'm²',
    'area_unit_million' => 'M m²',
    'needs_action' => 'Requires attention',
    'all_clear' => 'No pending requests',

    // ── Section 2: charts ───────────────────────────────────────
    'distribution_by_deed_status' => 'Deed Status',
    'distribution_by_type' => 'Asset Type',
    'distribution_by_land_transaction' => 'Land Transaction',
    'distribution_by_city' => 'Distribution by City',
    'distribution_by_district' => 'Distribution by District',
    'distribution_linked_decision' => 'Linked to Survey Decision',
    'distribution_by_office' => 'Engineering Offices',
    'linked' => 'Linked',
    'not_linked' => 'Not Linked',
    'no_data' => 'No data available',

    // ── Section 3: operational ──────────────────────────────────
    'pending_mod_requests' => 'Pending Modification Requests',
    'all_clear' => 'No pending requests',
    'last_sync' => 'Last Sync',
    'never' => 'Never',
    'sync_status_success' => 'Successful',
    'sync_status_failed' => 'Failed',
    'sync_status_partial' => 'Partial',
    'records_imported' => 'records imported',
    'active_users' => 'Active Users',
    'active_label' => 'currently active',

    // ── Map & recent ────────────────────────────────────────────
    'mapbox_missing' => 'Add MAPBOX_TOKEN to .env to enable the map',
    'parcel_details' => 'Parcel Details',
    'click_parcel' => 'Click a parcel on the map to view details',
    'recent_parcels' => 'Recent Parcels',
    'recent_alerts' => 'Outdated Deed Alerts',
    'no_alerts' => 'No alerts',
    'view_all' => 'View All',

    // ── Map right panel ─────────────────────────────────────────
    'coordinates' => 'Coordinates',
    'corner_coordinates' => 'Corner Coordinates',
    'corner' => 'Corner',
    'related_documents' => 'Related Documents',
    'no_documents' => 'No related documents',
    'view_full_details' => 'View Full Details',
    'map_layers' => 'Layers',
    'satellite_view' => 'Satellite',
    'street_view' => 'Street Map',
    'show_labels' => 'Parcel Numbers',
    'search_map_placeholder' => 'Search by parcel or deed number...',
    'deed_document' => 'Deed Document',
    'view_deed' => 'View Deed',
    'download_deed' => 'Download Deed',
    'deed_not_available' => 'Deed not available',
];
