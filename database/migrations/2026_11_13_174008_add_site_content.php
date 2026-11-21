<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site content (B-28): FAQs, and About and Contact pages next to the privacy policy and terms.
     */
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            // Markdown, written by admins.
            $table->text('answer');
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });

        // TODO(client): replace with the real text (Admin → Pages).
        $now = now();
        DB::table('pages')->insert([
            ['slug' => 'about', 'title' => 'About us', 'body' => 'This page is a placeholder. An admin can replace it under **Admin → Pages**.', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'contact', 'title' => 'Contact us', 'body' => "This page is a placeholder. An admin can replace it under **Admin → Pages**.\n\nAdd a phone number, an email address and opening hours here.", 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->whereIn('slug', ['about', 'contact'])->delete();
        Schema::dropIfExists('faqs');
    }
};
