<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('promotion_channels');
            $table->text('message')->nullable();

            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();

            $table->timestamp('applied_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();

            // The affiliate-only User account created once approved — see
            // AffiliateApplicationService::approve(). Null until then, and
            // for every rejected application.
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();

            // A single-use "set your password" link — this app has no
            // forgot-password/reset system anywhere else to reuse (see the
            // "no open registration" comment in routes/web.php), so this is
            // its own self-contained scheme rather than Laravel's signed-URL
            // helper, which doesn't survive a GET (show the form) -> POST
            // (submit the new password) handoff without reconstructing the
            // exact original URL. Cleared the moment the password is set,
            // making the link single-use. See AffiliateSetPasswordController.
            $table->string('set_password_token')->nullable()->unique();
            $table->timestamp('set_password_expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_applications');
    }
};
