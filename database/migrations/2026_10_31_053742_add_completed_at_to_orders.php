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
        // When the order was completed: all reports in and paid (client, 2026-10-31).
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('report_ready_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
