<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TemplateController (API v1)
 * ----------------------------
 * Mobile face of the Message Template editor (web:
 * App\Http\Controllers\Relationship\TemplateController). Same validation,
 * same type/channel enums, same forType() auto-create-stub behaviour — no
 * behaviour drift between web and mobile.
 *
 *   GET    /api/v1/templates                 → list (optional type/channel filters)
 *   GET    /api/v1/templates/{id}            → single template
 *   GET    /api/v1/templates/for-type/{type} → deep-link by type (auto-creates a stub if none exists)
 *   POST   /api/v1/templates                 → create
 *   PUT    /api/v1/templates/{id}            → update
 *   DELETE /api/v1/templates/{id}            → delete
 */
class TemplateController extends ApiController
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/templates
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $type    = $request->query('type');
        $channel = $request->query('channel');

        $templates = MessageTemplate::query()
            ->when($type, fn ($q) => $q->ofType($type))
            ->when($channel, fn ($q) => $q->channel($channel))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return $this->success(
            $templates->map(fn (MessageTemplate $t) => $this->present($t))->values(),
            '',
            200,
            ['tokens' => MessageTemplate::TOKENS]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/templates/{id}
    // ─────────────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);

        return $this->success($this->present($template), '', 200, ['tokens' => MessageTemplate::TOKENS]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/templates/for-type/{type}
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Deep-link entry point used by Settings "gear" icons that only know a
     * template *type*, not an id — mirrors web TemplateController::forType().
     * If an active template of the requested type already exists, returns it;
     * otherwise creates one sane default row for that type (whatsapp channel,
     * inactive until saved) and returns that.
     */
    public function forType(Request $request, string $type): JsonResponse
    {
        if (! array_key_exists($type, $this->types())) {
            return $this->error("Unknown template type: {$type}", [], 404);
        }

        $template = MessageTemplate::query()->ofType($type)->orderByDesc('is_active')->first();

        if (! $template) {
            $template = MessageTemplate::create([
                'name'      => $this->types()[$type] . ' — default',
                'channel'   => $request->query('channel', 'whatsapp'),
                'type'      => $type,
                'body'      => '',
                'is_active' => false,
            ]);
        }

        return $this->success($this->present($template), '', 200, ['tokens' => MessageTemplate::TOKENS]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/templates
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data     = $this->validated($request);
        $template = MessageTemplate::create($data);

        return $this->success($this->present($template), 'Template created.', 201, ['tokens' => MessageTemplate::TOKENS]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUT /api/v1/templates/{id}
    // ─────────────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $template = MessageTemplate::findOrFail($id);
        $data     = $this->validated($request);
        $template->update($data);

        return $this->success($this->present($template), 'Template saved.', 200, ['tokens' => MessageTemplate::TOKENS]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/templates/{id}
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        MessageTemplate::findOrFail($id)->delete();

        return $this->success(null, 'Template removed.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function present(MessageTemplate $t): array
    {
        return [
            'id'        => $t->id,
            'name'      => $t->name,
            'channel'   => $t->channel,
            'type'      => $t->type,
            'body'      => $t->body,
            'is_active' => (bool) $t->is_active,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'      => 'required|string|max:200',
            'channel'   => 'required|in:whatsapp,sms,email',
            'type'      => 'required|in:appointment_reminder,followup,recall,birthday,custom',
            'body'      => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        // Checkbox/boolean inputs may be absent from JSON payloads when unset.
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Type enum values, matching the message_templates.type column — mirrors web TemplateController::types(). */
    private function types(): array
    {
        return [
            'appointment_reminder' => 'Appointment Reminder',
            'followup'             => 'Follow-up',
            'recall'               => 'Recall',
            'birthday'             => 'Birthday',
            'custom'               => 'Custom',
        ];
    }
}
