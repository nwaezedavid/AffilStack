<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AffilStack Connector WordPress plugin: customers' connected WordPress
 * sites (with the shared secret both sides sign requests with), each
 * article sent to a site, and the per-plan number of sites allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('site_url');
            $table->string('rest_url');
            $table->string('name')->nullable();
            $table->text('secret');
            $table->string('status', 20)->default('pending');
            $table->string('connect_code_hash', 64)->nullable();
            $table->timestamp('connect_code_expires_at')->nullable();
            $table->boolean('auto_send')->default(false);
            $table->string('seo_plugin', 30)->nullable();
            $table->string('wp_version', 20)->nullable();
            $table->string('plugin_version', 20)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('wordpress_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wordpress_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->string('wp_post_status', 20)->nullable();
            $table->string('edit_url')->nullable();
            $table->string('preview_url')->nullable();
            $table->boolean('automatic')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['wordpress_site_id', 'generation_id']);
        });

        Schema::table('plans', function (Blueprint $table) {
            // 0 = not included, -1 = unlimited, otherwise the number of sites.
            $table->integer('wordpress_sites_limit')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('wordpress_sites_limit');
        });

        Schema::dropIfExists('wordpress_deliveries');
        Schema::dropIfExists('wordpress_sites');
    }
};
