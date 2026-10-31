<?php

namespace App\Console\Commands;

use App\Services\OrderNotifier;
use Illuminate\Console\Command;

class SendCollectionReminders extends Command
{
    protected $signature = 'orders:send-reminders';

    protected $description = 'Send collection reminders for slots starting within the hours set in Admin → Settings';

    public function handle(OrderNotifier $notifier): int
    {
        $count = $notifier->sendDueReminders();
        $this->info("Sent {$count} ".str('reminder')->plural($count).'.');

        return self::SUCCESS;
    }
}
