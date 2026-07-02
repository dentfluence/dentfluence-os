<?php

/**
 * ABDM layer configuration.
 *
 * Phase 1: everything is OFF and bound to the no-op NullGatewayClient. Flipping
 * these on happens later (per-facility), and the runtime master switch is the
 * app_settings('abdm_enabled') flag — this file just wires defaults + the client.
 */
return [

    // Default master switch (env override). The live runtime switch is the
    // app_settings('abdm_enabled') row, read by AbdmManager::enabled().
    'enabled' => env('ABDM_ENABLED', false),

    // Which gateway client implementation to bind. Until Sandbox is built, the
    // only real option is the null client. Later: 'sandbox' | 'production'.
    'driver' => env('ABDM_DRIVER', 'null'),

    // Map driver name → concrete class. New drivers are added here only.
    'clients' => [
        'null' => \App\Abdm\Clients\NullGatewayClient::class,
        // 'sandbox'    => \App\Abdm\Clients\SandboxGatewayClient::class,    // future
        // 'production' => \App\Abdm\Clients\ProductionGatewayClient::class, // future
    ],

    // FHIR settings (used once the mapping engine is built — Phase 2).
    'fhir' => [
        'enabled' => env('ABDM_FHIR_ENABLED', false),
    ],

    // Consent defaults (Phase 3+).
    'consent' => [
        'required'           => env('ABDM_CONSENT_REQUIRED', true),
        'default_expiry_days' => 180,
    ],
];
