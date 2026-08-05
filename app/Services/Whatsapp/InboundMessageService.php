<?php

namespace App\Services\Whatsapp;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\WaMessage;
use App\Models\WaThread;
use App\Services\AppointmentService;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\AppointmentActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * InboundMessageService — record an incoming WhatsApp message into the unified
 * inbox (Phase B item 1.2, Chunk 3a).
 * ----------------------------------------------------------------------------
 * The existing lead webhook (WhatsAppLeadController) turns the FIRST message from
 * a new number into a PRM lead. This service runs alongside it and threads EVERY
 * inbound message into wa_threads / wa_messages, so the inbox shows the full
 * back-and-forth conversation — not just the first contact.
 *
 * It also opens Meta's 24-hour customer-service window each time a patient writes
 * in, which is what lets staff reply with free text (enforced in OutboundMessageService).
 *
 * Safe to call repeatedly: messages are de-duplicated on the provider's own
 * message id (Meta retries webhooks).
 */
class InboundMessageService
{
    public function __construct(
        protected WhatsAppCloudService $client = new WhatsAppCloudService(),
        // Slice 2 (2026-08-04): reused as-is to (a) execute Cancel via the
        // existing Appointment Engine method and (b) record Confirm/Reschedule
        // as generic PRE communication events. Neither dependency is new code
        // — both already exist and are used elsewhere in the app.
        protected AppointmentService $appointments = new AppointmentService(new AppointmentActivityLogger(new ActivityEngine())),
        protected ActivityEngine $activity = new ActivityEngine(),
    ) {}

    /**
     * Record one inbound message.
     *
     * @param array $args  from (phone, required), name, body, wa_message_id,
     *                      type, lead_id, raw (provider payload).
     * @return WaMessage|null  the stored message, or null if it was a duplicate.
     */
    public function record(array $args): ?WaMessage
    {
        $from = $args['from'] ?? null;
        if (! $from) {
            return null;
        }

        $phone = $this->client->normalizePhone($from);
        $body  = $args['body'] ?? '';
        $waId  = $args['wa_message_id'] ?? null;

        // De-dupe on Meta's message id (webhooks can be re-delivered).
        if ($waId && WaMessage::where('wa_message_id', $waId)->exists()) {
            return null;
        }

        $thread = $this->resolveThread($phone, $args);

        $message = $thread->messages()->create([
            'channel'       => 'whatsapp',
            'direction'     => WaMessage::INBOUND,
            'wa_message_id' => $waId,
            'from_phone'    => $phone,
            'to_phone'      => null, // us — our number is only stored as a Meta id
            'type'          => $args['type'] ?? 'text',
            'body'          => $body,
            'status'        => WaMessage::STATUS_RECEIVED,
            'payload'       => $args['raw'] ?? null,
        ]);

        // Update conversation state + open the 24-hour reply window.
        $now = Carbon::now();
        $thread->update([
            'last_message_at'   => $now,
            'last_inbound_at'   => $now,
            'last_direction'    => 'inbound',
            'last_preview'      => mb_substr($body, 0, 160),
            'window_expires_at' => $now->copy()->addHours(24),
            'unread_count'      => $thread->unread_count + 1,
            // Fill the name if we didn't have one yet.
            'contact_name'      => $thread->contact_name ?: ($args['name'] ?? null),
            // Make sure a re-opened chat shows as open.
            'status'            => $thread->status === 'archived' ? 'open' : $thread->status,
        ]);

        $this->handleInteractiveReply($message, $args);

        return $message;
    }

    /**
     * Slice 2 (2026-08-04) — appointment-reminder interactive buttons.
     * ------------------------------------------------------------------------
     * Meta's button-reply payload carries a "button_reply.id" we control (set
     * when the reminder template is sent — Slice 4). Recognised ids are
     * "confirm_appt_{id}" / "reschedule_appt_{id}" / "cancel_appt_{id}". Any
     * other inbound message (free text, or an id we don't recognise) is a
     * no-op here — it's already been recorded normally by record() above.
     *
     * Cancel reuses the existing Appointment Engine cancel() method — the
     * same one the web "Cancel with reason" flow already calls. No new
     * appointment logic, no new status.
     *
     * Confirm and Reschedule are NOT appointment-status changes: the
     * appointment lifecycle has no "confirmed" state, and a one-tap reply
     * carries no new date/time for an actual reschedule. Both are recorded as
     * generic PRE communication events via the existing ActivityEngine — the
     * same universal event log AppointmentActivityLogger itself writes to —
     * so they appear on the patient's Timeline without touching the
     * Appointment Engine or inventing a new state. Event names are
     * channel-agnostic ('communication.*'); the channel lives in metadata so
     * SMS/email can write the same event later with no new event type.
     */
    protected function handleInteractiveReply(WaMessage $message, array $args): void
    {
        $buttonId = $args['raw']['interactive']['button_reply']['id'] ?? null;

        if (! $buttonId || ! preg_match('/^(confirm|reschedule|cancel)_appt_(\d+)$/', $buttonId, $m)) {
            return;
        }

        [, $action, $appointmentId] = $m;

        $appointment = Appointment::find((int) $appointmentId);
        if (! $appointment) {
            Log::warning('WhatsApp interactive reply: appointment not found', [
                'button_id' => $buttonId,
            ]);
            return;
        }

        if ($action === 'cancel') {
            $this->appointments->cancel($appointment, 'Cancelled via WhatsApp reply', 'patient');
            return;
        }

        $appointment->loadMissing('patient');
        $relationshipId = $appointment->patient?->relationship_id;

        $event       = $action === 'confirm' ? 'communication.confirmed' : 'communication.reschedule_requested';
        $description = $action === 'confirm'
            ? 'Patient confirmed the appointment'
            : 'Patient requested a reschedule';

        $this->activity->log(
            $appointment,
            $event,
            null, // patient-originated — no staff actor
            [
                'channel'    => 'whatsapp',
                'message_id' => $message->wa_message_id,
                'template'   => 'appointment_reminder',
                'source'     => 'interactive_button',
                'button_id'  => $buttonId,
            ],
            $relationshipId,
            $description,
        );
    }

    /** Find the thread for this phone, or open one — linking patient/lead if known. */
    protected function resolveThread(string $phone, array $args): WaThread
    {
        $thread = WaThread::where('channel', 'whatsapp')
            ->where('contact_phone', $phone)
            ->first();

        $patient = $this->matchPatient($phone);

        if ($thread) {
            $patch = [];
            if (! $thread->patient_id && $patient) {
                $patch['patient_id'] = $patient->id;
            }
            if (! $thread->lead_id && ! empty($args['lead_id'])) {
                $patch['lead_id'] = $args['lead_id'];
            }
            if ($patch) {
                $thread->update($patch);
            }
            return $thread;
        }

        return WaThread::create([
            'channel'       => 'whatsapp',
            'contact_phone' => $phone,
            'patient_id'    => $patient?->id,
            'lead_id'       => $args['lead_id'] ?? null,
            'contact_name'  => $args['name'] ?? $patient?->name,
            'status'        => 'open',
        ]);
    }

    /** Match a known patient by phone (last 10 digits; phone is not encrypted). */
    protected function matchPatient(string $phone): ?Patient
    {
        $last10 = substr($phone, -10);
        if (strlen($last10) < 10) {
            return null;
        }

        return Patient::where('phone', 'like', '%' . $last10)->first();
    }
}
