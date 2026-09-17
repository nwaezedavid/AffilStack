<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #3: "paste their reply, get an AI-drafted response" — a small
     * history of each exchange, both so a user can revisit past drafts and
     * so credit spend (CreditManager::spend) has something to reference.
     * offer_id is nullable — a reply can be about a general LinkedIn
     * conversation with no specific offer in mind.
     */
    public function up(): void
    {
        Schema::create('linkedin_reply_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('their_message');
            $table->text('draft_reply');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_reply_drafts');
    }
};
