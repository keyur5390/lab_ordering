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
        // Customer addresses for home collection (generic international format).
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('postcode', 20);
            // Normalised (upper case, no spaces or dashes) for matching postcode service areas.
            $table->string('postcode_normalized', 20)->index();
            $table->char('country', 2);
            // Optional map pin; needed for radius and zone service areas.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        // Where home collection is available (PROJECT.md §6), managed by admins.
        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // App\Enums\ServiceAreaType: postcodes | radius | polygon
            $table->string('type', 20);
            // postcodes: the country the postcodes belong to.
            $table->char('country', 2)->nullable();
            // radius: centre and radius.
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            // polygon: list of [latitude, longitude] points.
            $table->json('polygon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('service_area_postcodes', function (Blueprint $table) {
            $table->foreignId('service_area_id')->constrained()->cascadeOnDelete();
            // Normalised the same way as addresses.postcode_normalized.
            $table->string('postcode', 20);

            $table->primary(['service_area_id', 'postcode']);
            $table->index('postcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_area_postcodes');
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('addresses');
    }
};
