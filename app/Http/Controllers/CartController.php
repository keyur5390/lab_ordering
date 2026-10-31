<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartItemRequest;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function show(Request $request): Response
    {
        $cart = $this->cart->current($request);
        $lines = $cart ? $this->cart->lines($cart) : [];

        return Inertia::render('cart', [
            'lines' => $lines,
            'totalAmount' => $this->cart->total($lines),
        ]);
    }

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $cart = $this->cart->currentOrCreate($request);
        $this->cart->add($cart, $request->itemType(), $request->itemId());

        return back();
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        // Only items in the visitor's own cart; anything else looks like it doesn't exist.
        abort_unless($item->cart_id === $this->cart->current($request)?->id, 404);

        $item->delete();

        return back();
    }

    /** Sends a guest to log in, then back to the cart. Their cart moves to their account on login. */
    public function login(): RedirectResponse
    {
        redirect()->setIntendedUrl(route('cart.show'));

        return to_route('login');
    }
}
