<section class="band band--paper pad" id="clients">
    <div class="inner">
        <div class="shead rv">
            <h2 class="h-section">
                {{ __('landing.clients_title') }}
                <x-landing.mark>{{ __('landing.clients_title_mark') }}</x-landing.mark>
            </h2>
        </div>

        <div class="quotes rv">
            <x-landing.quote
                icon="building"
                :quote="__('landing.quote_1')"
                :role="__('landing.quote_1_role')"
                :org="__('landing.quote_1_org')" />

            <x-landing.quote
                featured
                icon="shield"
                :quote="__('landing.quote_2')"
                :role="__('landing.quote_2_role')"
                :org="__('landing.quote_2_org')" />

            <x-landing.quote
                icon="list"
                :quote="__('landing.quote_3')"
                :role="__('landing.quote_3_role')"
                :org="__('landing.quote_3_org')" />
        </div>
    </div>
</section>
