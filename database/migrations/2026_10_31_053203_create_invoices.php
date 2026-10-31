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
        // Gapless yearly counters for invoice and credit note numbers (client, 2026-10-31).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            // invoice | credit_note
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['type', 'year']);
        });

        // Issued when an order is paid; never changes afterwards. Everything shown on the PDF is kept here.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            // The online payment it was issued for; null when paid at collection.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->timestamp('issued_at');
            $table->char('currency', 3);
            // Seller (from Settings), buyer, lines and totals as issued.
            $table->json('seller');
            $table->json('buyer');
            $table->json('lines');
            $table->json('totals');
            $table->string('payment_note');
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->timestamp('issued_at');
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('document_sequences');
    }
};
