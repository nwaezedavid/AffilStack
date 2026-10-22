<?php

/**
 * Runtime security checks (App\Support\Security\PhpRuntime), run daily by
 * Tom, the Security Agent, and shown to admins as a banner when something
 * needs the host's attention.
 */
return [

    /*
    | The oldest patch of each PHP branch that is safe to serve the site on.
    | PHP2Shell (CVE-2026-109618 / CVE-2026-109619, October 2026) is fixed in
    | 8.4.27 and 8.5.12 — anything older on those branches is critical. Raise
    | these when PHP ships a future critical fix; the daily php.net check
    | below also catches any newer security release on its own.
    */
    'php_minimum_patches' => [
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    ],

    // Branches AffilStack runs on (composer.json requires PHP ^8.4).
    'php_supported_branches' => ['8.4', '8.5'],

    /*
    | Daily lookup of the newest release of the running branch on php.net, so
    | a security release AffilStack hasn't been told about yet is still
    | reported. Set PHP_RELEASE_CHECK=false to turn it off.
    */
    'php_release_check' => (bool) env('PHP_RELEASE_CHECK', true),
    'php_release_feed' => 'https://www.php.net/releases/index.php',

    /*
    | php.ini settings that widen PHP2Shell's attack surface when switched on
    | (see public/.user.ini, which turns them off where the host allows it).
    | Each value is the safe setting.
    */
    'php_risky_ini' => [
        'session.upload_progress.enabled' => '0',
    ],

    // A bigger post_max_size on the website is reported (AffilStack's
    // largest form is about 25 MB).
    'php_max_post_megabytes' => 64,

    // Functions the website should have switched off (disable_functions):
    // only the command-line cron, which lifts the limits for itself, runs
    // programs such as git and composer.
    'web_disabled_functions' => ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'],

    // How often each web worker re-records the PHP version it serves with.
    'php_runtime_record_minutes' => 360,

    /*
    | Folders under the app that must never contain PHP: uploads and public
    | files. A PHP file in one of them is treated as a possible web shell.
    | Paths are relative to the app's base folder.
    */
    'executable_scan_paths' => [
        'public',
        'storage/app',
    ],

    // The only PHP and server config files allowed in those folders.
    'executable_allowlist' => [
        'public/index.php',
        'public/.htaccess',
        'public/.user.ini',
        'storage/app/public/.htaccess',
    ],

    // Extensions a web server may run as PHP. Never stored from an upload.
    'executable_extensions' => ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps', 'pgif', 'shtml', 'cgi', 'pl', 'py', 'asp', 'aspx', 'jsp', 'htaccess'],

    // Uploads larger than this are checked by name only, not by content.
    'upload_content_scan_max_bytes' => 25 * 1024 * 1024,

];
