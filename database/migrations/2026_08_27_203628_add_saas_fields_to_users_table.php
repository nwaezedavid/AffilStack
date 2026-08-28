<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('email');
            $table->string('country')->nullable()->after('company_name');
            $table->integer('credits_balance')->default(0)->after('country');
            $table->boolean('is_suspended')->default(false)->after('credits_balance');
            $table->timestamp('last_active_at')->nullable()->after('is_suspended');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'country', 'credits_balance', 'is_suspended', 'last_active_at']);
        });
    }
};
