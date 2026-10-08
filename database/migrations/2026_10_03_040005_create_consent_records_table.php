<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: proof of consent — one row every time someone makes or
     * changes a cookie choice (banner, preferences, a Global Privacy Control
     * / Do Not Track signal, or a withdrawal). consent_id is the random id
     * stored in the visitor's own afs_consent cookie, so a record can be
     * matched to a browser without storing anything identifying; the IP is
     * kept only as a salted HMAC.
     */
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('consent_id')->index();
            $table->foreignId('visitor_id')->nullable()->constrained('analytics_visitors')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->string('action', 20);
            $table->string('source', 20);
            $table->string('policy_version', 20);
            $table->char('ip_hash', 64)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('page_path', 200)->nullable();
            $table->boolean('gpc')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'id']);
            $table->index(['visitor_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
