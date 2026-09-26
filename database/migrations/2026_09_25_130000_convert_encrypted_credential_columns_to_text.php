<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These `credentials` columns were created as `json`, but every model casts
 * them with 'encrypted:array', which stores base64 ciphertext — not JSON.
 * SQLite never checks, so tests passed; MariaDB (a JSON column is LONGTEXT
 * with a json_valid() CHECK) and MySQL 8 reject every save with an
 * integrity-constraint error. The create migrations now say text(); this
 * converts any MySQL/MariaDB database that already ran the old versions.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'partner_stack_settings',
        'heygen_settings',
        'email_connections',
        'linkedin_oauth_settings',
        'social_connections',
        'tiktok_settings',
        'instagram_settings',
        'github_sync_settings',
    ];

    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'credentials')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->text('credentials')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: converting back to json would reject
        // the encrypted values this column must hold.
    }
};
