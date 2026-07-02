<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\WaThread;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Http\Request;

/**
 * WhatsAppInboxController — the unified WhatsApp inbox UI (Phase B 1.2, Chunk 3b).
 * ----------------------------------------------------------------------------
 * Three screens/actions:
 *   index  — list of conversations, newest first, with unread badges.
 *   show   — one conversation (message bubbles) + a reply box. Opening it marks
 *            the thread as read.
 *   reply  — sends a free-text reply through OutboundMessageService (which runs
 *            the DPDP consent gate, records the message, audits, and sends —
 *            dry-run aware).
 *
 * Stays thin: all sending logic lives in the service.
 */
class WhatsAppInboxController extends Controller
{
    /** Conversation list. */
    public function index()
    {
        $threads = WaThread::with(['patient', 'lead'])
            ->recent()
            ->paginate(25);

        $unreadTotal = (int) WaThread::sum('unread_count');

        return view('communication.whatsapp.index', compact('threads', 'unreadTotal'));
    }

    /** One conversation + reply box. Opening marks it read. */
    public function show(WaThread $thread, OutboundMessageService $outbound)
    {
        $thread->load(['patient', 'lead']);
        $messages = $thread->messages()->with('sentBy')->get();

        if ($thread->unread_count > 0) {
            $thread->update(['unread_count' => 0]);
        }

        // Whether a free-text reply is currently allowed (consent / 24h window).
        $gate = $outbound->consentGate($thread, 'service');

        // Templates: the catalog + whether sending one is allowed right now.
        $templates    = config('whatsapp.templates', []);
        $templateGate = $outbound->consentGate($thread, 'service', isTemplate: true);

        return view('communication.whatsapp.show', compact('thread', 'messages', 'gate', 'templates', 'templateGate'));
    }

    /** Send a reply. */
    public function reply(Request $request, WaThread $thread, OutboundMessageService $outbound)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $res = $outbound->sendText($thread->contact_phone, $data['body'], [
            'category'   => 'service',
            'patient_id' => $thread->patient_id,
            'lead_id'    => $thread->lead_id,
            'sent_by_id' => $request->user()?->id,
        ]);

        if (! $res['ok']) {
            return back()->with('error', $res['reason'] ?? 'Message could not be sent.');
        }

        return back()->with('success', config('whatsapp.dry_run')
            ? 'Message recorded (dry-run — nothing actually sent).'
            : 'Message sent.');
    }

    /** Send a pre-approved template (works outside the 24h window). */
    public function sendTemplate(Request $request, WaThread $thread, OutboundMessageService $outbound)
    {
        $data = $request->validate([
            'template' => ['required', 'string'],
            'vars'     => ['array'],
            'vars.*'   => ['nullable', 'string', 'max:500'],
        ]);

        $res = $outbound->sendTemplate($thread->contact_phone, $data['template'], $data['vars'] ?? [], [
            'patient_id' => $thread->patient_id,
            'lead_id'    => $thread->lead_id,
            'sent_by_id' => $request->user()?->id,
        ]);

        if (! $res['ok']) {
            return back()->with('error', $res['reason'] ?? 'Template could not be sent.');
        }

        return back()->with('success', config('whatsapp.dry_run')
            ? 'Template recorded (dry-run — nothing actually sent).'
            : 'Template sent.');
    }
}
