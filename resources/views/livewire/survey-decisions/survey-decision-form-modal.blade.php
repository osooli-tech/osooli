<div>
    @if ($show)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="close"></div>

            {{-- Panel --}}
            <div data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-2xl max-h-[90vh] overflow-y-auto
                        bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-5 outline-none">

                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                    {{ $decision->decisionId ? __('survey_decisions.edit_title') : __('survey_decisions.create_title') }}
                </h2>

                <form wire:submit="save" class="space-y-6">

                    {{-- Decision --}}
                    <section class="space-y-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide
                                  text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.decision_section') }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-form.input name="decision.qrarNo"
                                          :label="__('survey_decisions.qrar_no')"
                                          class="data-tabular" ltr />

                            <x-form.input name="decision.reportNo"
                                          :label="__('survey_decisions.report_no')"
                                          class="data-tabular" ltr />

                            <x-form.input name="decision.folder"
                                          :label="__('survey_decisions.folder')" />

                            <x-form.enum-select name="decision.qrarSource"
                                                :label="__('survey_decisions.qrar_source')"
                                                column="qrar_source" />
                        </div>
                    </section>

                    {{-- Boundary — a second table behind a second permission, so it is
                         only offered to a user who may edit the parcel itself. --}}
                    @if ($canEditBoundary)
                        <section class="space-y-4 pt-5 border-t border-outline-variant dark:border-white/10">
                            <p class="text-[11px] font-semibold uppercase tracking-wide
                                      text-on-surface-variant dark:text-on-primary-container">
                                {{ __('survey_decisions.boundaries') }}
                            </p>

                            @if ($boundary->boundaryId === null)
                                <p class="text-xs rounded-xl px-3 py-2
                                          bg-tertiary/10 text-tertiary dark:bg-tertiary/20 dark:text-white/90">
                                    {{ __('survey_decisions.boundary_missing_hint') }}
                                </p>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-form.input name="boundary.nBorder" :label="__('parcels.n_border')" />
                                <x-form.input name="boundary.nDim"
                                              :label="__('survey_decisions.n_dim')"
                                              class="data-tabular" ltr />

                                <x-form.input name="boundary.sBorder" :label="__('parcels.s_border')" />
                                <x-form.input name="boundary.sDim"
                                              :label="__('survey_decisions.s_dim')"
                                              class="data-tabular" ltr />

                                <x-form.input name="boundary.eBorder" :label="__('parcels.e_border')" />
                                <x-form.input name="boundary.eDim"
                                              :label="__('survey_decisions.e_dim')"
                                              class="data-tabular" ltr />

                                <x-form.input name="boundary.wBorder" :label="__('parcels.w_border')" />
                                <x-form.input name="boundary.wDim"
                                              :label="__('survey_decisions.w_dim')"
                                              class="data-tabular" ltr />

                                <x-form.input name="boundary.measuredArea"
                                              :label="__('survey_decisions.measured_area')"
                                              :hint="__('survey_decisions.measured_area_hint')"
                                              class="data-tabular" ltr />

                                {{-- Three options, not a checkbox: "not checked yet" is a
                                     state of its own and the column stores it as NULL. --}}
                                <x-form.select name="boundary.matchesDeed"
                                               :label="__('survey_decisions.matches_deed')"
                                               :options="[
                                                   'yes' => __('survey_decisions.matches_deed_yes'),
                                                   'no' => __('survey_decisions.matches_deed_no'),
                                               ]"
                                               :placeholder="__('survey_decisions.matches_deed_unknown')" />

                                <x-form.input name="boundary.surveyDate"
                                              :label="__('survey_decisions.survey_date')"
                                              :hint="__('survey_decisions.survey_date_hint')"
                                              class="data-tabular" ltr />

                                <x-form.select name="boundary.engineeringOfficeId"
                                               :label="__('survey_decisions.engineering_office')"
                                               :options="$engineeringOffices" />
                            </div>
                        </section>
                    @endif

                    {{-- Buttons --}}
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="close"
                                class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                                       bg-secondary text-white hover:brightness-110 transition-all
                                       disabled:opacity-60">
                            <span wire:loading wire:target="save"
                                  class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            {{ __('common.save') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif
</div>
