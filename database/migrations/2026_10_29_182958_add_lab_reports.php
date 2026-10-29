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
        // When each lab step happened (client, 2026-10-29: marked by lab staff and admins).
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('collected_at')->nullable()->after('reminder_sent_at');
            $table->timestamp('received_at_lab_at')->nullable()->after('collected_at');
            $table->timestamp('report_ready_at')->nullable()->after('received_at_lab_at');
        });

        // One PDF report per booked item per patient (client, 2026-10-29), on the private disk.
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('report_path')->nullable();
            $table->string('report_name')->nullable();
            $table->timestamp('report_uploaded_at')->nullable();
            $table->foreignId('report_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Starting wording for the new event, which admins are expected to edit.
        $now = now();
        DB::table('notification_templates')->insert([
            [
                'event' => 'report_ready',
                'channel' => 'mail',
                'subject' => 'Your reports for order {order_number} are ready',
                'body' => "Hi {customer_name},\n\nYour test reports for order {order_number} are ready. You can download them from your account.",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'event' => 'report_ready',
                'channel' => 'sms',
                'subject' => null,
                'body' => '{site_name}: your reports for order {order_number} are ready. Download them at {order_url}',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')->where('event', 'report_ready')->delete();

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_uploaded_by');
            $table->dropColumn(['report_path', 'report_name', 'report_uploaded_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['collected_at', 'received_at_lab_at', 'report_ready_at']);
        });
    }
};
