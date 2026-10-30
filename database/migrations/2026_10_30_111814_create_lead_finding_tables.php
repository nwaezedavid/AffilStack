<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2B lead finding: AffilStack finds buyers for a product (Google Maps +
 * company websites, Apollo, Hunter), scores and tags them, and files them
 * into the product's CRM list. Plans carry a monthly lead allowance; leads
 * past it cost credits.
 */
return new class extends Migration
{
    /**
     * Monthly leads included per plan slug — editable per plan in admin.
     *
     * @var array<string, int>
     */
    protected array $allowances = [
        'starter' => 100,
        'growth' => 500,
        'pro' => 1000,
        'business' => 5000,
        'agency' => 10000,
    ];

    public function up(): void
    {
        Schema::create('lead_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued'); // queued, running, completed, failed
            $table->string('trigger')->default('manual'); // manual, research, hunter_agent
            $table->unsignedInteger('target')->default(25);
            $table->json('sources')->nullable();
            $table->unsignedInteger('found')->default(0);
            $table->unsignedInteger('with_email')->default(0);
            $table->unsignedInteger('saved')->default(0);
            $table->unsignedInteger('free_leads')->default(0);
            $table->unsignedInteger('credits_spent')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('lead_source_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('apollo_enabled')->default(false);
            $table->boolean('hunter_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->string('apollo_test_status')->nullable();
            $table->text('apollo_test_message')->nullable();
            $table->string('hunter_test_status')->nullable();
            $table->text('hunter_test_message')->nullable();
            $table->timestamps();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->integer('monthly_lead_allowance')->default(0)->after('contact_limit');
        });

        foreach ($this->allowances as $slug => $allowance) {
            DB::table('plans')->where('slug', $slug)->update(['monthly_lead_allowance' => $allowance]);
        }

        Schema::table('offers', function (Blueprint $table) {
            $table->boolean('find_leads')->default(false)->after('affiliate_link');
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('status');
            $table->unsignedTinyInteger('lead_score')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['tags', 'lead_score']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('find_leads');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('monthly_lead_allowance');
        });

        Schema::dropIfExists('lead_source_settings');
        Schema::dropIfExists('lead_runs');
    }
};
