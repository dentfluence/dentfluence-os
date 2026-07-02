<?php

use App\Providers\AppServiceProvider;
use App\Providers\CommunicationServiceProvider;

return [
    AppServiceProvider::class,
    CommunicationServiceProvider::class,
    App\Providers\MarketingServiceProvider::class,
    App\Providers\AbdmServiceProvider::class,
    App\Providers\FoundationServiceProvider::class, // Phase 0 — Safety & Foundations
];