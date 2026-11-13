<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * B-29: a message for pay-at-collection orders once the payment is recorded; the email carries
     * the invoice. Starting wording, which admins are expected to edit.
     */
    public function up(): void
    {
        $now = now();
        DB::table('notification_templates')->insert([
            [
                'event' => 'payment_collected',
                'channel' => 'mail',
                'subject' => 'Payment received for order {order_number}',
                'body' => "Hi {customer_name},\n\nThank you for your payment of {amount} for order {order_number}. Your invoice is attached.",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'event' => 'payment_collected',
                'channel' => 'sms',
                'subject' => null,
                'body' => '{site_name}: payment of {amount} received for order {order_number}. Thank you.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->where('event', 'payment_collected')->delete();
    }
};
