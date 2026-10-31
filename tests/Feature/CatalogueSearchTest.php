<?php

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Models\Category;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\User;
use App\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->setCurrency(Currency::INR);

    $this->diabetes = Category::factory()->create(['name' => 'Diabetes', 'slug' => 'diabetes']);
    $this->heart = Category::factory()->create(['name' => 'Heart', 'slug' => 'heart']);

    $this->hba1c = LabTest::factory()->create(['name' => 'HbA1c', 'code' => 'HBA1C', 'price_amount' => 50000, 'preparation' => null]);
    $this->sugar = LabTest::factory()->create(['name' => 'Fasting Blood Sugar', 'code' => 'FBS', 'price_amount' => 15000, 'preparation' => 'Fast for 8 hours.']);
    $this->lipid = LabTest::factory()->create(['name' => 'Lipid Profile', 'code' => 'LIPID', 'price_amount' => 60000, 'preparation' => 'Fast for 12 hours.']);
    $this->hidden = LabTest::factory()->create(['name' => 'Hidden Sugar Test', 'code' => 'HID', 'is_active' => false]);
    $this->hba1c->categories()->attach([$this->diabetes->id, $this->heart->id]);
    $this->sugar->categories()->attach($this->diabetes);
    $this->lipid->categories()->attach($this->heart);

    // ₹1,100 of tests at 10% off = ₹990.
    $this->package = LabPackage::factory()->discounted(DiscountType::Percent, 10)->create(['name' => 'Diabetes Care', 'code' => 'DCARE']);
    $this->package->tests()->attach([$this->hba1c->id, $this->lipid->id]);
    $this->package->categories()->attach($this->diabetes);
});

function names(Assert $page, string $key): array
{
    return collect($page->toArray()['props'][$key])->pluck('name')->all();
}

function catalogue(array $query = [])
{
    return test()->get(route('catalogue.index', $query));
}

it('lists everything published, with the categories in use', function () {
    catalogue()->assertInertia(function (Assert $page) {
        expect(names($page, 'tests'))->toBe(['Fasting Blood Sugar', 'HbA1c', 'Lipid Profile'])
            ->and(names($page, 'packages'))->toBe(['Diabetes Care']);
        $page->where('categories', [['name' => 'Diabetes', 'slug' => 'diabetes'], ['name' => 'Heart', 'slug' => 'heart']])
            ->where('filters.sort', 'name');
    });

    Category::factory()->create(['name' => 'Empty']);
    catalogue()->assertInertia(fn (Assert $page) => $page->has('categories', 2));
});

it('searches names and codes, and packages by the tests inside', function (string $q, array $tests, array $packages) {
    catalogue(['q' => $q])->assertInertia(function (Assert $page) use ($tests, $packages) {
        expect(names($page, 'tests'))->toBe($tests)->and(names($page, 'packages'))->toBe($packages);
    });
})->with([
    'part of a name, any case' => ['sugar', ['Fasting Blood Sugar'], []],
    'a code' => ['fbs', ['Fasting Blood Sugar'], []],
    'a test inside a package' => ['lipid', ['Lipid Profile'], ['Diabetes Care']],
    'wildcards are literal' => ['%', [], []],
    'no match' => ['vitamin', [], []],
]);

it('filters by category, type and fasting', function () {
    catalogue(['category' => 'heart'])->assertInertia(function (Assert $page) {
        expect(names($page, 'tests'))->toBe(['HbA1c', 'Lipid Profile'])->and(names($page, 'packages'))->toBe([]);
        $page->where('filters.category', 'heart');
    });

    catalogue(['type' => 'packages'])->assertInertia(fn (Assert $page) => expect(names($page, 'tests'))->toBe([]));
    catalogue(['type' => 'tests'])->assertInertia(fn (Assert $page) => expect(names($page, 'packages'))->toBe([]));

    // The package has the fasting lipid test, so it's left out too.
    catalogue(['no_fasting' => 1])->assertInertia(function (Assert $page) {
        expect(names($page, 'tests'))->toBe(['HbA1c'])->and(names($page, 'packages'))->toBe([]);
    });

    catalogue(['category' => 'diabetes', 'q' => 'a1c'])->assertInertia(fn (Assert $page) => expect(names($page, 'tests'))->toBe(['HbA1c']));
});

it('sorts by price, including packages\' worked-out prices', function () {
    $cheap = LabPackage::factory()->create(['name' => 'Cheap Pack', 'pricing_mode' => 'fixed', 'price_amount' => 20000]);
    $cheap->tests()->attach([$this->sugar->id, $this->hba1c->id]);

    catalogue(['sort' => 'price_asc'])->assertInertia(function (Assert $page) {
        expect(names($page, 'tests'))->toBe(['Fasting Blood Sugar', 'HbA1c', 'Lipid Profile'])
            ->and(names($page, 'packages'))->toBe(['Cheap Pack', 'Diabetes Care']);
    });
    catalogue(['sort' => 'price_desc'])->assertInertia(function (Assert $page) {
        expect(names($page, 'tests'))->toBe(['Lipid Profile', 'HbA1c', 'Fasting Blood Sugar'])
            ->and(names($page, 'packages'))->toBe(['Diabetes Care', 'Cheap Pack']);
    });
});

it('rejects unknown filter values and ignores unknown categories', function () {
    catalogue(['sort' => 'random'])->assertSessionHasErrors('sort');
    catalogue(['type' => 'kits'])->assertSessionHasErrors('type');
    catalogue(['category' => 'nope'])->assertInertia(fn (Assert $page) => $page->where('filters.category', null)->has('tests', 3));
});

it('shows categories on detail pages', function () {
    $this->get(route('catalogue.tests.show', $this->hba1c))
        ->assertInertia(fn (Assert $page) => $page->where('test.categories.0.slug', 'diabetes')->where('test.categories.1.slug', 'heart'));
    $this->get(route('catalogue.packages.show', $this->package))
        ->assertInertia(fn (Assert $page) => $page->where('package.categories.0.name', 'Diabetes'));
});

describe('admin', function () {
    beforeEach(fn () => $this->admin = User::factory()->admin()->create());

    it('adds, renames and deletes categories, keeping the slug', function () {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Thyroid'])->assertRedirect(route('admin.categories.index'));
        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Heart'])->assertSessionHasErrors('name');

        $thyroid = Category::where('name', 'Thyroid')->sole();
        $this->actingAs($this->admin)->put(route('admin.categories.update', $thyroid), ['name' => 'Thyroid health']);
        expect($thyroid->fresh()->only(['name', 'slug']))->toBe(['name' => 'Thyroid health', 'slug' => 'thyroid']);

        $this->actingAs($this->admin)->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/categories')->where('categories.0.name', 'Diabetes')
                ->where('categories.0.tests_count', 2)->where('categories.0.packages_count', 1));

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $this->heart));
        expect($this->lipid->categories()->count())->toBe(0)->and(LabTest::whereKey($this->lipid->id)->exists())->toBeTrue();
    });

    it('puts tests and packages in categories from their forms', function () {
        $this->actingAs($this->admin)->get(route('admin.tests.edit', $this->sugar))
            ->assertInertia(fn (Assert $page) => $page->has('categories', 2)->where('test.categories.0.slug', 'diabetes'));

        $this->actingAs($this->admin)->put(route('admin.tests.update', $this->sugar), [
            'name' => 'Fasting Blood Sugar', 'code' => 'FBS', 'sample_type' => 'Blood', 'turnaround_hours' => 12, 'price' => '150',
            'is_active' => true, 'category_ids' => [$this->heart->id],
        ])->assertSessionHasNoErrors();
        expect($this->sugar->categories()->pluck('slug')->all())->toBe(['heart']);

        $this->actingAs($this->admin)->put(route('admin.tests.update', $this->sugar), [
            'name' => 'Fasting Blood Sugar', 'code' => 'FBS', 'sample_type' => 'Blood', 'turnaround_hours' => 12, 'price' => '150',
            'category_ids' => [999],
        ])->assertSessionHasErrors('category_ids.0');
    });

    it('is admin-only', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
    });
});
