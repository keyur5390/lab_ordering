<?php

namespace App\Services;

use App\Enums\CartItemType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LabPackage;
use App\Models\LabTest;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The shopping cart (PROJECT.md §5 step 1). Customers have one cart; guests have a cart
 * identified by a token in their session, which moves to their account when they log in.
 */
class CartService
{
    public const SESSION_KEY = 'cart_token';

    public function __construct(private PackagePricingService $pricing) {}

    /** The current visitor's cart, or null if they don't have one yet. */
    public function current(Request $request): ?Cart
    {
        if ($user = $request->user()) {
            return Cart::where('user_id', $user->id)->first();
        }

        $token = $request->session()->get(self::SESSION_KEY);

        return $token ? Cart::where('guest_token', $token)->first() : null;
    }

    /** The current visitor's cart, created if needed. */
    public function currentOrCreate(Request $request): Cart
    {
        if ($cart = $this->current($request)) {
            return $cart;
        }

        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $token = Str::random(40);
        $request->session()->put(self::SESSION_KEY, $token);

        return Cart::create(['guest_token' => $token]);
    }

    /** Adds a test or package; adding something already in the cart does nothing. */
    public function add(Cart $cart, CartItemType $type, int $id): CartItem
    {
        $item = $cart->items()->firstOrCreate([$type->column() => $id]);
        $cart->touch();

        return $item;
    }

    /**
     * Moves a guest's cart into the customer's cart when they log in or register.
     * Items already in the customer's cart are not duplicated.
     */
    public function mergeGuestCart(Session $session, User $user): void
    {
        $token = $session->pull(self::SESSION_KEY);
        $guestCart = $token ? Cart::where('guest_token', $token)->with('items')->first() : null;

        if (! $guestCart) {
            return;
        }

        DB::transaction(function () use ($guestCart, $user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            foreach ($guestCart->items as $item) {
                $cart->items()->firstOrCreate([
                    'lab_test_id' => $item->lab_test_id,
                    'lab_package_id' => $item->lab_package_id,
                ]);
            }

            $guestCart->delete();
            $cart->touch();
        });
    }

    /**
     * Counts and ids for the header badge and the "In cart" buttons.
     *
     * @return array{count: int, test_ids: list<int>, package_ids: list<int>}
     */
    public function summary(?Cart $cart): array
    {
        $items = $cart?->items()->get(['lab_test_id', 'lab_package_id']) ?? collect();

        return [
            'count' => $items->count(),
            'test_ids' => $items->pluck('lab_test_id')->filter()->values()->all(),
            'package_ids' => $items->pluck('lab_package_id')->filter()->values()->all(),
        ];
    }

    /**
     * Cart lines with live prices. Items an admin has since unpublished stay in the cart,
     * marked unavailable, and are left out of the total.
     *
     * @return list<array<string, mixed>>
     */
    public function lines(Cart $cart): array
    {
        $cart->loadMissing(['items.test', 'items.package.tests']);

        // Packages in the cart, by test id, so a test can say which package already includes it.
        $packagesByTest = [];
        foreach ($cart->items as $item) {
            if ($item->package?->is_active) {
                foreach ($item->package->tests as $test) {
                    $packagesByTest[$test->id][] = $item->package->name;
                }
            }
        }

        return $cart->items->map(function (CartItem $item) use ($packagesByTest) {
            if ($item->package) {
                return $this->line($item, CartItemType::Package, $item->package, [
                    'price_amount' => $this->pricing->price($item->package),
                    'original_amount' => $this->pricing->testsTotal($item->package),
                    'tests_count' => $item->package->tests->count(),
                    'included_in' => [],
                    'requires_prescription' => $item->package->tests->contains('requires_prescription', true),
                ]);
            }

            /** @var LabTest $test */
            $test = $item->test;

            return $this->line($item, CartItemType::Test, $test, [
                'price_amount' => $test->price_amount,
                'original_amount' => null,
                'tests_count' => 1,
                'included_in' => $packagesByTest[$test->id] ?? [],
                'requires_prescription' => $test->requires_prescription,
            ]);
        })->values()->all();
    }

    /**
     * Total of the available lines, in minor units.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function total(array $lines): int
    {
        return (int) collect($lines)->where('available', true)->sum('price_amount');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function line(CartItem $item, CartItemType $type, LabTest|LabPackage $model, array $extra): array
    {
        return [
            'id' => $item->id,
            'type' => $type->value,
            'item_id' => $model->id,
            'name' => $model->name,
            'slug' => $model->slug,
            'available' => $model->is_active,
            ...$extra,
        ];
    }
}
