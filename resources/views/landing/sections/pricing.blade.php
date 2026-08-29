<section class="band band--surface pad" id="pricing">
    <div class="inner">
        <div class="shead rv">
            <h2 class="h-section">
                {{ __('landing.pricing_title') }}
                <x-landing.mark>{{ __('landing.pricing_title_mark') }}</x-landing.mark>
            </h2>
            <p class="lede">{{ __('landing.pricing_lede') }}</p>
        </div>

        <div class="tiers rv">
            <x-landing.tier
                :name="__('landing.tier_basic_name')"
                :note="__('landing.tier_basic_note')"
                :price="__('landing.tier_basic_price')"
                :period="__('landing.tier_basic_period')"
                :features="[
                    __('landing.tier_basic_1'),
                    __('landing.tier_basic_2'),
                    __('landing.tier_basic_3'),
                    __('landing.tier_basic_4'),
                ]"
                :cta="__('landing.tier_basic_cta')" />

            <x-landing.tier
                featured
                cta-style="gold"
                :name="__('landing.tier_pro_name')"
                :note="__('landing.tier_pro_note')"
                :price="__('landing.tier_pro_price')"
                :period="__('landing.tier_pro_period')"
                :features="[
                    __('landing.tier_pro_1'),
                    __('landing.tier_pro_2'),
                    __('landing.tier_pro_3'),
                    __('landing.tier_pro_4'),
                    __('landing.tier_pro_5'),
                ]"
                :cta="__('landing.tier_pro_cta')" />

            <x-landing.tier
                :name="__('landing.tier_ent_name')"
                :note="__('landing.tier_ent_note')"
                :price="__('landing.tier_ent_price')"
                :features="[
                    __('landing.tier_ent_1'),
                    __('landing.tier_ent_2'),
                    __('landing.tier_ent_3'),
                    __('landing.tier_ent_4'),
                ]"
                :cta="__('landing.tier_ent_cta')" />
        </div>
    </div>
</section>
