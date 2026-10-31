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
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sample_type', 100);
            $table->text('preparation')->nullable();
            $table->unsignedInteger('turnaround_hours');
            $table->unsignedBigInteger('price_amount');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
