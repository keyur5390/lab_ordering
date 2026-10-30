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
        // Collectors work in one or more service areas (client, 2026-10-30).
        Schema::create('collector_service_area', function (Blueprint $table) {
            $table->foreignId('service_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_area_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('collector_id')->nullable()->after('service_area_id')->constrained('users')->nullOnDelete();
            $table->timestamp('collector_assigned_at')->nullable()->after('collector_id');

            // A home visit that didn't work out; cleared when the order is rescheduled.
            $table->timestamp('visit_failed_at')->nullable();
            // App\Enums\VisitFailureReason
            $table->string('visit_failure_reason', 30)->nullable();
            $table->string('visit_failure_note')->nullable();

            // Pay-at-collection orders: when the collector took the payment, and how.
            $table->timestamp('payment_collected_at')->nullable();
            // App\Enums\CollectionPaymentMethod
            $table->string('payment_collected_method', 20)->nullable();
        });

        // Starting wording for the new events, which admins are expected to edit.
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
            ['collector_assigned', 'mail', '{collector_name} will collect your samples', "Hi {customer_name},\n\n{collector_name} will visit {address} on {slot} to collect the samples for order {order_number}."],
            ['collector_assigned', 'sms', null, '{site_name}: {collector_name} will collect your samples for order {order_number} on {slot}.'],
            ['admin_visit_failed', 'mail', 'Home visit failed for order {order_number}', "{collector_name} could not complete the visit for order {order_number} ({customer_name}) on {slot}.\n\nReason: {reason}\n\nPlease reschedule or cancel the order."],
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')->whereIn('event', ['collector_assigned', 'admin_visit_failed'])->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collector_id');
            $table->dropColumn([
                'collector_assigned_at', 'visit_failed_at', 'visit_failure_reason', 'visit_failure_note',
                'payment_collected_at', 'payment_collected_method',
            ]);
        });

        Schema::dropIfExists('collector_service_area');
    }
};
