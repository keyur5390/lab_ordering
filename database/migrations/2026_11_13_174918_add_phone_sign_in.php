<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phone sign-in (B-30): a verified mobile number on the account, and one-time codes sent by SMS.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // International format (+ and country code); unique so a code signs in one account.
            $table->string('phone', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
        });

        Schema::create('phone_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            // login | verify
            $table->string('purpose', 10);
            // The account signing in, or adding the number.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['phone', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at']);
        });
    }
};
