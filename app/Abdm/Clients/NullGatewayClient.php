<?php

namespace App\Abdm\Clients;

use App\Abdm\Contracts\AbdmGatewayClient;
use Illuminate\Support\Facades\Log;

/**
 * The DEFAULT gateway client — does nothing.
 *
 * This is what makes the entire ABDM layer safe to ship today: every ABDM call
 * resolves to this no-op. Nothing leaves the building, no ABDM account is needed,
 * and existing behaviour is completely unchanged. When you're ready for Sandbox,
 * we add a SandboxGatewayClient and rebind it in AbdmServiceProvider — no caller changes.
 */
class NullGatewayClient implements AbdmGatewayClient
{
    public function isLive(): bool
    {
        return false;
    }

    public function environment(): string
    {
        return 'none';
    }

    public function send(string $operation, array $payload): array
    {
        // In local/dev we log that a call *would* have happened, so the pipeline is
        // observable end-to-end without any real ABDM connection.
        Log::debug("[ABDM:null] send() skipped — gateway not live", ['operation' => $operation]);
        return ['ok' => false, 'txn_id' => null, 'error' => 'abdm_gateway_not_configured'];
    }

    public function fetch(string $operation, array $params): array
    {
        Log::debug("[ABDM:null] fetch() skipped — gateway not live", ['operation' => $operation]);
        return ['ok' => false, 'data' => null, 'error' => 'abdm_gateway_not_configured'];
    }
}
