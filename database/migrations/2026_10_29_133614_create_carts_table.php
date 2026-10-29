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
        // A cart belongs to a customer, or to a guest identified by a token kept in their session.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->nullable()->unique();
            $table->timestamps();
        });

        // Each item is either a test or a package (exactly one of the two is set).
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lab_package_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cart_id', 'lab_test_id']);
            $table->unique(['cart_id', 'lab_package_id']);
            $table->index('lab_test_id');
            $table->index('lab_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
