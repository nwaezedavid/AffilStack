<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile and business details a user can fill in from Profile &
     * Security — used for the birthday and festive-season emails (which
     * pick a festival by country and religion) and shown on the admin user
     * record. Every field is optional; company_name and country already
     * exist and double as "Business name" and "Country".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
            $table->date('birth_date')->nullable()->after('avatar_path');
            $table->string('phone', 40)->nullable()->after('birth_date');
            $table->string('gender', 30)->nullable()->after('phone');
            $table->string('religion', 40)->nullable()->after('gender');
            $table->string('business_website')->nullable()->after('company_name');
            $table->string('business_industry', 120)->nullable()->after('business_website');
            $table->string('city', 120)->nullable()->after('country');
            $table->string('state_region', 120)->nullable()->after('city');
            $table->string('timezone', 64)->nullable()->after('state_region');
            $table->timestamp('last_login_at')->nullable()->after('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path', 'birth_date', 'phone', 'gender', 'religion',
                'business_website', 'business_industry', 'city', 'state_region', 'timezone', 'last_login_at',
            ]);
        });
    }
};
