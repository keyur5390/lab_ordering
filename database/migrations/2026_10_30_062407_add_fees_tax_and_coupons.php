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
        // Home collection fee per area (client, 2026-10-30: per area, free above a site-wide value).
        Schema::table('service_areas', function (Blueprint $table) {
            $table->unsignedBigInteger('collection_fee_amount')->default(0);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            // Stored upper case; customers type it in any case.
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            // App\Enums\DiscountType: percent (1–100) or amount (minor units)
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value');
            // Percent coupons only: the most it can take off.
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_order_amount')->nullable();
            // Valid from/until these dates, inclusive, in the site timezone.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // What the order was charged, as worked out at checkout (all minor units of `currency`).
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('subtotal_amount')->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('coupon_code');
            $table->unsignedBigInteger('collection_fee_amount')->default(0)->after('discount_amount');
            // Rate in basis points (1800 = 18%); null when no tax applied.
            $table->unsignedInteger('tax_rate_basis_points')->nullable()->after('collection_fee_amount');
            $table->boolean('tax_inclusive')->default(true)->after('tax_rate_basis_points');
            $table->unsignedBigInteger('tax_amount')->default(0)->after('tax_inclusive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'discount_amount', 'collection_fee_amount', 'tax_rate_basis_points', 'tax_inclusive', 'tax_amount']);
        });

        Schema::dropIfExists('coupons');

        Schema::table('service_areas', function (Blueprint $table) {
            $table->dropColumn('collection_fee_amount');
        });
    }
};
