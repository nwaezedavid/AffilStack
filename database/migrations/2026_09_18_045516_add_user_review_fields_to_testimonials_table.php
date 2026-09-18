<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I want users to be able to submit their review from their dashboard and
 * it will then appear in the admin dashboard area where I can review, edit
 * and approve it." user_id is null for every testimonial an admin authors
 * directly (unchanged existing behavior — is_published is still the only
 * lever there); it's set the moment a customer submits their own, which
 * always starts life as status=pending/is_published=false until an admin
 * approves or declines it. See Testimonial::STATUS_* and
 * TestimonialsTable's Approve/Decline row actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('status')->default('approved')->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('status');
        });
    }
};
