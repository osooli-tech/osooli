{{-- Thirteen compact tiles. The map now leads the page, so these read as a
     secondary strip rather than the headline. --}}
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-6 gap-2.5">

    @if ($this->shows('total_parcels'))
    <x-stat-card
        :label="__('dashboard.total_parcels')"
        :value="number_format($totalParcels)"
        icon="terrain"
        color="primary"
    />
    @endif

    @if ($this->shows('total_deeds'))
    <x-stat-card
        :label="__('dashboard.total_deeds')"
        :value="number_format($totalDeeds)"
        icon="description"
        color="secondary"
    />
    @endif

    @if ($this->shows('total_area'))
    <x-stat-card
        :label="__('dashboard.total_area')"
        :value="$totalArea"
        icon="straighten"
        color="tertiary"
    />
    @endif

    @if ($this->shows('avg_area'))
    <x-stat-card
        :label="__('dashboard.avg_area')"
        :value="$avgArea"
        icon="calculate"
        color="primary"
    />
    @endif

    @if ($this->shows('max_min_area'))
    <x-stat-card
        :label="__('dashboard.max_min_area')"
        :value="$maxArea"
        icon="unfold_more"
        color="secondary"
        :subtext="__('dashboard.min_area_label').': '.$minArea"
    />
    @endif

    @if ($this->shows('total_plans'))
    <x-stat-card
        :label="__('dashboard.total_plans')"
        :value="number_format($totalPlans)"
        icon="grid_view"
        color="tertiary"
    />
    @endif

    @if ($this->shows('total_owners'))
    <x-stat-card
        :label="__('dashboard.total_owners')"
        :value="number_format($totalOwners)"
        icon="group"
        color="primary"
    />
    @endif

    @if ($this->shows('avg_price_per_metre'))
    <x-stat-card
        :label="__('dashboard.avg_price_per_metre')"
        :value="$avgPricePerMetre"
        icon="payments"
        color="secondary"
    />
    @endif

    @if ($this->shows('total_estimated_value'))
    <x-stat-card
        :label="__('dashboard.total_estimated_value')"
        :value="$totalEstimatedValue"
        icon="account_balance_wallet"
        color="tertiary"
    />
    @endif

    @if ($this->shows('multi_owner_deeds'))
    <x-stat-card
        :label="__('dashboard.multi_owner_deeds')"
        :value="number_format($multiOwnerDeeds)"
        icon="people"
        color="secondary"
        :subtext="$multiOwnerDeeds > 0 ? __('dashboard.needs_action') : null"
    />
    @endif

    @if ($this->shows('pending_requests'))
    <x-stat-card
        :label="__('dashboard.pending_requests')"
        :value="number_format($pendingRequests)"
        icon="edit_note"
        color="error"
        :subtext="$pendingRequests > 0 ? __('dashboard.needs_action') : __('dashboard.all_clear')"
    />
    @endif

    @if ($this->shows('top_owner'))
    <x-stat-card
        :label="__('dashboard.top_owner')"
        :value="$topOwnerName"
        icon="workspace_premium"
        color="tertiary"
        :subtext="$topOwnerDeedCount > 0 ? number_format($topOwnerDeedCount).' '.__('dashboard.deeds') : null"
    />
    @endif

    @if ($this->shows('updated_deeds'))
    <x-stat-card
        :label="__('dashboard.updated_deeds')"
        :value="number_format($updatedDeeds)"
        icon="verified"
        color="secondary"
    />
    @endif

    @if ($this->shows('non_updated_deeds'))
    <x-stat-card
        :label="__('dashboard.non_updated_deeds')"
        :value="number_format($nonUpdatedDeeds)"
        icon="report"
        color="error"
        :subtext="$nonUpdatedDeeds > 0 ? __('dashboard.needs_action') : null"
    />
    @endif

    @if ($this->shows('active_alerts'))
    <x-stat-card
        :label="__('dashboard.active_alerts')"
        :value="number_format($activeAlerts)"
        icon="notifications_active"
        color="error"
        :subtext="$activeAlerts > 0 ? __('dashboard.needs_action') : __('dashboard.all_clear')"
    />
    @endif

</div>
