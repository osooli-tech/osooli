<?php

declare(strict_types=1);

namespace App\Livewire\Reference;

use App\Livewire\Forms\CityForm;
use App\Livewire\Forms\CountryForm;
use App\Livewire\Forms\DistrictForm;
use App\Livewire\Forms\EngineeringOfficeForm;
use App\Livewire\Forms\PlanForm;
use App\Livewire\Forms\RegionForm;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\EngineeringOffice;
use App\Models\Plan;
use App\Models\Region;
use App\Support\Concerns\WritesSafely;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * The reference data the rest of the dashboard depends on, on one screen.
 *
 * Until this existed a parcel whose plan was not already in the database could
 * not be entered at all, and crediting an engineering office meant knowing its
 * numeric id. Six record types share one page because they are one chain —
 * country → region → city → district → plan — plus the offices, and the
 * person adding a plan usually has to add the district in the same sitting.
 */
class ReferenceIndex extends Component
{
    use WithPagination;
    use WritesSafely;

    private const PER_PAGE = 20;

    /** Tabs in child-first order: the work usually starts at the plan. */
    public const TABS = ['plans', 'districts', 'cities', 'regions', 'countries', 'offices'];

    public string $tab = 'plans';

    public string $search = '';

    public bool $showModal = false;

    public bool $editing = false;

    public bool $showDeleteConfirm = false;

    public ?int $deletingId = null;

    /** Dependent rows found when the delete dialog opened; above zero blocks it. */
    public int $deleteBlockedBy = 0;

    public PlanForm $planForm;

    public DistrictForm $districtForm;

    public CityForm $cityForm;

    public RegionForm $regionForm;

    public CountryForm $countryForm;

    public EngineeringOfficeForm $officeForm;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reference.view'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, self::TABS, true), 404);

        $this->tab = $tab;
        $this->search = '';
        $this->resetPage();
        $this->closeModal();
        $this->cancelDelete();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('reference.create'), 403);

        $this->resetForms();
        $this->editing = false;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()?->can('reference.edit'), 403);

        $this->resetForms();

        $record = $this->findRecord($id);

        match (true) {
            $record instanceof Plan => $this->planForm->setPlan($record),
            $record instanceof District => $this->districtForm->setDistrict($record),
            $record instanceof City => $this->cityForm->setCity($record),
            $record instanceof Region => $this->regionForm->setRegion($record),
            $record instanceof Country => $this->countryForm->setCountry($record),
            $record instanceof EngineeringOffice => $this->officeForm->setOffice($record),
            default => throw new RuntimeException("Unknown reference tab [{$this->tab}]."),
        };

        $this->editing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless(
            auth()->user()?->can($this->editing ? 'reference.edit' : 'reference.create'),
            403
        );

        try {
            match ($this->tab) {
                'plans' => $this->editing ? $this->planForm->update() : $this->planForm->store(),
                'districts' => $this->editing ? $this->districtForm->update() : $this->districtForm->store(),
                'cities' => $this->editing ? $this->cityForm->update() : $this->cityForm->store(),
                'regions' => $this->editing ? $this->regionForm->update() : $this->regionForm->store(),
                'countries' => $this->editing ? $this->countryForm->update() : $this->countryForm->store(),
                'offices' => $this->editing ? $this->officeForm->update() : $this->officeForm->store(),
                default => throw new RuntimeException("Unknown reference tab [{$this->tab}]."),
            };
        } catch (RuntimeException $e) {
            // The optimistic lock trips here. Its message is already the
            // Arabic explanation from common.conflict, so show it and leave
            // the form filled — the user's typing is not thrown away.
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $message = $this->editing ? __('common.updated') : __('common.created');

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editing = false;
        $this->resetForms();
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(auth()->user()?->can('reference.delete'), 403);

        $this->deletingId = $id;
        $this->deleteBlockedBy = $this->dependentCount($this->findRecord($id));
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        abort_unless(auth()->user()?->can('reference.delete'), 403);

        if ($this->deletingId === null) {
            return;
        }

        $record = $this->findRecord($this->deletingId);

        /*
         * Counted, not caught. Every parent key here is restrictOnDelete, so
         * Postgres would reject the delete anyway — but as a 500 naming a
         * constraint nobody outside the team can read. Counting first lets the
         * screen name what is in the way and how much of it there is. Counted
         * again here rather than trusting confirmDelete(): rows can arrive
         * while the dialog sits open.
         */
        $blocking = $this->dependentCount($record);

        if ($blocking > 0) {
            $this->deleteBlockedBy = $blocking;
            $this->dispatch('toast', type: 'error', message: $this->blockedMessage($blocking));

            return;
        }

        $type = $this->entity()['audit'];

        $this->writeSafely(
            $type.'.delete',
            $type,
            (int) $record->getKey(),
            fn (): ?bool => $record->delete()
        );

        $this->cancelDelete();
        $this->resetPage();
        $this->dispatch('toast', type: 'success', message: __('common.deleted'));
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingId = null;
        $this->deleteBlockedBy = 0;
    }

    public function blockedMessage(int $count): string
    {
        return __($this->entity()['blockedKey'], ['count' => $count]);
    }

    public function render(): View
    {
        $page = $this->records();

        return view('livewire.reference.reference-index', [
            'paginator' => $page,
            'headers' => $this->headers(),
            'rows' => $this->rows($page),
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    /**
     * Everything that differs between the six tabs but is not a query.
     *
     * @return array{model: class-string<Model>, audit: string, blockedKey: string}
     */
    private function entity(): array
    {
        return match ($this->tab) {
            'plans' => ['model' => Plan::class, 'audit' => 'plan', 'blockedKey' => 'reference.blocked.plans'],
            'districts' => ['model' => District::class, 'audit' => 'district', 'blockedKey' => 'reference.blocked.districts'],
            'cities' => ['model' => City::class, 'audit' => 'city', 'blockedKey' => 'reference.blocked.cities'],
            'regions' => ['model' => Region::class, 'audit' => 'region', 'blockedKey' => 'reference.blocked.regions'],
            'countries' => ['model' => Country::class, 'audit' => 'country', 'blockedKey' => 'reference.blocked.countries'],
            'offices' => ['model' => EngineeringOffice::class, 'audit' => 'engineering_office', 'blockedKey' => 'reference.blocked.offices'],
            default => throw new RuntimeException("Unknown reference tab [{$this->tab}]."),
        };
    }

    private function findRecord(int $id): Model
    {
        $model = $this->entity()['model'];

        return $model::query()->findOrFail($id);
    }

    /**
     * The active tab's page of records, each carrying a `dependents_count`
     * alias so the list can show what a delete would collide with.
     *
     * @return LengthAwarePaginator<Model>
     */
    private function records(): LengthAwarePaginator
    {
        $term = '%'.$this->search.'%';
        $searching = $this->search !== '';

        return match ($this->tab) {
            'plans' => Plan::query()
                ->with('district.city')
                ->withCount(['parcels as dependents_count'])
                ->when($searching, fn ($q) => $q->where('plan_no', 'ilike', $term))
                ->orderBy('plan_no')
                ->paginate(self::PER_PAGE),

            'districts' => District::query()
                ->with('city')
                ->withCount(['plans as dependents_count'])
                ->when($searching, fn ($q) => $this->nameFilter($q, $term))
                ->orderBy('name_ar')
                ->paginate(self::PER_PAGE),

            'cities' => City::query()
                ->with('region')
                ->withCount(['districts as dependents_count'])
                ->when($searching, fn ($q) => $this->nameFilter($q, $term))
                ->orderBy('name_ar')
                ->paginate(self::PER_PAGE),

            'regions' => Region::query()
                ->with('country')
                ->withCount(['cities as dependents_count'])
                ->when($searching, fn ($q) => $this->nameFilter($q, $term))
                ->orderBy('name_ar')
                ->paginate(self::PER_PAGE),

            'countries' => Country::query()
                ->withCount(['regions as dependents_count'])
                ->when($searching, fn ($q) => $this->nameFilter($q, $term))
                ->orderBy('name_ar')
                ->paginate(self::PER_PAGE),

            'offices' => EngineeringOffice::query()
                ->withCount(['parcelBoundaries as dependents_count'])
                ->when($searching, fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('name', 'ilike', $term)
                    ->orWhere('license_no', 'ilike', $term)
                    ->orWhere('phone', 'ilike', $term)))
                ->orderBy('name')
                ->paginate(self::PER_PAGE),

            default => throw new RuntimeException("Unknown reference tab [{$this->tab}]."),
        };
    }

    /**
     * Search across both name columns, grouped so it cannot leak out of an
     * enclosing `when()` and widen the rest of the query.
     */
    private function nameFilter(mixed $query, string $term): mixed
    {
        return $query->where(fn ($inner) => $inner
            ->where('name_ar', 'ilike', $term)
            ->orWhere('name_en', 'ilike', $term));
    }

    /**
     * @return array<int, string>
     */
    private function headers(): array
    {
        return match ($this->tab) {
            'plans' => [__('reference.plan_no'), __('reference.district'), __('reference.city')],
            'districts' => [__('reference.name_ar'), __('reference.name_en'), __('reference.city')],
            'cities' => [__('reference.name_ar'), __('reference.name_en'), __('reference.region')],
            'regions' => [__('reference.name_ar'), __('reference.name_en'), __('reference.country')],
            'countries' => [__('reference.name_ar'), __('reference.name_en'), __('reference.iso_code')],
            'offices' => [
                __('reference.office_name'),
                __('reference.license_no'),
                __('reference.phone'),
                __('reference.email'),
            ],
            default => throw new RuntimeException("Unknown reference tab [{$this->tab}]."),
        };
    }

    /**
     * @param  LengthAwarePaginator<Model>  $page
     * @return array<int, array{id: int, cells: array<int, string>, dependents: int}>
     */
    private function rows(LengthAwarePaginator $page): array
    {
        $rows = [];

        foreach ($page->items() as $record) {
            $rows[] = [
                'id' => (int) $record->getKey(),
                'cells' => $this->cells($record),
                'dependents' => (int) $record->getAttribute('dependents_count'),
            ];
        }

        return $rows;
    }

    /**
     * One record flattened to the strings its row shows, in header order.
     *
     * Flattened here rather than branched in Blade so the six record types
     * share one table markup instead of six near-identical copies.
     *
     * @return array<int, string>
     */
    private function cells(Model $record): array
    {
        $blank = __('reference.unassigned');

        return match (true) {
            $record instanceof Plan => [
                (string) $record->plan_no,
                $this->relatedName($record, 'district', $blank),
                $this->relatedName($record, 'district.city', $blank),
            ],
            $record instanceof District => [
                (string) $record->name_ar,
                (string) ($record->name_en ?? '—'),
                $this->relatedName($record, 'city', $blank),
            ],
            $record instanceof City => [
                (string) $record->name_ar,
                (string) ($record->name_en ?? '—'),
                $this->relatedName($record, 'region', $blank),
            ],
            $record instanceof Region => [
                (string) $record->name_ar,
                (string) ($record->name_en ?? '—'),
                $this->relatedName($record, 'country', $blank),
            ],
            $record instanceof Country => [
                (string) $record->name_ar,
                (string) ($record->name_en ?? '—'),
                (string) ($record->iso_code ?? '—'),
            ],
            $record instanceof EngineeringOffice => [
                (string) $record->name,
                (string) ($record->license_no ?? '—'),
                (string) ($record->phone ?? '—'),
                (string) ($record->email ?? '—'),
            ],
            default => [],
        };
    }

    /**
     * The Arabic name reached by following a relation path, or the fallback
     * when the chain runs out.
     *
     * Walked with getRelationValue() rather than `?->` because a nullable
     * foreign key — plans.district_id is one — legitimately yields no related
     * record, which the relation's own declared type does not admit.
     */
    private function relatedName(Model $record, string $path, string $fallback): string
    {
        $current = $record;

        foreach (explode('.', $path) as $relation) {
            $next = $current->getRelationValue($relation);

            if (! $next instanceof Model) {
                return $fallback;
            }

            $current = $next;
        }

        $name = $current->getAttribute('name_ar');

        return is_string($name) && $name !== '' ? $name : $fallback;
    }

    /**
     * Rows elsewhere that point at this record.
     *
     * Offices are counted too even though their key is nullOnDelete rather
     * than restrictOnDelete: the database would accept that delete and quietly
     * blank the surveying office on every boundary it was credited on, which
     * is worse than refusing.
     */
    private function dependentCount(Model $record): int
    {
        return match (true) {
            $record instanceof Plan => $record->parcels()->count(),
            $record instanceof District => $record->plans()->count(),
            $record instanceof City => $record->districts()->count(),
            $record instanceof Region => $record->cities()->count(),
            $record instanceof Country => $record->regions()->count(),
            $record instanceof EngineeringOffice => $record->parcelBoundaries()->count(),
            default => throw new RuntimeException("Unknown reference tab [{$this->tab}]."),
        };
    }

    /**
     * The parent dropdown for the active tab, as id => Arabic name. Empty for
     * countries and offices, which have no parent.
     *
     * @return array<int, string>
     */
    private function parentOptions(): array
    {
        return match ($this->tab) {
            'plans' => District::query()->with('city')->orderBy('name_ar')->get()
                ->mapWithKeys(fn (District $district): array => [
                    $district->id => $this->qualified($district->name_ar, $this->relatedName($district, 'city', '')),
                ])->all(),

            'districts' => City::query()->with('region')->orderBy('name_ar')->get()
                ->mapWithKeys(fn (City $city): array => [
                    $city->id => $this->qualified($city->name_ar, $this->relatedName($city, 'region', '')),
                ])->all(),

            'cities' => Region::query()->with('country')->orderBy('name_ar')->get()
                ->mapWithKeys(fn (Region $region): array => [
                    $region->id => $this->qualified($region->name_ar, $this->relatedName($region, 'country', '')),
                ])->all(),

            'regions' => Country::query()->orderBy('name_ar')->get()
                ->mapWithKeys(fn (Country $country): array => [
                    $country->id => $this->qualified($country->name_ar, null),
                ])->all(),

            default => [],
        };
    }

    /**
     * "الحي — المدينة". Two districts in different cities routinely share a
     * name, so the parent is part of the label or the picker is a guess.
     */
    private function qualified(mixed $name, mixed $parent): string
    {
        $label = (string) $name;

        return is_string($parent) && $parent !== ''
            ? $label.' — '.$parent
            : $label;
    }

    private function resetForms(): void
    {
        $this->planForm->reset();
        $this->districtForm->reset();
        $this->cityForm->reset();
        $this->regionForm->reset();
        $this->countryForm->reset();
        $this->officeForm->reset();
        $this->resetValidation();
    }
}
