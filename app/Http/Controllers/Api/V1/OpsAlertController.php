<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Notifications\FcmSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OpsAlertController — 1.5 (2026-09-21).
 *
 * The missing half of ops/alerts.sh. That script has watched this box since
 * 19 Sep and has never once reached a phone: Telegram was declined, Brevo email
 * was declined, and WhatsApp is blocked behind Meta business verification. So
 * every alert has accumulated in backups/alerts.log, where a night-time CRIT is
 * seen the next morning at best.
 *
 * Push changed that on 21 Sep. The phones are already registered, the channel is
 * Google's own, and nothing passes through a third party — which was the standing
 * objection to a CallMeBot-style relay while DPDP processor contracts are being
 * prepared.
 *
 * So alerts.sh POSTs here and this hands the message to the same FcmSender the
 * clinic's own notifications use. Admins only, and by the ASSIGNED ROLE, never by
 * the legacy staff-type string (V.18) — a receptionist must not start receiving
 * database alarms because someone typed "admin" into her HR record.
 *
 * ⚠ THE LIMIT, STATED PLAINLY AND UNAVOIDABLE BY DESIGN: this route lives inside
 * the application it is meant to watch. It carries a failed backup, a dead queue,
 * a filling disk, an expiring certificate. It CANNOT carry "the box is gone",
 * because then nothing is left to send it. Row 1.5 already names that gap — an
 * external uptime ping is still owed before clinic #2.
 */
class OpsAlertController extends Controller
{
    /** Severities alerts.sh emits. Anything else is a caller bug, not an alert. */
    private const SEVERITIES = ['CRIT', 'WARN', 'OK'];

    public function store(Request $request): JsonResponse
    {
        // The shared secret is the only gate. It is compared with hash_equals so
        // the check cannot be timed, and a missing secret in config means the
        // route is CLOSED rather than open — failing open on an unauthenticated
        // endpoint that writes notifications would be worse than no alerts.
        $expected = (string) config('alerting.webhook_secret');
        $given    = (string) $request->header('X-Dentfluence-Alert-Secret', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            Log::warning('Ops alert rejected: bad or missing secret', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorised.'], 401);
        }

        $data = $request->validate([
            'severity' => 'required|string|in:' . implode(',', self::SEVERITIES),
            'check'    => 'required|string|max:64',
            'text'     => 'required|string|max:2000',
            'host'     => 'nullable|string|max:120',
        ]);

        // A recovery is not an alarm. It is logged and acknowledged, but it does
        // not buzz a phone — staff who get woken by good news stop reading alerts.
        if ($data['severity'] === 'OK') {
            return response()->json(['delivered' => 0, 'skipped' => 'recovery']);
        }

        $admins = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->isAdminRole());

        if ($admins->isEmpty()) {
            Log::critical('Ops alert had nobody to send to — no active admin user.', $data);

            return response()->json(['delivered' => 0, 'reason' => 'no admin'], 200);
        }

        $sender    = app(FcmSender::class);
        $delivered = 0;

        foreach ($admins as $admin) {
            $notification = AppNotification::create([
                'user_id'   => $admin->id,
                'type'      => 'popup',
                'priority'  => $data['severity'] === 'CRIT' ? 'urgent' : 'normal',
                'event_key' => 'ops.' . $data['check'],
                'title'     => '[' . $data['severity'] . '] ' . $data['check'],
                'message'   => $data['text'],
                'push'      => true,
            ]);

            $delivered += $sender->send($notification);
        }

        // Written even when zero phones were reached, because "the alert fired and
        // reached nobody" is itself the thing worth knowing later.
        Log::info('Ops alert dispatched', [
            'check'     => $data['check'],
            'severity'  => $data['severity'],
            'admins'    => $admins->count(),
            'delivered' => $delivered,
            'tokens'    => DeviceToken::live()->count(),
        ]);

        return response()->json([
            'delivered' => $delivered,
            'admins'    => $admins->count(),
        ]);
    }
}
