<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Feature 7: the developer portal and add-on marketplace — developers,
 * their add-ons (each listing change is a version an admin approves),
 * customers' installs and monthly subscriptions, "Connect with AffilStack"
 * (OAuth) for connected apps, developers' 70% earnings, and the
 * templates customers install from template packs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 80);
            $table->string('slug', 90)->unique();
            $table->string('website')->nullable();
            $table->string('support_email');
            $table->text('bio')->nullable();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->decimal('revenue_share_percent', 5, 2)->nullable(); // null = marketplace default
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 90)->unique();
            $table->string('type', 20); // app | template_pack | automation
            $table->string('status', 20)->default('draft'); // draft | published | suspended | retired
            $table->unsignedBigInteger('live_version_id')->nullable();
            $table->string('client_id', 60)->nullable()->unique();
            $table->string('client_secret_hash', 64)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('installs_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        Schema::create('addon_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->string('status', 20)->default('draft'); // draft | submitted | approved | rejected | superseded
            $table->string('name', 60);
            $table->string('tagline', 140);
            $table->text('description');
            $table->string('category', 40);
            $table->string('icon_path')->nullable();
            $table->json('screenshots')->nullable();
            $table->string('pricing', 10)->default('free'); // free | monthly
            $table->unsignedInteger('price_cents')->default(0); // USD
            $table->string('website_url')->nullable();
            $table->string('support_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->string('install_url')->nullable();
            $table->json('redirect_uris')->nullable();
            $table->json('scopes')->nullable();
            $table->string('webhook_url')->nullable();
            $table->json('webhook_events')->nullable();
            $table->json('content')->nullable();
            $table->text('changelog')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['addon_id', 'status']);
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->foreign('live_version_id')->references('id')->on('addon_versions')->nullOnDelete();
        });

        Schema::create('addon_installs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // pending_payment | active | past_due | expired | uninstalled
            $table->json('scopes')->nullable();
            $table->unsignedInteger('price_cents')->default(0); // USD, locked at subscription time
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('renewal_attempted_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();

            $table->unique(['addon_id', 'user_id']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->text('redirect_uri');
            $table->json('scopes');
            $table->string('code_challenge', 128)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('oauth_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_install_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('api_tokens', function (Blueprint $table) {
            $table->foreignId('addon_install_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->json('scopes')->nullable()->after('scope');
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->foreignId('addon_install_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('addon_install_id')->nullable()->after('credit_package_id')->constrained()->nullOnDelete();
        });

        Schema::table('referral_payouts', function (Blueprint $table) {
            $table->string('source', 20)->default('referral')->after('user_id'); // referral | developer
        });

        Schema::create('developer_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('addon_install_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('sale'); // sale | refund | chargeback | adjustment
            $table->integer('gross_cents');
            $table->decimal('share_percent', 5, 2);
            $table->integer('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | paid | reversed
            $table->timestamp('available_at')->nullable();
            $table->foreignId('referral_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['developer_profile_id', 'status']);
        });

        Schema::create('user_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_install_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // prompt | email | post | hook | script
            $table->string('title', 160);
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });

        // Admin sub-accounts can be given the new Marketplace department.
        if (Schema::hasTable('permissions')) {
            Permission::findOrCreate('department.marketplace', 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_templates');
        Schema::dropIfExists('developer_earnings');

        Schema::table('referral_payouts', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('addon_install_id'));
        Schema::table('webhook_endpoints', fn (Blueprint $table) => $table->dropConstrainedForeignId('addon_install_id'));
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('addon_install_id');
            $table->dropColumn(['scopes', 'expires_at']);
        });

        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_authorization_codes');
        Schema::dropIfExists('addon_installs');
        Schema::table('addons', fn (Blueprint $table) => $table->dropForeign(['live_version_id']));
        Schema::dropIfExists('addon_versions');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('developer_profiles');
    }
};
