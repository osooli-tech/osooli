<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Enums\ModificationRequestStatus;
use App\Enums\PhotoType;
use App\Models\Owner;
use App\Models\Parcel;
use App\Queries\OwnerParcelQuery;
use App\Services\Parcel\DigitalTwinService;
use App\Support\HijriDate;
use App\Support\ParcelFrontage;
use Illuminate\Support\Collection;

/**
 * The owner portal's headline reading of a portfolio: value, health, what
 * needs attention, and where the value sits. Built only from records we hold
 * — like the digital twin, nothing here is estimated to fill a gap.
 */
class OwnerInsightsService
{
    /** A deed area this far from the surveyed geometry is worth a second look. */
    private const AREA_MISMATCH_RATIO = 0.05;

    /** FIFA's recommended pitch, 105 × 68 m — a size people can picture. */
    private const FOOTBALL_PITCH_SQM = 7140;

    private const TOP_PARCELS = 5;

    /**
     * A district average is only shown when at least this many other owners'
     * priced parcels feed it — fewer, and the "average" could reveal one
     * neighbour's price.
     */
    private const MIN_COMPARABLES = 5;

    public function __construct(private readonly DigitalTwinService $twin) {}

    /** @return array<string, mixed> */
    public function for(Owner $owner): array
    {
        $parcels = (new OwnerParcelQuery($owner))
            ->base()
            ->with([
                'plan.district.city.region',
                'deeds.deedOwners.owner',
                'boundary.engineeringOffice',
                'surveyDecisions',
                'photos.deed',
                'heldDeed',
            ])
            ->get();

        $rows = $parcels->map(fn (Parcel $parcel): array => $this->row($parcel));
        $priceComparison = $this->priceComparison($rows);

        // Parcels held under the owner in another's name count in the headline
        // figures; the analysis below stays on what they hold themselves.
        $linked = app(LinkedParcelsService::class)->totals($owner);
        $portfolio = $this->portfolio($rows);
        $portfolio['parcels'] += $linked['parcels'];
        $portfolio['area'] += $linked['area'];
        $portfolio['football_pitches'] = $portfolio['area'] / self::FOOTBALL_PITCH_SQM;
        if ($linked['value'] !== null) {
            $portfolio['value'] = (float) $portfolio['value'] + $linked['value'];
        }

        return [
            'kpis' => $this->kpis($rows, $priceComparison),
            'portfolio' => $portfolio,
            'health' => $this->health($rows),
            'alerts' => $this->alerts($rows, $owner),
            'byDistrict' => $this->valueBy($rows, 'district'),
            'byAssetType' => $this->valueBy($rows, 'asset_type'),
            'timeline' => $this->timeline($rows),
            'priceComparison' => $priceComparison,
            'frontage' => $this->frontageProfile($rows),
            'topParcels' => $rows->sortByDesc('value')->take(self::TOP_PARCELS)->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Parcel $parcel): array
    {
        $twin = $this->twin->for($parcel);
        $deedArea = $twin['area']['deed'];
        $computed = $twin['area']['computed'];

        return [
            'id' => $parcel->id,
            'parcel_no' => $parcel->parcel_no,
            'district' => $parcel->plan?->district?->name_ar,
            'district_id' => $parcel->plan?->district_id,
            'm_price' => $parcel->m_price === null ? null : (float) $parcel->m_price,
            'city' => $parcel->plan?->district?->city?->name_ar,
            'asset_type' => $parcel->asset_type,
            'geom_json' => $parcel->getAttribute('geom_json'),
            'area' => $deedArea ?? $computed,
            'value' => $twin['valuation']['total'],
            'completeness' => $twin['completeness']['percent'],
            'missing' => $twin['completeness']['missing']->all(),
            'has_deed_scan' => $parcel->photos->contains('photo_type', PhotoType::Deed),
            'area_mismatch' => $deedArea && $computed && abs($deedArea - $computed) / $deedArea > self::AREA_MISMATCH_RATIO,
            'deed_year' => $this->hijriYear($parcel->heldDeed?->deed_date_hijri),
            'frontage' => ParcelFrontage::read($parcel->boundary),
            'matches_deed' => $parcel->boundary?->matches_deed,
            'deed_area' => $deedArea,
            'computed_area' => $computed,
            'deed_age' => HijriDate::ageInYears($parcel->heldDeed?->deed_date_hijri),
            'co_owned' => ($parcel->deeds->firstWhere('id', $parcel->heldDeed?->id)?->deedOwners->count() ?? 0) > 1,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function portfolio(Collection $rows): array
    {
        $area = (float) $rows->sum('area');
        $priced = $rows->whereNotNull('value');

        return [
            'parcels' => $rows->count(),
            'value' => $priced->isEmpty() ? null : (float) $priced->sum('value'),
            'area' => $area,
            'football_pitches' => $area / self::FOOTBALL_PITCH_SQM,
            'districts' => $rows->pluck('district')->filter()->unique()->count(),
            'cities' => $rows->pluck('city')->filter()->unique()->count(),
        ];
    }

    /**
     * Average record completeness, and the fields most often missing across
     * the portfolio — which tells the owner what to ask the team to fill in.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function health(Collection $rows): array
    {
        $missing = $rows->pluck('missing')->flatten()->countBy()->sortDesc()->take(4);

        return [
            'percent' => $rows->isEmpty() ? 0 : (int) round($rows->avg('completeness')),
            'documented' => $rows->where('has_deed_scan', true)->count(),
            'priced' => $rows->whereNotNull('value')->count(),
            'matched' => $rows->where('matches_deed', true)->count(),
            'total' => $rows->count(),
            'top_missing' => $missing->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{key: string, tone: string, icon: string, count: int, parcels: list<string|null>}>
     */
    private function alerts(Collection $rows, Owner $owner): array
    {
        $alerts = [];

        foreach ([
            ['key' => 'area_mismatch', 'tone' => 'warning', 'icon' => 'straighten', 'rows' => $rows->where('area_mismatch', true)],
            ['key' => 'no_deed_scan', 'tone' => 'warning', 'icon' => 'description', 'rows' => $rows->where('has_deed_scan', false)],
            ['key' => 'unpriced', 'tone' => 'info', 'icon' => 'sell', 'rows' => $rows->whereNull('value')],
        ] as $rule) {
            if ($rule['rows']->isNotEmpty()) {
                $alerts[] = [
                    'key' => $rule['key'],
                    'tone' => $rule['tone'],
                    'icon' => $rule['icon'],
                    'count' => $rule['rows']->count(),
                    'parcels' => $rule['rows']->pluck('parcel_no')->take(6)->values()->all(),
                ];
            }
        }

        $pending = $owner->modificationRequests()->where('status', ModificationRequestStatus::Pending->value)->count();
        if ($pending > 0) {
            $alerts[] = ['key' => 'pending_requests', 'tone' => 'info', 'icon' => 'pending_actions', 'count' => $pending, 'parcels' => []];
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{name: string, parcels: int, area: float, value: float, share: float}>
     */
    private function valueBy(Collection $rows, string $key): array
    {
        $total = (float) $rows->sum('value');

        return $rows->groupBy(fn (array $row) => $row[$key] ?? '—')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'parcels' => $group->count(),
                'area' => (float) $group->sum('area'),
                'value' => (float) $group->sum('value'),
                'share' => $total > 0 ? round($group->sum('value') / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Parcels by the Hijri year their current deed was issued — the owner's
     * own acquisition story, read straight from the deed dates.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function timeline(Collection $rows): array
    {
        return $rows->pluck('deed_year')->filter()->countBy()->sortKeys()->all();
    }

    /**
     * The owner's average price per m² in each district beside the average of
     * everyone else's priced parcels there. Districts with too few other
     * parcels are listed without an average rather than left out.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{district: string, mine: float, market: float|null, comparables: int, diff: float|null}>
     */
    private function priceComparison(Collection $rows): array
    {
        $mine = $rows->whereNotNull('m_price')->whereNotNull('district_id')->groupBy('district_id');

        if ($mine->isEmpty()) {
            return [];
        }

        $market = $this->marketAverages($mine->keys(), $rows->pluck('id'));

        return $mine->map(function (Collection $group, int|string $districtId) use ($market): array {
            $own = (float) $group->avg('m_price');
            $count = $market[$districtId]['comparables'] ?? 0;
            $avg = $market[$districtId]['average'] ?? null;

            return [
                'district' => (string) $group->first()['district'],
                'mine' => round($own, 2),
                'market' => $avg === null ? null : round($avg, 2),
                'comparables' => $count,
                'diff' => $avg ? round(($own - $avg) / $avg * 100, 1) : null,
            ];
        })->sortByDesc('mine')->values()->all();
    }

    /**
     * Figures an owner would not work out on their own: how concentrated or
     * spread the portfolio is, how old its deeds are, how its price sits
     * against the market, and how the surveyed land compares with the deeds.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<array{mine: float, market: float|null, diff: float|null}>  $priceComparison
     * @return array<string, float|int|null>
     */
    private function kpis(Collection $rows, array $priceComparison): array
    {
        $count = $rows->count();
        $value = (float) $rows->sum('value');
        $area = (float) $rows->sum('area');

        // Diversification: 1 − Herfindahl index of value shares across districts, as 0–100.
        $shares = $value > 0 ? $rows->groupBy('district')->map(fn (Collection $g) => $g->sum('value') / $value) : collect();
        $hhi = $shares->sum(fn (float $s) => $s ** 2);

        $compared = collect($priceComparison)->whereNotNull('diff');
        $withBoth = $rows->filter(fn (array $r) => $r['deed_area'] && $r['computed_area']);
        $frontages = $rows->pluck('frontage')->filter();

        return [
            'avg_value' => $count > 0 && $value > 0 ? $value / $count : null,
            'avg_price_per_sqm' => $area > 0 && $value > 0 ? $value / $area : null,
            'top_share' => $value > 0 ? round((float) $rows->max('value') / $value * 100, 1) : null,
            'diversification' => $shares->isEmpty() ? null : (int) round((1 - $hhi) * 100),
            'avg_deed_age' => $rows->pluck('deed_age')->filter(fn ($a) => $a !== null)->avg(),
            'market_premium' => $compared->isEmpty() ? null : round((float) $compared->avg('diff'), 1),
            'area_gap' => $withBoth->isEmpty() ? null : round((float) $withBoth->sum(fn (array $r) => $r['computed_area'] - $r['deed_area'])),
            'corner_share' => $frontages->isEmpty() ? null : (int) round($frontages->where('is_corner', true)->count() / $frontages->count() * 100),
            'co_owned' => $rows->where('co_owned', true)->count(),
        ];
    }

    /**
     * Average price per m² of other priced parcels in each district, left as
     * null wherever fewer than MIN_COMPARABLES parcels feed it.
     *
     * @param  Collection<int, mixed>  $districtIds
     * @param  Collection<int, mixed>  $excludeParcelIds  the owner's own parcels
     * @return array<int|string, array{average: float|null, comparables: int}>
     */
    public function marketAverages(Collection $districtIds, Collection $excludeParcelIds): array
    {
        return Parcel::query()
            ->join('plans', 'plans.id', '=', 'parcels.plan_id')
            ->whereIn('plans.district_id', $districtIds)
            ->whereNotIn('parcels.id', $excludeParcelIds)
            ->whereNotNull('parcels.m_price')
            ->groupBy('plans.district_id')
            ->selectRaw('plans.district_id, AVG(parcels.m_price) AS avg_price, COUNT(*) AS comparables')
            ->get()
            ->mapWithKeys(function (Parcel $row): array {
                $count = (int) $row->getAttribute('comparables');

                return [$row->getAttribute('district_id') => [
                    'average' => $count >= self::MIN_COMPARABLES ? round((float) $row->getAttribute('avg_price'), 2) : null,
                    'comparables' => $count,
                ]];
            })
            ->all();
    }

    /**
     * How the portfolio sits on the street: interior vs corner lots, which way
     * the frontages face, the widest street, and lot regularity.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function frontageProfile(Collection $rows): array
    {
        /** @var Collection<int, array{streets: array<string, float|null>, frontage_count: int, frontage_length: float, widest_street: float|null, is_regular: bool|null}> $surveyed */
        $surveyed = $rows->pluck('frontage')->filter();

        $bySide = collect(ParcelFrontage::SIDES)->mapWithKeys(fn (string $side) => [
            $side => $surveyed->filter(fn (array $f) => array_key_exists($side, $f['streets']))->count(),
        ]);

        return [
            'surveyed' => $surveyed->count(),
            'by_count' => [
                'interior' => $surveyed->where('frontage_count', 0)->count(),
                'one' => $surveyed->where('frontage_count', 1)->count(),
                'corner' => $surveyed->where('frontage_count', 2)->count(),
                'three_plus' => $surveyed->filter(fn (array $f) => $f['frontage_count'] >= 3)->count(),
            ],
            'by_side' => $bySide->all(),
            'widest_street' => $surveyed->pluck('widest_street')->filter()->max(),
            'total_frontage' => round((float) $surveyed->sum('frontage_length'), 1),
            'regular' => $surveyed->where('is_regular', true)->count(),
            'matches_deed' => $rows->where('matches_deed', true)->count(),
        ];
    }

    private function hijriYear(?string $date): ?string
    {
        return $date !== null && preg_match('/^(\d{4})/', $date, $m) ? $m[1] : null;
    }
}
