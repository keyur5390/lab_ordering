<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Wording per event and channel, edited by admins (client, 2026-10-29).
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            // App\Enums\NotificationEvent
            $table->string('event', 40);
            // App\Enums\NotificationChannel
            $table->string('channel', 20);
            // Email only.
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event', 'channel']);
        });

        // Customers can opt out of SMS (client, 2026-10-29).
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sms_opt_out')->default(false);
        });

        // Set when the collection reminder is sent; cleared on reschedule.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('payment_expires_at');
        });

        // Starting wording, which admins are expected to edit.
        $now = now();
        DB::table('notification_templates')->insert(array_map(fn (array $row) => [
            'event' => $row[0],
            'channel' => $row[1],
            'subject' => $row[2],
            'body' => $row[3],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['order_confirmed', 'mail', 'Your order {order_number} is confirmed', "Hi {customer_name},\n\nThank you for your order. We'll collect your samples on {slot} at {address}.\n\nTotal: {total}, to be paid at collection."],
            ['order_confirmed', 'sms', null, '{site_name}: order {order_number} confirmed. Collection {slot}. Pay {total} at collection.'],
            ['payment_received', 'mail', 'Payment received for order {order_number}', "Hi {customer_name},\n\nWe've received your payment of {amount} and your order is confirmed. We'll collect your samples on {slot} at {address}."],
            ['payment_received', 'sms', null, '{site_name}: payment of {amount} received. Order {order_number} confirmed for {slot}.'],
            ['refund_issued', 'mail', 'Refund for order {order_number}', "Hi {customer_name},\n\nWe've refunded {amount} for order {order_number}. It goes back to your original payment method; banks can take a few days to show it."],
            ['refund_issued', 'sms', null, '{site_name}: {amount} refunded for order {order_number}.'],
            ['order_cancelled', 'mail', 'Order {order_number} is cancelled', "Hi {customer_name},\n\nYour order {order_number} for {slot} has been cancelled."],
            ['order_cancelled', 'sms', null, '{site_name}: order {order_number} for {slot} has been cancelled.'],
            ['payment_expired', 'mail', 'Order {order_number} was not paid', "Hi {customer_name},\n\nWe didn't receive payment for order {order_number} in time, so it has been cancelled and the collection slot released. You're welcome to book again."],
            ['payment_expired', 'sms', null, '{site_name}: order {order_number} was cancelled because it was not paid in time.'],
            ['order_rescheduled', 'mail', 'New collection time for order {order_number}', "Hi {customer_name},\n\nYour collection for order {order_number} has moved to {slot} at {address}."],
            ['order_rescheduled', 'sms', null, '{site_name}: order {order_number} collection moved to {slot}.'],
            ['collection_reminder', 'mail', 'Reminder: sample collection {slot}', "Hi {customer_name},\n\nThis is a reminder that we'll collect your samples for order {order_number} on {slot} at {address}. Please check any preparation instructions, such as fasting."],
            ['collection_reminder', 'sms', null, '{site_name}: reminder, sample collection for order {order_number} on {slot}.'],
            ['admin_new_order', 'mail', 'New order {order_number}', '{customer_name} placed order {order_number} for {slot} at {address}. Total {total}.'],
            ['admin_order_cancelled', 'mail', 'Order {order_number} cancelled', 'Order {order_number} ({customer_name}) for {slot} was cancelled.'],
            ['admin_refund_failed', 'mail', 'Refund failed for order {order_number}', 'The payment gateway did not accept a refund of {amount} for order {order_number} ({customer_name}). Please refund it from the order page.'],
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sms_opt_out');
        });

        Schema::dropIfExists('notification_templates');
    }
};
