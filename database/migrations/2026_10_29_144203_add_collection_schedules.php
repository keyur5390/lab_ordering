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
        // Each service area has its own collection schedule (client, 2026-10-29). All values are set by
        // admins (⚙️ PROJECT.md §6); an area without them offers no slots.
        Schema::table('service_areas', function (Blueprint $table) {
            $table->unsignedSmallInteger('slot_minutes')->nullable()->after('polygon');
            $table->unsignedSmallInteger('capacity_per_slot')->nullable()->after('slot_minutes');
            $table->unsignedInteger('lead_time_minutes')->nullable()->after('capacity_per_slot');
            $table->unsignedSmallInteger('booking_window_days')->nullable()->after('lead_time_minutes');
        });

        // Weekly opening hours; a weekday may have several ranges (e.g. 07:00–11:00 and 16:00–19:00).
        Schema::create('service_area_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_area_id')->constrained()->cascadeOnDelete();
            // ISO-8601 weekday: 1 = Monday … 7 = Sunday.
            $table->unsignedTinyInteger('weekday');
            // Local wall-clock times in the site timezone (settings).
            $table->time('opens_at');
            $table->time('closes_at');

            $table->index(['service_area_id', 'weekday']);
        });

        // Holidays and other closures: a whole day, several whole days, or part of one day.
        Schema::create('collection_closures', function (Blueprint $table) {
            $table->id();
            // Null closes every service area (e.g. a public holiday).
            $table->foreignId('service_area_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // Both null for whole days; both set (and starts_on = ends_on) for part of a day.
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_closures');
        Schema::dropIfExists('service_area_hours');

        Schema::table('service_areas', function (Blueprint $table) {
            $table->dropColumn(['slot_minutes', 'capacity_per_slot', 'lead_time_minutes', 'booking_window_days']);
        });
    }
};
