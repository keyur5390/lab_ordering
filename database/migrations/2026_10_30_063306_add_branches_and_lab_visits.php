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
        // Lab branches for lab visits (client, 2026-10-30): address + pin, contact, directions, photo,
        // and their own slot schedule like service areas. Every branch takes every test.
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postcode', 20);
            $table->char('country', 2);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('directions')->nullable();
            // Public disk: branch photos are not personal data.
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('slot_minutes')->nullable();
            $table->unsignedSmallInteger('capacity_per_slot')->nullable();
            $table->unsignedInteger('lead_time_minutes')->nullable();
            $table->unsignedSmallInteger('booking_window_days')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // ISO-8601: 1 = Monday … 7 = Sunday
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->index(['branch_id', 'weekday']);
        });

        // A closure for one branch; with neither area nor branch it applies everywhere.
        Schema::table('collection_closures', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('service_area_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            // App\Enums\CollectionType: home | lab_visit
            $table->string('collection_type', 20)->default('home')->after('payment_expires_at');
            $table->foreignId('branch_id')->nullable()->after('service_area_id')->constrained()->nullOnDelete();
            $table->index(['branch_id', 'slot_starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'slot_starts_at']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('collection_type');
        });

        Schema::table('collection_closures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branch_hours');
        Schema::dropIfExists('branches');
    }
};
