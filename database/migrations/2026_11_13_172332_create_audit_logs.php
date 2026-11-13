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
        // What staff did (B-26): every change by an admin, lab staff or collector, and staff sign-ins.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // As they were at the time, in case the account changes or goes.
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('role', 20)->nullable();
            // Route name or auth event, e.g. admin.orders.cancel or auth.login
            $table->string('action', 100)->index();
            // What it was done to, e.g. ORD-000123
            $table->string('subject')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            // Submitted fields, without passwords, tokens or file contents.
            $table->json('input')->nullable();
            // done | rejected | forbidden | error
            $table->string('outcome', 12);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
