<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Every order email/SMS, sent or failed (B-24).
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\NotificationEvent
            $table->string('event', 40);
            // App\Enums\NotificationChannel
            $table->string('channel', 20);
            $table->string('recipient')->nullable();
            // sent | failed
            $table->string('status', 10);
            $table->text('error')->nullable();
            // What the notification carried, so it can be resent as it was.
            $table->unsignedBigInteger('amount')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
