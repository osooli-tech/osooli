<div class="grid grid-cols-2 md:grid-cols-3 gap-4">

    <x-stat-card
        :label="__('dashboard.total_parcels')"
        :value="number_format($totalParcels)"
        icon="terrain"
        color="primary"
    />

    <x-stat-card
        :label="__('dashboard.total_deeds')"
        :value="number_format($totalDeeds)"
        icon="description"
        color="secondary"
    />

    <x-stat-card
        :label="__('dashboard.total_area')"
        :value="$totalArea"
        icon="straighten"
        color="tertiary"
    />

    <x-stat-card
        :label="__('dashboard.avg_area')"
        :value="$avgArea"
        icon="calculate"
        color="primary"
    />

    <x-stat-card
        :label="__('dashboard.max_min_area')"
        :value="$maxArea"
        icon="unfold_more"
        color="secondary"
        :subtext="__('dashboard.min_area_label').': '.$minArea"
    />

    <x-stat-card
        :label="__('dashboard.total_plans')"
        :value="number_format($totalPlans)"
        icon="grid_view"
        color="tertiary"
    />

    <x-stat-card
        :label="__('dashboard.total_owners')"
        :value="number_format($totalOwners)"
        icon="group"
        color="primary"
    />

    <x-stat-card
        :label="__('dashboard.multi_owner_deeds')"
        :value="number_format($multiOwnerDeeds)"
        icon="people"
        color="secondary"
        :subtext="$multiOwnerDeeds > 0 ? __('dashboard.needs_action') : null"
    />

    <x-stat-card
        :label="__('dashboard.pending_requests')"
        :value="number_format($pendingRequests)"
        icon="edit_note"
        color="error"
        :subtext="$pendingRequests > 0 ? __('dashboard.needs_action') : __('dashboard.all_clear')"
    />

    <x-stat-card
        :label="__('dashboard.top_owner')"
        :value="$topOwnerName"
        icon="workspace_premium"
        color="tertiary"
        :subtext="$topOwnerDeedCount > 0 ? number_format($topOwnerDeedCount).' '.__('dashboard.deeds') : null"
    />

</div>
