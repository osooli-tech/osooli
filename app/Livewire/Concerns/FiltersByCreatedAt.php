<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * The "date added" filter and sort every list page shares.
 *
 * Two dates bound the range (either may be left open) and a click on the
 * column header orders by it. Until that click the list keeps its own order —
 * a parcel list is still easiest to read by parcel number — so the sort only
 * replaces it once someone asks for it.
 */
trait FiltersByCreatedAt
{
    /** Gregorian YYYY-MM-DD, or '' for an open end. */
    public string $createdFrom = '';

    public string $createdTo = '';

    /** '' keeps the page's own order; 'desc' or 'asc' sorts by date added. */
    public string $createdSort = '';

    public function updatingCreatedFrom(): void
    {
        $this->resetCreatedAtPage();
    }

    public function updatingCreatedTo(): void
    {
        $this->resetCreatedAtPage();
    }

    /** Newest first on the first click, then flips with every click after. */
    public function sortByCreated(): void
    {
        $this->createdSort = $this->createdSort === 'desc' ? 'asc' : 'desc';
        $this->resetCreatedAtPage();
    }

    public function clearCreatedAtFilter(): void
    {
        $this->createdFrom = '';
        $this->createdTo = '';
        $this->resetCreatedAtPage();
    }

    public function filteringByCreatedAt(): bool
    {
        return $this->createdFrom !== '' || $this->createdTo !== '';
    }

    /**
     * Narrows the query to the picked range and, when asked, sorts by the
     * date. Applied after the page's own orderBy, which reorder() replaces.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    protected function applyCreatedAt(mixed $query, string $column = 'created_at'): mixed
    {
        $from = $this->createdAtDate($this->createdFrom);
        $to = $this->createdAtDate($this->createdTo);

        $query->when($from, fn ($q) => $q->where($column, '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where($column, '<=', $to->endOfDay()));

        if (in_array($this->createdSort, ['asc', 'desc'], true)) {
            // The key breaks ties, so rows added in the same second do not
            // swap places between pages.
            $key = str_contains($column, '.') ? str($column)->beforeLast('.').'.id' : 'id';

            $query->reorder($column, $this->createdSort)->orderBy($key, $this->createdSort);
        }

        return $query;
    }

    /** The value arrives from the client, so anything not a real date is ignored. */
    private function createdAtDate(string $value): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private function resetCreatedAtPage(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }
}
