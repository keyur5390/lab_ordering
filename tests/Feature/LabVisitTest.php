<?php

use App\Enums\CollectionType;
use App\Enums\Currency;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\CollectionClosure;
use App\Models\LabTest;
use App\Models\Order;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\ServiceAreaService;
use App\Services\SlotService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $settings = app(Settings::class);
    $settings->setCurrency(Currency::INR);
    $settings->setTimezone('Asia/Kolkata');
    $settings->setCancellationCutoffHours(2);
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->user = User::factory()->create();
    $this->self = $this->user->patients()->sole();
    $this->self->update(['date_of_birth' => '1990-01-01', 'sex' => 'female']);

    // Home collection costs ₹50 here; lab visits are free.
    $this->area = ServiceArea::factory()->scheduled()->create(['country' => 'IN', 'collection_fee_amount' => 5000]);
    app(ServiceAreaService::class)->syncPostcodes($this->area, ['400001']);
    $this->address = Address::factory()->for($this->user)->create(['postcode' => '400001', 'country' => 'IN']);

    // 30-minute slots, 1 visit each, 08:00–12:00.
    $this->branch = Branch::factory()->scheduled(capacity: 1)->create(['name' => 'FC Road Lab', 'directions' => 'First floor']);

    $this->item = Cart::create(['user_id' => $this->user->id])->items()->create(['lab_test_id' => LabTest::factory()->create(['price_amount' => 40000])->id]);
});

function bookVisit(array $overrides = [])
{
    return test()->actingAs(test()->user)->post(route('checkout.store'), [
        'items' => [test()->item->id => [test()->self->id]],
        'collection_type' => 'lab_visit',
        'branch_id' => test()->branch->id,
        'slot_starts_at' => '2026-10-30T08:30:00+05:30',
        'payment_method' => 'pay_at_collection',
        ...$overrides,
    ]);
}

describe('checkout', function () {
    it('offers lab visits only when a branch takes bookings', function () {
        $this->actingAs($this->user)->get(route('checkout.show', ['collection_type' => 'lab_visit']))
            ->assertInertia(fn (Assert $page) => $page->where('collectionType', 'lab_visit')->has('branches', 1)
                ->where('branchId', $this->branch->id)->where('branches.0.directions', 'First floor')
                ->where('slots.1.slots.0.time', '08:00')
                // No collection fee for lab visits.
                ->where('quote.collection_fee', 0)->where('quote.total', 40000));

        $this->branch->update(['is_active' => false]);
        Branch::factory()->create(); // no schedule
        $this->actingAs($this->user)->get(route('checkout.show', ['collection_type' => 'lab_visit']))
            ->assertInertia(fn (Assert $page) => $page->where('collectionType', 'home')->has('branches', 0));
    });

    it('books a lab visit at the branch, without a fee, using the branch\'s slots', function () {
        bookVisit()->assertSessionHasNoErrors();

        $order = Order::sole();
        expect($order->collection_type)->toBe(CollectionType::LabVisit)
            ->and($order->branch_id)->toBe($this->branch->id)
            ->and($order->service_area_id)->toBeNull()
            ->and($order->address_id)->toBeNull()
            ->and($order->address['label'])->toBe('FC Road Lab')
            ->and($order->collection_fee_amount)->toBe(0)
            ->and($order->total_amount)->toBe(40000)
            ->and($order->slot_ends_at->diffInMinutes($order->slot_starts_at, true))->toBe(30.0);

        $slot = collect(app(SlotService::class)->days($this->branch))->firstWhere('date', '2026-10-30')['slots'][1];
        expect($slot['status'])->toBe('full');
        // The area's slot at the same time is untouched.
        expect(collect(app(SlotService::class)->days($this->area))->firstWhere('date', '2026-10-30')['slots'][0]['remaining'])->toBe(2);

        $this->actingAs($this->user)->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('order.collection_type', 'lab_visit')->where('order.branch.name', 'FC Road Lab')
                ->where('order.branch.directions', 'First floor'));
    });

    it('validates the branch and slot', function () {
        bookVisit(['branch_id' => null])->assertSessionHasErrors('branch_id');
        bookVisit(['slot_starts_at' => '2026-10-30T13:00:00+05:30'])->assertSessionHasErrors('slot_starts_at');

        $this->branch->update(['slot_minutes' => null]);
        bookVisit()->assertSessionHasErrors('branch_id');

        $this->branch->update(['slot_minutes' => 30, 'is_active' => false]);
        bookVisit()->assertSessionHasErrors('branch_id');
        expect(Order::count())->toBe(0);
    });

    it('still books home collection with the area fee', function () {
        bookVisit(['collection_type' => 'home', 'branch_id' => null, 'address_id' => $this->address->id, 'slot_starts_at' => '2026-10-30T09:00:00+05:30'])
            ->assertSessionHasNoErrors();

        expect(Order::sole()->only(['collection_type', 'branch_id', 'service_area_id', 'collection_fee_amount']))
            ->toBe(['collection_type' => CollectionType::Home, 'branch_id' => null, 'service_area_id' => $this->area->id, 'collection_fee_amount' => 5000]);
    });

    it('reschedules lab visits within the branch\'s slots', function () {
        bookVisit();
        $order = Order::sole();

        $this->actingAs($this->user)->get(route('orders.reschedule.edit', $order))
            ->assertInertia(fn (Assert $page) => $page->where('slots.1.slots.0.time', '08:00'));
        $this->actingAs($this->user)->put(route('orders.reschedule', $order), ['slot_starts_at' => '2026-10-31T11:30:00+05:30'])
            ->assertSessionHasNoErrors();

        expect($order->fresh()->slot_starts_at->toIso8601String())->toBe('2026-10-31T06:00:00+00:00');
    });

    it('has no collector for lab visits', function () {
        bookVisit();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.show', Order::sole()))
            ->assertInertia(fn (Assert $page) => $page->where('collector.can_assign', false));
    });
});

describe('closures', function () {
    it('closes one branch, one area, or everywhere', function () {
        $slotOn = fn ($schedule) => collect(app(SlotService::class)->days($schedule))->firstWhere('date', '2026-10-30')['slots'][0]['status'];

        $branchOnly = CollectionClosure::factory()->create(['branch_id' => $this->branch->id, 'starts_on' => '2026-10-30', 'ends_on' => '2026-10-30']);
        expect($slotOn($this->branch))->toBe('closed')->and($slotOn($this->area))->toBe('available');

        $branchOnly->delete();
        CollectionClosure::factory()->create(['service_area_id' => $this->area->id, 'starts_on' => '2026-10-30', 'ends_on' => '2026-10-30']);
        expect($slotOn($this->branch))->toBe('available')->and($slotOn($this->area))->toBe('closed');

        CollectionClosure::factory()->create(['starts_on' => '2026-10-30', 'ends_on' => '2026-10-30']);
        expect($slotOn($this->branch))->toBe('closed');
    });

    it('lets admins add a branch closure, but not for a branch and an area at once', function () {
        $admin = User::factory()->admin()->create();
        $closure = ['starts_on' => '2026-11-02', 'whole_day' => true, 'reason' => 'Diwali'];

        $this->actingAs($admin)->post(route('admin.closures.store'), [...$closure, 'branch_id' => $this->branch->id, 'service_area_id' => $this->area->id])
            ->assertSessionHasErrors('branch_id');
        $this->actingAs($admin)->post(route('admin.closures.store'), [...$closure, 'branch_id' => $this->branch->id])->assertSessionHasNoErrors();

        expect(CollectionClosure::sole()->only(['branch_id', 'service_area_id']))->toBe(['branch_id' => $this->branch->id, 'service_area_id' => null]);
        $this->actingAs($admin)->get(route('admin.closures.index'))
            ->assertInertia(fn (Assert $page) => $page->where('closures.0.area', 'FC Road Lab')->has('branches', 1));
    });
});

describe('admin', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    });

    function branchForm(array $overrides = []): array
    {
        return [
            'name' => 'Baner Lab', 'line1' => '12 Baner Road', 'city' => 'Pune', 'postcode' => '411045', 'country' => 'IN',
            'latitude' => '18.559', 'longitude' => '73.786', 'phone' => '+91 20 1234 5678', 'email' => 'Baner@Example.com',
            'directions' => 'Next to the bank', 'is_active' => true, ...$overrides,
        ];
    }

    it('creates a branch with a photo, then its schedule', function () {
        $this->actingAs($this->admin)->post(route('admin.branches.store'), branchForm(['photo' => UploadedFile::fake()->image('front.jpg')]))
            ->assertRedirect(route('admin.branches.schedule.edit', Branch::latest('id')->first()));

        $branch = Branch::latest('id')->first();
        expect($branch->only(['name', 'email', 'latitude']))->toBe(['name' => 'Baner Lab', 'email' => 'baner@example.com', 'latitude' => 18.559]);
        Storage::disk('public')->assertExists($branch->photo_path);

        $this->actingAs($this->admin)->get(route('admin.branches.schedule.edit', $branch))
            ->assertInertia(fn (Assert $page) => $page->component('admin/service-areas/schedule')->where('owner.kind', 'branch')
                ->where('submitUrl', route('admin.branches.schedule.update', $branch))->where('preview', []));

        $this->actingAs($this->admin)->put(route('admin.branches.schedule.update', $branch), [
            'slot_minutes' => 20, 'capacity_per_slot' => 3, 'lead_time_hours' => 1, 'booking_window_days' => 14,
            'hours' => [['weekday' => 1, 'opens_at' => '07:00', 'closes_at' => '11:00']],
        ])->assertSessionHasNoErrors();
        expect($branch->fresh()->hasSchedule())->toBeTrue()->and($branch->hours()->count())->toBe(1);
    });

    it('validates branches', function () {
        $this->actingAs($this->admin)->post(route('admin.branches.store'), branchForm(['latitude' => '', 'country' => 'XX', 'photo' => UploadedFile::fake()->create('menu.pdf', 10)]))
            ->assertSessionHasErrors(['latitude', 'country', 'photo']);
    });

    it('replaces or removes the photo', function () {
        $this->actingAs($this->admin)->post(route('admin.branches.update', $this->branch), [...branchForm(), '_method' => 'put', 'photo' => UploadedFile::fake()->image('a.jpg')]);
        $first = $this->branch->fresh()->photo_path;

        $this->actingAs($this->admin)->post(route('admin.branches.update', $this->branch), [...branchForm(), '_method' => 'put', 'photo' => UploadedFile::fake()->image('b.jpg')]);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($this->admin)->post(route('admin.branches.update', $this->branch), [...branchForm(), '_method' => 'put', 'remove_photo' => true]);
        expect($this->branch->fresh()->photo_path)->toBeNull();
    });

    it('deletes only branches without bookings, and is admin-only', function () {
        $unused = Branch::factory()->create();
        Order::factory()->create(['branch_id' => $this->branch->id, 'collection_type' => CollectionType::LabVisit]);

        $this->actingAs($this->admin)->delete(route('admin.branches.destroy', $this->branch))->assertSessionHasErrors('delete');
        $this->actingAs($this->admin)->delete(route('admin.branches.destroy', $unused))->assertRedirect(route('admin.branches.index'));
        expect(Branch::pluck('id')->all())->toBe([$this->branch->id]);

        $this->actingAs($this->user)->get(route('admin.branches.index'))->assertForbidden();
        $this->actingAs($this->user)->put(route('admin.branches.schedule.update', $this->branch), [])->assertForbidden();
    });
});
