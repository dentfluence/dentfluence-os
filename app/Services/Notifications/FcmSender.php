<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FcmSender — the phone half of a popup (N-5, 2026-09-09).
 *
 * Sends one AppNotification row to every live device token of its recipient
 * over FCM HTTP v1. Deliberately hand-rolled against the REST API rather than
 * pulling in kreait/firebase-php or google/apiclient: the whole job is one
 * signed JWT and one POST, and a new composer dependency on a box that
 * deploys by rebuilding a Docker image is a cost with no return here.
 *
 * Rules
 *  - Only popup-level rows with push=true ever reach this class; the
 *    dispatcher decides that when it writes the row.
 *  - push_sent_at is stamped whatever happens — sent, skipped or failed — so
 *    a retry can never double-buzz someone's phone. A push is a moment; a
 *    missed one is a bell item, not a debt.
 *  - A token FCM reports as UNREGISTERED is invalidated on the spot. Dead
 *    tokens are the normal end of an app install, not an error.
 *  - Never throws. Push is the least important copy of a notification.
 */
class FcmSender
{
    private const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CACHE_KEY = 'fcm_access_token';

    /** @return int devices actually pushed to */
    public function send(AppNotification $notification): int
    {
        try {
            return $this->dispatch($notification);
        } catch (Throwable $e) {
            report($e);
            $this->markHandled($notification);

            return 0;
        }
    }

    private function dispatch(AppNotification $notification): int
    {
        if (! $notification->push || $notification->push_sent_at || ! $notification->user_id) {
            return 0;
        }

        if (! config('fcm.enabled')) {
            Log::info('FCM disabled — push skipped', ['notification' => $notification->id]);
            $this->markHandled($notification);

            return 0;
        }

        if ($this->inQuietHours($notification)) {
            Log::info('FCM quiet hours — push dropped, bell keeps it', ['notification' => $notification->id]);
            $this->markHandled($notification);

            return 0;
        }

        $tokens = DeviceToken::live()->where('user_id', $notification->user_id)->get();
        if ($tokens->isEmpty()) {
            $this->markHandled($notification);

            return 0;
        }

        $accessToken = $this->accessToken();
        if (! $accessToken) {
            $this->markHandled($notification);

            return 0;
        }

        $sent = 0;
        foreach ($tokens as $device) {
            if ($this->sendOne($accessToken, $device, $notification)) {
                $sent++;
            }
        }

        $this->markHandled($notification);

        return $sent;
    }

    private function sendOne(string $accessToken, DeviceToken $device, AppNotification $n): bool
    {
        $url = 'https://fcm.googleapis.com/v1/projects/' . config('fcm.project_id') . '/messages:send';

        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->post($url, [
                'message' => [
                    'token'        => $device->token,
                    'notification' => [
                        'title' => $n->title,
                        'body'  => (string) $n->message,
                    ],
                    // The app reads `data` to decide whether to raise the
                    // in-app popup dialog and where the action button goes.
                    'data' => [
                        'notification_id' => (string) $n->id,
                        'event_key'       => (string) $n->event_key,
                        'priority'        => (string) $n->priority,
                        'action_url'      => (string) $n->action_url,
                        'action_label'    => (string) $n->action_label,
                    ],
                    'android' => [
                        'priority'     => 'high',
                        'notification' => [
                            'channel_id' => config('fcm.android_channel'),
                            'sound'      => 'default',
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        // A token that no longer belongs to an install: retire it quietly.
        $status = $response->json('error.status');
        if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $device->update(['invalidated_at' => now()]);
            Log::info('FCM token retired', ['device' => $device->id, 'status' => $status]);

            return false;
        }

        Log::warning('FCM send failed', [
            'device' => $device->id,
            'code'   => $response->status(),
            'body'   => mb_substr($response->body(), 0, 400),
        ]);

        return false;
    }

    /**
     * Quiet hours span midnight, so the window is "from >= until" — 21:30
     * to 09:30 is quiet, and the comparison has to be an OR, not an AND.
     * Admins are exempt: a failed backup at 2am is exactly when they want it.
     */
    private function inQuietHours(AppNotification $notification): bool
    {
        if (! config('fcm.quiet_hours.enabled')) {
            return false;
        }

        $exempt = (array) config('fcm.quiet_hours.exempt_roles', []);
        if (in_array($notification->target_role, $exempt, true)) {
            return false;
        }
        // The row's target_role is 'owner' for owner-addressed events, so also
        // check what the user actually is.
        if ($notification->target_role === NotificationCatalog::OWNER
            && $notification->user?->hasRole(Role::ADMIN)) {
            return false;
        }

        $now   = now();
        $from  = Carbon::createFromFormat('H:i', config('fcm.quiet_hours.from'), $now->timezone)->setDateFrom($now);
        $until = Carbon::createFromFormat('H:i', config('fcm.quiet_hours.until'), $now->timezone)->setDateFrom($now);

        return $from->greaterThan($until)
            ? ($now->greaterThanOrEqualTo($from) || $now->lessThan($until))  // window crosses midnight
            : ($now->greaterThanOrEqualTo($from) && $now->lessThan($until));
    }

    private function markHandled(AppNotification $notification): void
    {
        $notification->forceFill(['push_sent_at' => now()])->saveQuietly();
    }

    // ── OAuth2: service account JWT → access token ───────────────────────────

    /** Cached for 55 minutes; Google issues them for 60. */
    private function accessToken(): ?string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(55), function () {
            $creds = $this->credentials();
            if (! $creds) {
                return null;
            }

            $jwt = $this->signedJwt($creds);
            if (! $jwt) {
                return null;
            }

            $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('FCM token exchange failed', ['body' => mb_substr($response->body(), 0, 400)]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function credentials(): ?array
    {
        $path = config('fcm.credentials');
        if (! $path || ! is_readable($path)) {
            Log::error('FCM credentials file not readable', ['path' => $path]);

            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return (isset($json['client_email'], $json['private_key'])) ? $json : null;
    }

    private function signedJwt(array $creds): ?string
    {
        $now    = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $input = $this->b64($header) . '.' . $this->b64($claims);

        $signature = '';
        if (! openssl_sign($input, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::error('FCM JWT signing failed — is the private_key intact?');

            return null;
        }

        return $input . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function b64(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }
}
