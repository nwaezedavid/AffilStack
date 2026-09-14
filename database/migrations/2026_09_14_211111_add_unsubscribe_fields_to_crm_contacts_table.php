<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM/email dashboard: contacts can now actually be emailed from the
     * platform (see CrmEmailService), so they need an unsubscribe link —
     * unsubscribe_token identifies the contact in that public, unauthenticated
     * link; unsubscribed_at (once set) blocks any further sends to them.
     */
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->string('unsubscribe_token')->nullable()->unique()->after('status');
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribe_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['unsubscribe_token', 'unsubscribed_at']);
        });
    }
};
