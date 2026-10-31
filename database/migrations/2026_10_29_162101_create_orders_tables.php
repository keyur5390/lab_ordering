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
        // Tests an admin flags as needing a prescription (client, 2026-10-29).
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->boolean('requires_prescription')->default(false)->after('preparation');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // App\Enums\OrderStatus
            $table->string('status', 30)->index();
            // App\Enums\PaymentMethod; pay at collection until online payments (client, 2026-10-29).
            $table->string('payment_method', 30);

            // Home collection: the area that served it and the slot, in UTC.
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('slot_starts_at')->index();
            $table->dateTime('slot_ends_at');

            // Address as booked (the customer may edit or delete their saved address later).
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->json('address');

            // Money in minor units of `currency`, as charged.
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('total_amount');

            // Prescription file on the private disk.
            $table->string('prescription_path')->nullable();
            $table->string('prescription_name')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['service_area_id', 'slot_starts_at']);
        });

        // One row per item per patient, with snapshots so orders keep showing what was booked.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('patient_name');
            $table->date('patient_date_of_birth');
            $table->string('patient_sex', 20);

            // App\Enums\CartItemType: test | package
            $table->string('item_type', 20);
            $table->foreignId('lab_test_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lab_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            // Package contents as booked (test names).
            $table->json('test_names')->nullable();
            $table->unsignedBigInteger('price_amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn('requires_prescription');
        });
    }
};
