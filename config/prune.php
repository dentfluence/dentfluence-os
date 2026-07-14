<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log retention
    |--------------------------------------------------------------------------
    |
    | How many months of rows to keep in the high-volume log tables
    | (activities, and audit_logs when explicitly included).
    |
    | Used by: php artisan logs:prune
    |
    | 24 months covers a clinic's practical audit needs while keeping the
    | tables — and therefore backups and write performance — bounded.
    |
    | Set PRUNE_RETENTION_MONTHS in .env to override.
    |
    */
    'retention_months' => (int) env('PRUNE_RETENTION_MONTHS', 24),

];
