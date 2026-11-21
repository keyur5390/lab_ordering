<?php

use App\Models\Cart;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Slot capacity already ignores lapsed holds; this tidies the orders themselves.
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('orders:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
// Idle guest carts (App\Models\Cart::prunable).
Schedule::command('model:prune', ['--model' => [Cart::class]])->daily();
