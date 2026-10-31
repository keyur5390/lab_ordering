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
        // Catalogue categories (client, 2026-10-31): a test or package can be in several.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('category_lab_test', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'lab_test_id']);
            $table->index('lab_test_id');
        });

        Schema::create('category_lab_package', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_package_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'lab_package_id']);
            $table->index('lab_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_lab_package');
        Schema::dropIfExists('category_lab_test');
        Schema::dropIfExists('categories');
    }
};
