<?php

return [

    /*
    |--------------------------------------------------------------------
    | Lifecycle emails (birthday, festive season, re-engagement)
    |--------------------------------------------------------------------
    |
    | Defaults only — every value here can be changed by an admin from
    | Support > Lifecycle Emails > "Settings" (stored in SiteSetting, which
    | always wins over these). See App\Support\Lifecycle\LifecycleSettings.
    |
    */

    /** Local hour (0-23, in the user's own timezone) lifecycle emails go out. */
    'send_hour' => (int) env('LIFECYCLE_SEND_HOUR', 9),

    /**
     * A user active within this many days is "Active"; between this and
     * inactive_after_days they're "Idle"; beyond it, "Inactive".
     */
    'active_within_days' => (int) env('LIFECYCLE_ACTIVE_WITHIN_DAYS', 7),

    'inactive_after_days' => (int) env('LIFECYCLE_INACTIVE_AFTER_DAYS', 30),

    /**
     * last_active_at is written at most this often per user, so the
     * activity middleware costs one tiny UPDATE every few minutes rather
     * than one per request.
     */
    'activity_write_interval_minutes' => 5,

    /** Daily-active-user history kept for the admin sparkline. */
    'activity_history_days' => 90,

];
