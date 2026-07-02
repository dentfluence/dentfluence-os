<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse
 * -----------
 * One single shape for EVERY API reply, so the mobile app, Tulip and any
 * future client always parse responses the same way.
 *
 *   Success ->  { "success": true,  "message": "...", "data":   {...} }
 *   Error   ->  { "success": false, "message": "...", "errors": [...] }
 *
 * Any API controller that `use ApiResponse;` gets these two helpers.
 */
trait ApiResponse
{
    /**
     * Return a successful response in the standard envelope.
     *
     * @param  mixed   $data     The payload (array, model, collection, or null).
     * @param  string  $message  A short human-readable message.
     * @param  int     $status   HTTP status code (200 OK by default).
     */
    protected function success($data = null, string $message = '', int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        // Optional pagination info (current_page, total, last_page, ...).
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return an error response in the standard envelope.
     *
     * @param  string  $message  What went wrong, in plain language.
     * @param  mixed   $errors   Optional details (e.g. field validation errors).
     * @param  int     $status   HTTP status code (400 Bad Request by default).
     */
    protected function error(string $message = '', $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
