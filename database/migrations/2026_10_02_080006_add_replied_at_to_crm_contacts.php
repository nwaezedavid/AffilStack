<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->timestamp('replied_at')->nullable()->after('unsubscribed_at');
            $table->index(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'email']);
            $table->dropColumn('replied_at');
        });
    }
};
