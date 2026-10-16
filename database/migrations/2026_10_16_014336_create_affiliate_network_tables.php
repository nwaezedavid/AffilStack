<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 5: affiliate network connections (Impact, PartnerStack, Awin,
     * CJ, ClickBank, Digistore24) — the customer's network accounts, the
     * programs they've joined or could join, network-made tracked links on
     * products, per-content tracked links for attribution, and earnings
     * synced from the networks with a USD value for mixed-currency totals.
     */
    public function up(): void
    {
        Schema::create('network_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network', 30);
            $table->text('credentials')->nullable();
            $table->string('account_id', 120)->nullable();
            $table->string('account_name', 190)->nullable();
            $table->string('status', 20)->default('connected'); // pending, connected, error
            $table->text('connect_token')->nullable(); // Digistore24 approval request, while pending
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('programs_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedInteger('last_sync_new')->default(0);
            $table->unsignedInteger('last_sync_updated')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'network']);
            $table->index('status');
        });

        Schema::create('network_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 120);
            $table->string('name', 190);
            $table->string('advertiser_name', 190)->nullable();
            $table->string('status', 20)->default('joined'); // joined, pending, available
            $table->string('homepage_url', 1000)->nullable();
            $table->string('tracking_url', 2000)->nullable();
            $table->string('logo_url', 1000)->nullable();
            $table->string('category', 120)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('commission', 190)->nullable();
            $table->text('description')->nullable();
            $table->boolean('deep_linking')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['network_connection_id', 'external_id']);
            $table->index(['network_connection_id', 'status']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('network_program_id')->nullable()->after('affiliate_link')->constrained()->nullOnDelete();
            $table->text('network_link')->nullable()->after('network_program_id');
            $table->boolean('use_network_link')->default(false)->after('network_link');
        });

        // One tracked link per piece of content, so a sale can be traced to
        // the article, video or post that made it. Existing per-channel
        // links keep generation_id null and keep working.
        Schema::table('tracked_links', function (Blueprint $table) {
            $table->foreignId('generation_id')->nullable()->after('offer_id')->constrained()->nullOnDelete();
            // Added before the old unique index goes: it also starts with
            // offer_id, so the offer foreign key keeps an index throughout.
            $table->unique(['offer_id', 'module', 'generation_id']);
        });

        Schema::table('tracked_links', function (Blueprint $table) {
            $table->dropUnique(['offer_id', 'module']);
        });

        Schema::table('earnings', function (Blueprint $table) {
            $table->foreignId('generation_id')->nullable()->after('offer_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('usd_cents')->nullable()->after('currency');
            $table->string('description', 190)->nullable()->after('notes');
        });

        // Other currencies get their USD value from the daily exchange-rate
        // refresh (fx:refresh fills in any earning still missing one).
        DB::table('earnings')->where('currency', 'USD')->update(['usd_cents' => DB::raw('amount_cents')]);

        Schema::table('plans', function (Blueprint $table) {
            $table->integer('network_connections_limit')->default(0)->after('wordpress_sites_limit');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('earnings_currency', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('earnings_currency'));
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('network_connections_limit'));

        Schema::table('earnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generation_id');
            $table->dropColumn(['usd_cents', 'description']);
        });

        Schema::table('tracked_links', function (Blueprint $table) {
            $table->unique(['offer_id', 'module']);
        });

        Schema::table('tracked_links', function (Blueprint $table) {
            $table->dropUnique(['offer_id', 'module', 'generation_id']);
            $table->dropConstrainedForeignId('generation_id');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('network_program_id');
            $table->dropColumn(['network_link', 'use_network_link']);
        });

        Schema::dropIfExists('network_programs');
        Schema::dropIfExists('network_connections');
    }
};
