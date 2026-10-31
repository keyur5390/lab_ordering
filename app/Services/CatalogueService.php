<?php

namespace App\Services;

use App\Models\Category;
use App\Models\LabPackage;
use App\Models\LabTest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Searching and filtering the public catalogue (client, 2026-10-31): text search on name and code
 * (and, for packages, their tests' names), category, tests or packages, no fasting needed, and a
 * sort. Package prices are worked out in PHP, so price sorting happens after the query.
 */
class CatalogueService
{
    public const SORTS = ['name', 'price_asc', 'price_desc'];

    public const TYPES = ['tests', 'packages'];

    public function __construct(private PackagePricingService $pricing) {}

    /**
     * @param  array{q?: string|null, category?: Category|null, type?: string|null, no_fasting?: bool, sort?: string|null}  $filters
     * @return array{packages: Collection<int, LabPackage>, tests: Collection<int, LabTest>}
     */
    public function search(array $filters): array
    {
        $term = trim((string) ($filters['q'] ?? ''));
        $category = $filters['category'] ?? null;
        $type = $filters['type'] ?? null;
        $noFasting = (bool) ($filters['no_fasting'] ?? false);
        $sort = $filters['sort'] ?? 'name';

        $tests = $type === 'packages' ? new Collection : LabTest::active()->with('categories')
            ->when($term !== '', fn (Builder $query) => $this->matching($query, $term))
            ->when($category, fn (Builder $query) => $query->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id)))
            ->when($noFasting, fn (Builder $query) => $this->withoutPreparation($query))
            ->orderBy('name')
            ->get();

        $packages = $type === 'tests' ? new Collection : LabPackage::active()->with(['tests', 'categories'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $this->matching($q, $term)
                ->orWhereHas('tests', fn (Builder $tests) => $this->matching($tests, $term))))
            ->when($category, fn (Builder $query) => $query->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id)))
            ->when($noFasting, fn (Builder $query) => $query->whereDoesntHave('tests', fn (Builder $tests) => $tests
                ->whereNotNull('preparation')->where('preparation', '!=', '')))
            ->orderBy('name')
            ->get();

        if ($sort !== 'name') {
            $descending = $sort === 'price_desc';
            $tests = $tests->sortBy('price_amount', SORT_NUMERIC, $descending)->values();
            $packages = $packages->sortBy(fn (LabPackage $package) => $this->pricing->price($package), SORT_NUMERIC, $descending)->values();
        }

        return ['packages' => $packages, 'tests' => $tests];
    }

    /**
     * Name or code contains the term, ignoring case. LIKE wildcards in the term are taken literally.
     *
     * @param  Builder<LabTest|LabPackage>  $query
     * @return Builder<LabTest|LabPackage>
     */
    private function matching(Builder $query, string $term): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("lower(name) like ? escape '!'", [$pattern])
            ->orWhereRaw("lower(code) like ? escape '!'", [$pattern]));
    }

    /**
     * @param  Builder<LabTest>  $query
     * @return Builder<LabTest>
     */
    private function withoutPreparation(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('preparation')->orWhere('preparation', ''));
    }
}
