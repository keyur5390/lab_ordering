<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp (B-31): messages are pre-approved Twilio Content Templates; admins enter each event's
     * template SID and which of our placeholders fill its variables {{1}}, {{2}}, …
     */
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            // App\Enums\NotificationEvent (customer events only)
            $table->string('event', 40)->unique();
            $table->string('content_sid', 64);
            // Placeholder names in variable order, e.g. ["customer_name", "order_number"].
            $table->json('variables');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // WhatsApp needs the customer's opt-in.
            $table->boolean('whatsapp_opt_in')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_opt_in');
        });
        Schema::dropIfExists('whatsapp_templates');
    }
};
