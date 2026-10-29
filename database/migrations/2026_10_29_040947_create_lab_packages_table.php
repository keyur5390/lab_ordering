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
        Schema::create('lab_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // App\Enums\PackagePricingMode: fixed | discounted
            $table->string('pricing_mode', 20);
            // Used when pricing_mode = fixed.
            $table->unsignedBigInteger('price_amount')->nullable();
            // Used when pricing_mode = discounted. App\Enums\DiscountType: percent | amount
            $table->string('discount_type', 20)->nullable();
            // Whole percent (0–100) for percent; minor units for amount.
            $table->unsignedBigInteger('discount_value')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('lab_package_lab_test', function (Blueprint $table) {
            $table->foreignId('lab_package_id')->constrained()->cascadeOnDelete();
            // Restrict: a test must be removed from its packages before it can be deleted.
            $table->foreignId('lab_test_id')->constrained()->restrictOnDelete();

            $table->primary(['lab_package_id', 'lab_test_id']);
            $table->index('lab_test_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_package_lab_test');
        Schema::dropIfExists('lab_packages');
    }
};
