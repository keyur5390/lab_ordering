<?php

use App\Enums\Currency;
use App\Enums\UserRole;
use App\Models\Faq;
use App\Models\LabPackage;
use App\Models\User;
use App\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(Settings::class)->setCurrency(Currency::INR);
    $this->admin = User::factory()->admin()->create();
});

function saveHome(array $data = [])
{
    return test()->actingAs(test()->admin)->put(route('admin.pages.home.update'), [
        'hero_title' => 'Tests at home',
        'hero_text' => 'Sample collection from your door.',
        'hero_button' => 'See tests',
        'featured_package_ids' => [],
        ...$data,
    ]);
}

describe('home page', function () {
    it('shows the launch copy until an admin changes it', function () {
        $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('home')
            ->where('hero', ['title' => 'Book lab tests online', 'text' => 'Browse tests, choose home collection or a lab visit, and get your reports online.', 'button' => 'Browse tests'])
            ->where('featured', [])
            ->where('faqs', []));
    });

    it('shows the hero and featured packages the admin chose, in their order, published ones only', function () {
        [$a, $b, $hidden] = [LabPackage::factory()->create(['name' => 'A Pack']), LabPackage::factory()->create(['name' => 'B Pack']), LabPackage::factory()->create()];
        saveHome(['featured_package_ids' => [$b->id, $hidden->id, $a->id], 'hero_text' => ''])->assertRedirect(route('admin.pages.home.edit'));
        $hidden->update(['is_active' => false]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('hero', ['title' => 'Tests at home', 'text' => null, 'button' => 'See tests'])
            ->has('featured', 2)
            ->where('featured.0.name', 'B Pack')
            ->where('featured.1.name', 'A Pack')
            ->has('featured.0.price_amount'));
    });

    it('checks what the admin sends', function () {
        saveHome(['hero_title' => '', 'featured_package_ids' => [999999]])->assertSessionHasErrors(['hero_title', 'featured_package_ids.0']);
        $package = LabPackage::factory()->create();
        saveHome(['featured_package_ids' => [$package->id, $package->id]])->assertSessionHasErrors('featured_package_ids.0');
        $this->actingAs($this->admin)->put(route('admin.pages.home.update'), ['hero_title' => 'x', 'hero_button' => 'y'])->assertSessionHasErrors('featured_package_ids');
    });
});

describe('FAQs', function () {
    it('lets admins add, edit, reorder and delete questions, shown in order on the home and FAQ pages', function () {
        $as = $this->actingAs($this->admin);
        $as->post(route('admin.pages.faqs.store'), ['question' => 'Do I need to fast?', 'answer' => 'Only for **some** tests.'])->assertRedirect(route('admin.pages.faqs.index'));
        $as->post(route('admin.pages.faqs.store'), ['question' => 'When are reports ready?', 'answer' => 'Within a day.']);
        $as->post(route('admin.pages.faqs.store'), ['question' => 'Can I pay later?', 'answer' => 'Yes, at collection.']);
        [$fast, $reports, $pay] = Faq::ordered()->get();

        $as->post(route('admin.pages.faqs.move', $pay), ['direction' => 'up']);
        $as->post(route('admin.pages.faqs.move', $fast), ['direction' => 'up']); // already first: no change
        expect(Faq::ordered()->pluck('question')->all())->toBe(['Do I need to fast?', 'Can I pay later?', 'When are reports ready?']);
        $as->post(route('admin.pages.faqs.move', $fast), ['direction' => 'down']);
        expect(Faq::ordered()->pluck('question')->all())->toBe(['Can I pay later?', 'Do I need to fast?', 'When are reports ready?']);
        $as->post(route('admin.pages.faqs.move', $fast), ['direction' => 'sideways'])->assertSessionHasErrors('direction');

        $as->put(route('admin.pages.faqs.update', $reports), ['question' => 'When are reports ready?', 'answer' => ''])->assertSessionHasErrors('answer');
        $as->put(route('admin.pages.faqs.update', $reports), ['question' => '  Report times?  ', 'answer' => 'Same day.']);
        $as->delete(route('admin.pages.faqs.destroy', $pay));
        // New questions go last.
        $as->post(route('admin.pages.faqs.store'), ['question' => 'Is it safe?', 'answer' => 'Yes.']);
        expect(Faq::ordered()->get()->last()->question)->toBe('Is it safe?');
        Faq::where('question', 'Is it safe?')->delete();

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('faqs', 2)
            ->where('faqs.0.question', 'Do I need to fast?')
            ->where('faqs.0.html', "<p>Only for <strong>some</strong> tests.</p>\n")
            ->where('faqs.1.question', 'Report times?'));
        $this->get(route('faq'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('faq')->has('faqs', 2));
    });
});

describe('pages', function () {
    it('serves about and contact pages alongside the policies', function (string $slug) {
        $this->get(route('pages.show', $slug))->assertOk()->assertInertia(fn (Assert $page) => $page->component('page')->where('page.slug', $slug));
    })->with(['about', 'contact', 'privacy', 'terms']);
});

it('keeps content editing to admins', function () {
    $faq = Faq::create(['question' => 'Q', 'answer' => 'A', 'position' => 1]);

    foreach ([User::factory()->create(['role' => UserRole::LabStaff]), User::factory()->create()] as $user) {
        $this->actingAs($user)->get(route('admin.pages.home.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.pages.home.update'), [])->assertForbidden();
        $this->actingAs($user)->post(route('admin.pages.faqs.store'), ['question' => 'Q', 'answer' => 'A'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.pages.faqs.destroy', $faq))->assertForbidden();
    }
    $this->actingAs($this->admin)->get(route('admin.pages.home.edit'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.pages.faqs.index'))->assertOk();
});
