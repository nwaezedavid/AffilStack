<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 6: the website, customer dashboard and partner portal in
     * more languages. English stays the source; each other language's text
     * is translated once by Claude, saved here, and only redone when the
     * English changes (which makes a new row). Admins can correct any line.
     */
    public function up(): void
    {
        Schema::create('site_languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // fr, es, pt-br
            $table->string('name', 60); // French
            $table->string('native_name', 60); // Français
            $table->string('status', 10)->default('draft'); // draft (translating, admins only), live
            $table->boolean('rtl')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            $table->char('hash', 40);
            $table->text('source');
            $table->text('translation')->nullable();
            $table->string('status', 10)->default('pending'); // pending, machine, reviewed, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('context', 255)->nullable(); // the page it was first seen on
            $table->date('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'hash']);
            $table->index(['locale', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable();
        });

        DB::table('site_languages')->insert([
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'status' => 'live', 'rtl' => false, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'status' => 'live', 'rtl' => false, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
        Schema::dropIfExists('translations');
        Schema::dropIfExists('site_languages');
    }
};
