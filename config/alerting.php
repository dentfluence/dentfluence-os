<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ops alert webhook secret
    |--------------------------------------------------------------------------
    |
    | Shared secret between ops/alerts.sh on the host and POST /api/v1/ops/alert.
    | The route is unauthenticated by necessity — a shell script watching the box
    | has no user session — so this secret is the only thing standing in front of
    | it.
    |
    | UNSET MEANS CLOSED. The controller refuses every request when this is empty
    | rather than letting an open endpoint write notifications. An ops channel
    | that silently accepts anything is worse than one that is switched off.
    |
    | Set the SAME value in two places:
    |   .env.production            ALERT_WEBHOOK_SECRET=...
    |   /root/.dentfluence-alerts.conf  ALERT_WEBHOOK_SECRET=...
    |
    | Generate one with:  php -r "echo bin2hex(random_bytes(32));"
    |
    */
    'webhook_secret' => env('ALERT_WEBHOOK_SECRET'),

];
