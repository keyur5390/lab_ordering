<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Login;

/**
 * When a guest logs in (or registers, which logs them in), their guest cart moves to their account.
 */
class MergeGuestCart
{
    public function __construct(private CartService $cart) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User && request()->hasSession()) {
            $this->cart->mergeGuestCart(request()->session(), $event->user);
        }
    }
}
