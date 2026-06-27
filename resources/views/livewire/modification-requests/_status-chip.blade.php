@php
    use App\Enums\ModificationRequestStatus;
    $chipClass = match ($status) {
        ModificationRequestStatus::Pending       => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        ModificationRequestStatus::SentToArcgis  => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        ModificationRequestStatus::Applied        => 'bg-secondary/15 text-secondary dark:bg-secondary/25 dark:text-emerald-300',
        ModificationRequestStatus::Rejected       => 'bg-error/10 text-error dark:bg-error/20 dark:text-red-300',
    };
    $chipIcon = match ($status) {
        ModificationRequestStatus::Pending       => 'schedule',
        ModificationRequestStatus::SentToArcgis  => 'send',
        ModificationRequestStatus::Applied        => 'check_circle',
        ModificationRequestStatus::Rejected       => 'cancel',
    };
@endphp
<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $chipClass }}">
    <span class="material-symbols-outlined text-[12px]"
          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 16;">
        {{ $chipIcon }}
    </span>
    {{ __('modification_requests.status.'.$status->value) }}
</span>
