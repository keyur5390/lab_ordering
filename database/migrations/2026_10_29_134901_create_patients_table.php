<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // People a customer books tests for (PROJECT.md §2): themselves and family members.
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // App\Enums\PatientRelationship
            $table->string('relationship', 20);
            // Nullable only because the "Self" profile is created at sign-up before these are known.
            $table->date('date_of_birth')->nullable();
            // App\Enums\Sex
            $table->string('sex', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'relationship']);
        });

        // Every existing account gets its "Self" profile, as new accounts do on sign-up.
        $now = now();
        DB::table('users')->orderBy('id')->each(function (object $user) use ($now) {
            DB::table('patients')->insert([
                'user_id' => $user->id,
                'name' => $user->name,
                'relationship' => 'self',
                'email' => $user->email,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
