<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Privacy and data (B-27): consent at sign-up, anonymised accounts, and admin-edited pages
     * (privacy policy and terms first; more pages in B-28).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable();
            // Set when the customer deleted their account: personal details are gone, orders stay.
            $table->timestamp('anonymised_at')->nullable();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('title');
            // Markdown, written by admins.
            $table->longText('body');
            $table->timestamps();
        });

        // TODO(client): replace with the real policy and terms (Admin → Pages).
        $now = now();
        DB::table('pages')->insert([
            [
                'slug' => 'privacy',
                'title' => 'Privacy policy',
                'body' => "This page is a placeholder. An admin can replace it under **Admin → Pages**.\n\nIt should say what personal and health data we collect, why, who we share it with (for example payment providers and the lab), how long we keep it, and how you can download or delete your data from **Your account → Privacy and data**.",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of service',
                'body' => 'This page is a placeholder. An admin can replace it under **Admin → Pages**.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['privacy_accepted_at', 'anonymised_at']);
        });
    }
};
