<?php

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\Order;
use App\Models\Patient;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\ServiceAreaService;
use App\Services\SlotService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $settings = app(Settings::class);
    $settings->setCurrency(Currency::INR);
    $settings->setTimezone('Asia/Kolkata');
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->user = User::factory()->create();
    $this->self = $this->user->patients()->sole();
    $this->self->update(['date_of_birth' => '1990-01-01', 'sex' => 'female']);
    $this->parent = Patient::factory()->for($this->user)->create();

    $this->area = ServiceArea::factory()->scheduled(capacity: 1)->create(['country' => 'IN']);
    app(ServiceAreaService::class)->syncPostcodes($this->area, ['400001']);
    $this->address = Address::factory()->for($this->user)->create(['postcode' => '400001', 'country' => 'IN']);

    $this->cbc = LabTest::factory()->create(['name' => 'CBC', 'price_amount' => 40000]);
    $this->lipid = LabTest::factory()->create(['name' => 'Lipid', 'price_amount' => 60000]);
    $this->package = LabPackage::factory()->discounted(DiscountType::Percent, 10)->create(['name' => 'Heart']);
    $this->package->tests()->attach([$this->cbc->id, $this->lipid->id]);

    $this->cart = Cart::create(['user_id' => $this->user->id]);
    $this->testItem = $this->cart->items()->create(['lab_test_id' => $this->cbc->id]);
    $this->packageItem = $this->cart->items()->create(['lab_package_id' => $this->package->id]);

    $this->slot = '2026-10-30T09:00:00+05:30';
});

function checkout(array $overrides = [])
{
    return test()->actingAs(test()->user)->post(route('checkout.store'), [
        'items' => [
            test()->testItem->id => [test()->self->id, test()->parent->id],
            test()->packageItem->id => [test()->self->id],
        ],
        'address_id' => test()->address->id,
        'slot_starts_at' => test()->slot,
        ...$overrides,
    ]);
}

it('shows checkout with patients, addresses and slots for the chosen address', function () {
    $this->actingAs($this->user)
        ->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout')
            ->has('lines', 2)
            ->has('patients', 2)
            ->where('addressId', $this->address->id)
            ->has('slots', 7)
            ->where('prescriptionRequired', false)
        );
});

it('sends customers with an empty cart back to the cart', function () {
    $this->cart->items()->delete();

    $this->actingAs($this->user)->get(route('checkout.show'))->assertRedirect(route('cart.show'));
});

it('requires a signed-in, verified customer', function () {
    $this->get(route('checkout.show'))->assertRedirect(route('login'));
});

it('places an order: one line per item per patient, snapshots, confirmed, cart emptied', function () {
    checkout()->assertSessionHasNoErrors();

    $order = Order::sole();
    expect($order->number)->toBe(sprintf('ORD-%06d', $order->id))
        ->and($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->service_area_id)->toBe($this->area->id)
        ->and($order->slot_starts_at->toIso8601String())->toBe('2026-10-30T03:30:00+00:00')
        ->and($order->slot_ends_at->diffInMinutes($order->slot_starts_at, true))->toBe(60.0)
        ->and($order->address['postcode'])->toBe('400001')
        ->and($order->currency)->toBe('INR')
        // CBC × 2 patients (80000) + Heart package at 10% off 100000 (90000)
        ->and($order->subtotal_amount)->toBe(170000)
        ->and($order->total_amount)->toBe(170000)
        ->and($order->items()->count())->toBe(3)
        ->and($this->cart->items()->count())->toBe(0);

    $package = $order->items()->where('item_type', 'package')->sole();
    expect($package->test_names)->toBe(['CBC', 'Lipid'])
        ->and($package->patient_name)->toBe($this->self->name)
        ->and($package->price_amount)->toBe(90000);

    // Snapshots survive catalogue and profile changes.
    $this->cbc->update(['name' => 'Renamed', 'price_amount' => 1]);
    $this->parent->delete();
    $line = $order->items()->where('item_type', 'test')->where('patient_name', $this->parent->name)->sole();
    expect($line->name)->toBe('CBC')->and($line->price_amount)->toBe(40000)->and($line->patient_id)->toBeNull();
});

it('redirects to the new order', function () {
    checkout()->assertRedirect(route('orders.show', Order::sole()));
});

it('rejects items without patients, others\' patients and incomplete patients', function (Closure $items) {
    checkout(['items' => $items()])->assertSessionHasErrors('items.'.test()->testItem->id);

    expect(Order::count())->toBe(0);
})->with([
    'no patient' => [fn () => [test()->testItem->id => [], test()->packageItem->id => [test()->self->id]]],
    'someone else\'s patient' => [fn () => [test()->testItem->id => [Patient::factory()->create()->id], test()->packageItem->id => [test()->self->id]]],
    'incomplete patient' => [fn () => [test()->testItem->id => [Patient::factory()->incomplete()->for(test()->user)->create()->id], test()->packageItem->id => [test()->self->id]]],
]);

it('rejects addresses outside the collection area and other customers\' addresses', function () {
    $outside = Address::factory()->for($this->user)->create(['postcode' => '999999', 'country' => 'IN']);
    checkout(['address_id' => $outside->id])->assertSessionHasErrors('address_id');

    checkout(['address_id' => Address::factory()->create()->id])->assertSessionHasErrors('address_id');

    expect(Order::count())->toBe(0);
});

it('rejects slots that cannot be booked', function (string $slot) {
    checkout(['slot_starts_at' => $slot])->assertSessionHasErrors('slot_starts_at');

    expect(Order::count())->toBe(0);
})->with([
    'not a slot time' => ['2026-10-30T09:15:00+05:30'],
    'outside opening hours' => ['2026-10-30T14:00:00+05:30'],
    'beyond the booking window' => ['2026-11-20T09:00:00+05:30'],
    'in the past' => ['2026-10-28T09:00:00+05:30'],
]);

it('does not overbook a slot', function () {
    checkout()->assertSessionHasNoErrors();

    // Capacity is 1 visit per slot; a second customer can't take the same slot.
    $other = User::factory()->create();
    $patient = $other->patients()->sole();
    $patient->update(['date_of_birth' => '1980-01-01', 'sex' => 'male']);
    $address = Address::factory()->for($other)->create(['postcode' => '400001', 'country' => 'IN']);
    $item = Cart::create(['user_id' => $other->id])->items()->create(['lab_test_id' => $this->cbc->id]);

    $this->actingAs($other)->post(route('checkout.store'), [
        'items' => [$item->id => [$patient->id]],
        'address_id' => $address->id,
        'slot_starts_at' => $this->slot,
    ])->assertSessionHasErrors('slot_starts_at');

    expect(Order::count())->toBe(1);
});

it('shows booked slots as full and frees them when cancelled', function () {
    checkout();
    $slot = fn () => collect(app(SlotService::class)->days($this->area))->firstWhere('date', '2026-10-30')['slots'][1];

    expect($slot()['time'])->toBe('09:00')->and($slot()['status'])->toBe('full')->and($slot()['remaining'])->toBe(0);

    Order::sole()->update(['status' => OrderStatus::Cancelled]);
    expect($slot()['status'])->toBe('available');
});

it('requires a prescription when a test needs one, and stores it privately', function () {
    $this->lipid->update(['requires_prescription' => true]);

    $this->actingAs($this->user)->get(route('checkout.show'))
        ->assertInertia(fn (Assert $page) => $page->where('prescriptionRequired', true));
    checkout()->assertSessionHasErrors('prescription');
    checkout(['prescription' => UploadedFile::fake()->create('notes.exe', 10)])->assertSessionHasErrors('prescription');

    checkout(['prescription' => UploadedFile::fake()->create('rx.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();

    $order = Order::sole();
    expect($order->prescription_name)->toBe('rx.pdf');
    Storage::disk('local')->assertExists($order->prescription_path);
});

it('lets the customer and admins download the prescription, but nobody else', function () {
    $this->lipid->update(['requires_prescription' => true]);
    checkout(['prescription' => UploadedFile::fake()->create('rx.pdf', 10, 'application/pdf')]);
    $order = Order::sole();

    $this->actingAs($this->user)->get(route('orders.prescription', $order))->assertOk()->assertDownload('rx.pdf');
    $this->actingAs(User::factory()->admin()->create())->get(route('orders.prescription', $order))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('orders.prescription', $order))->assertForbidden();
});

it('refuses to place orders before a currency is set', function () {
    DB::table('settings')->where('key', 'currency')->delete();
    Cache::forget('settings');

    checkout()->assertSessionHasErrors('order');
});

it('books a patient chosen twice for the same item only once', function () {
    checkout(['items' => [
        test()->testItem->id => [test()->self->id, test()->self->id],
        test()->packageItem->id => [test()->self->id],
    ]])->assertSessionHasNoErrors();

    expect(Order::sole()->items()->count())->toBe(2);
});
