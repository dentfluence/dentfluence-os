<?php

namespace App\Abdm\Contracts;

/**
 * The ONE interface every ABDM gateway implementation must satisfy.
 *
 * Why this exists: no module, controller, or service ever talks to ABDM directly.
 * They call AbdmManager, which calls a class implementing THIS interface. Today the
 * bound implementation is NullGatewayClient (does nothing). Later we bind a Sandbox
 * client, then a Production client — with ZERO changes to any caller. That swap-ability
 * is the whole point of the ABDM-as-a-layer design (see docs/abdm/02-TARGET-ARCHITECTURE).
 */
interface AbdmGatewayClient
{
    /** Is a real gateway connection configured & enabled? (false for the null client) */
    public function isLive(): bool;

    /** Which environment this client targets: 'none' | 'sandbox' | 'production'. */
    public function environment(): string;

    /**
     * Push a FHIR bundle / payload out to ABDM (e.g. link a care-context, share a record).
     * @param  array  $payload  already FHIR-mapped + (later) signed/encrypted
     * @return array  normalized response: ['ok' => bool, 'txn_id' => ?string, 'error' => ?string]
     */
    public function send(string $operation, array $payload): array;

    /**
     * Fetch data from ABDM (e.g. external records under a consent).
     * @return array  ['ok' => bool, 'data' => mixed, 'error' => ?string]
     */
    public function fetch(string $operation, array $params): array;
}
