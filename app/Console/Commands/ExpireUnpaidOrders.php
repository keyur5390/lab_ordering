<?php

namespace App\Console\Commands;

use App\Services\PaymentService;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancel unpaid online orders whose payment hold has passed, freeing their slots';

    public function handle(PaymentService $payments): int
    {
        $count = $payments->expireUnpaid();
        $this->info("Cancelled {$count} unpaid ".str('order')->plural($count).'.');

        return self::SUCCESS;
    }
}
