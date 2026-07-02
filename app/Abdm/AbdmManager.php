<?php

namespace App\Abdm;

use App\Abdm\Contracts\AbdmGatewayClient;
use App\Abdm\Fhir\FhirMappingEngine;
use App\Models\AppSetting;

/**
 * AbdmManager — the single front door to the ABDM layer.
 *
 * Every module/service that needs anything ABDM-related calls THIS class (usually
 * via the `Abdm` facade or dependency injection), never an ABDM SDK or HTTP client.
 *
 * In Phase 1 this is mostly a skeleton: it knows whether ABDM is enabled and holds
 * the bound gateway client (NullGatewayClient by default). The real managers
 * (ABHA / HPR / HFR / Consent / FHIR / Sync) get plugged in here in later phases
 * exactly as designed in docs/abdm. Keeping the front door stable now means future
 * phases add capability without changing a single caller.
 */
class AbdmManager
{
    public function __construct(
        protected AbdmGatewayClient $gateway
    ) {}

    /**
     * Master feature flag. Reads app_settings('abdm_enabled'), default '0' (off).
     * While this is off, the whole layer is inert and the app behaves exactly as before.
     */
    public function enabled(): bool
    {
        return (string) AppSetting::get('abdm_enabled', '0') === '1';
    }

    /** The currently bound gateway client (NullGatewayClient until Sandbox/Prod wired). */
    public function gateway(): AbdmGatewayClient
    {
        return $this->gateway;
    }

    /** The FHIR mapping engine — turns internal models into FHIR resources. */
    public function fhir(): FhirMappingEngine
    {
        return app(FhirMappingEngine::class);
    }

    /** Convenience: are we actually talking to a live ABDM gateway right now? */
    public function isLive(): bool
    {
        return $this->enabled() && $this->gateway->isLive();
    }

    /**
     * A safe no-op-aware entry point modules can already call. While ABDM is disabled
     * or the gateway is the null client, this returns a skipped result and changes nothing.
     *
     * Example (future): app('abdm')->record('encounter.link', $fhirBundle);
     */
    public function record(string $operation, array $payload): array
    {
        if (! $this->isLive()) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'abdm_disabled'];
        }
        return $this->gateway->send($operation, $payload);
    }
}
