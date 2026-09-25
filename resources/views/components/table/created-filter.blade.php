{{-- The "date added" range above a list; the component uses FiltersByCreatedAt. --}}
<div class="min-w-[160px]">
    <x-form.date-input name="createdFrom" calendar="gregorian" live
                       :label="__('common.created_from')" />
</div>
<div class="min-w-[160px]">
    <x-form.date-input name="createdTo" calendar="gregorian" live
                       :label="__('common.created_to')" />
</div>
