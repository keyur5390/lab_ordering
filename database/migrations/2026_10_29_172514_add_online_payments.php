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
        // Online orders hold their slot until this moment while payment is pending (UTC).
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_expires_at')->nullable()->after('payment_method');
            $table->index(['status', 'payment_expires_at']);
        });

        // One row per attempt to pay online: a hosted checkout at the gateway.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // App\Enums\PaymentGatewayName
            $table->string('gateway', 20);
            // The gateway's checkout id (Stripe Checkout Session, Razorpay Payment Link).
            $table->string('reference')->nullable();
            // The gateway's id for the money received (Stripe PaymentIntent, Razorpay payment), used for refunds.
            $table->string('gateway_payment_id')->nullable();
            // App\Enums\PaymentStatus
            $table->string('status', 20)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'reference']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            // Admin who issued it; null for automatic refunds.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\RefundStatus
            $table->string('status', 20);
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->string('gateway_refund_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_expires_at']);
            $table->dropColumn('payment_expires_at');
        });
    }
};
