<?php

use App\Enums\DiscountType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\User;
use App\Services\CartService;
use Inertia\Testing\AssertableInertia as Assert;

function addToCart(string $type, int $id)
{
    return test()->from(route('catalogue.index'))->post(route('cart.items.store'), ['type' => $type, 'id' => $id]);
}

it('shows an empty cart to guests', function () {
    $this->get(route('cart.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart')
            ->where('lines', [])
            ->where('totalAmount', 0)
            ->where('cart', ['count' => 0, 'test_ids' => [], 'package_ids' => []])
        );

    expect(Cart::count())->toBe(0);
});

it('lets a guest add a test, keeping the cart in their session', function () {
    $test = LabTest::factory()->create();

    addToCart('test', $test->id)->assertRedirect(route('catalogue.index'));

    $cart = Cart::sole();
    expect($cart->user_id)->toBeNull()
        ->and($cart->guest_token)->toBe(session(CartService::SESSION_KEY))
        ->and($cart->items()->sole()->lab_test_id)->toBe($test->id);

    $this->get(route('catalogue.index'))->assertInertia(fn (Assert $page) => $page
        ->where('cart', ['count' => 1, 'test_ids' => [$test->id], 'package_ids' => []])
    );
});

it("adds to a customer's own cart", function () {
    $user = User::factory()->create();
    $package = LabPackage::factory()->create();

    $this->actingAs($user);
    addToCart('package', $package->id);

    expect(Cart::sole()->user_id)->toBe($user->id)
        ->and(CartItem::sole()->lab_package_id)->toBe($package->id);
});

it('does not add the same item twice', function () {
    $test = LabTest::factory()->create();

    addToCart('test', $test->id);
    addToCart('test', $test->id);

    expect(CartItem::count())->toBe(1);
});

it('rejects unpublished, unknown and invalid items', function (Closure $payload, string $field) {
    $this->from(route('catalogue.index'))
        ->post(route('cart.items.store'), $payload())
        ->assertSessionHasErrors($field);

    expect(CartItem::count())->toBe(0);
})->with([
    'unpublished test' => [fn () => ['type' => 'test', 'id' => LabTest::factory()->inactive()->create()->id], 'id'],
    'unpublished package' => [fn () => ['type' => 'package', 'id' => LabPackage::factory()->inactive()->create()->id], 'id'],
    'unknown id' => [fn () => ['type' => 'test', 'id' => 999999], 'id'],
    'bad type' => [fn () => ['type' => 'voucher', 'id' => 1], 'type'],
]);

it('lists cart lines with live prices and a total', function () {
    $cbc = LabTest::factory()->create(['name' => 'CBC', 'price_amount' => 40000]);
    $lipid = LabTest::factory()->create(['name' => 'Lipid', 'price_amount' => 60000]);
    $package = LabPackage::factory()->discounted(DiscountType::Percent, 10)->create(['name' => 'Heart']);
    $package->tests()->attach([$cbc->id, $lipid->id]);

    addToCart('package', $package->id);
    addToCart('test', $cbc->id);
    $cbc->update(['price_amount' => 50000]); // prices are read live, not stored in the cart

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->has('lines', 2)
        ->where('lines.0.type', 'package')
        ->where('lines.0.price_amount', 99000)
        ->where('lines.0.original_amount', 110000)
        ->where('lines.0.tests_count', 2)
        ->where('lines.1.type', 'test')
        ->where('lines.1.price_amount', 50000)
        ->where('lines.1.included_in', ['Heart'])
        ->where('totalAmount', 149000)
    );
});

it('keeps unpublished items in the cart but leaves them out of the total', function () {
    $kept = LabTest::factory()->create(['price_amount' => 10000]);
    $hidden = LabTest::factory()->create(['price_amount' => 25000]);
    addToCart('test', $kept->id);
    addToCart('test', $hidden->id);

    $hidden->update(['is_active' => false]);

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->where('lines.1.available', false)
        ->where('totalAmount', 10000)
    );
});

it('removes an item', function () {
    addToCart('test', LabTest::factory()->create()->id);

    $this->from(route('cart.show'))
        ->delete(route('cart.items.destroy', CartItem::sole()))
        ->assertRedirect(route('cart.show'));

    expect(CartItem::count())->toBe(0);
});

it("does not let anyone remove items from someone else's cart", function () {
    $owner = User::factory()->create();
    $item = Cart::create(['user_id' => $owner->id])->items()->create(['lab_test_id' => LabTest::factory()->create()->id]);

    $this->delete(route('cart.items.destroy', $item))->assertNotFound();
    $this->actingAs(User::factory()->create())->delete(route('cart.items.destroy', $item))->assertNotFound();

    $this->assertModelExists($item);
});

it("moves a guest's cart into their account when they log in, without duplicates", function () {
    $user = User::factory()->create();
    $shared = LabTest::factory()->create();
    $guestOnly = LabPackage::factory()->create();
    Cart::create(['user_id' => $user->id])->items()->create(['lab_test_id' => $shared->id]);

    addToCart('test', $shared->id);
    addToCart('package', $guestOnly->id);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    expect(Cart::count())->toBe(1)
        ->and(Cart::sole()->user_id)->toBe($user->id)
        ->and(Cart::sole()->items()->count())->toBe(2)
        ->and(session(CartService::SESSION_KEY))->toBeNull();
});

it("moves a guest's cart into their new account when they register", function () {
    $test = LabTest::factory()->create();
    addToCart('test', $test->id);

    $this->post(route('register.store'), [
        'name' => 'Asha Patel',
        'email' => 'asha@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $cart = Cart::sole();
    expect($cart->user->email)->toBe('asha@example.com')
        ->and($cart->guest_token)->toBeNull()
        ->and($cart->items()->sole()->lab_test_id)->toBe($test->id);
});

it('sends guests to log in and then back to the cart', function () {
    $this->get(route('cart.login'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('cart.show'));
});

it('removes deleted catalogue items from carts', function () {
    $test = LabTest::factory()->create();
    addToCart('test', $test->id);

    $test->delete();

    expect(CartItem::count())->toBe(0);
});
