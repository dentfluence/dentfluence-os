<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Full CRUD editor for MessageTemplate — the generic, reusable template
 * module any PRE feature (Recall, Birthday, ...) can point at.
 *
 * Moved here from Communication\TemplateController on 2026-07-06 — Sumit
 * decided Templates conceptually belongs to the Relationship/PRE module
 * (it's Recall/Birthday copy, all PRE concerns), not Communication
 * OS. The Communication-side controller/views were archived, not deleted, at
 * under_review/pre_consolidation_2026_07_06/. Behaviour here is identical to
 * that original — only the route names/namespace/views changed.
 *
 * This is deliberately separate from Settings\MastersController::
 * storeMessageTemplate()/destroyMessageTemplate(), which power a compact
 * "quick add" widget inside the (global) Settings → Growth & Comms tab. That
 * quick-add form/route is left untouched (still name+type+body only, no
 * channel/is_active fields). This controller is the fuller, standalone editor
 * surface (channel + type + active toggle + token picker) reached via
 * relationship.templates.*.
 *
 * Deliberately NOT a tab in the PRE tab-strip (resources/views/relationship/
 * layouts/app.blade.php's $relTabs) — reached only via gear-icon deep links
 * from Settings, to avoid tab-strip bloat.
 */
class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->query('type');

        $templates = MessageTemplate::query()
            ->when($type, fn ($q) => $q->ofType($type))
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return view('relationship.templates.index', [
            'pageTitle'  => 'Templates',
            'templates'  => $templates,
            'typeFilter' => $type,
            'types'      => $this->types(),
        ]);
    }

    public function create(): View
    {
        return view('relationship.templates.editor', [
            'pageTitle' => 'New Template',
            'template'  => new MessageTemplate(['channel' => 'whatsapp', 'type' => 'custom', 'is_active' => true]),
            'channels'  => $this->channels(),
            'types'     => $this->types(),
            'tokens'    => MessageTemplate::TOKENS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $template = MessageTemplate::create($data);

        return redirect()
            ->route('relationship.templates.edit', $template->id)
            ->with('success', 'Template created.');
    }

    public function edit(int $id): View
    {
        $template = MessageTemplate::findOrFail($id);

        return view('relationship.templates.editor', [
            'pageTitle' => 'Edit Template',
            'template'  => $template,
            'channels'  => $this->channels(),
            'types'     => $this->types(),
            'tokens'    => MessageTemplate::TOKENS,
        ]);
    }

    /**
     * Deep-link entry point used by Settings "gear" icons (Recall/Birthday
     * Settings) that only know a template *type*, not an id —
     * they don't want to force staff through the index/create flow, and
     * they don't want to create N templates per feature either. If an
     * active template of the requested type already exists, jump straight
     * to editing it; otherwise create one sane default row for that type
     * (whatsapp channel, inactive until saved) and open it for editing.
     *
     * Route: GET /relationship/templates/for-type/{type} [relationship.templates.forType]
     */
    public function forType(Request $request, string $type): RedirectResponse
    {
        if (!array_key_exists($type, $this->types())) {
            abort(404, "Unknown template type: {$type}");
        }

        $template = MessageTemplate::query()->ofType($type)->orderByDesc('is_active')->first();

        if (!$template) {
            $template = MessageTemplate::create([
                'name'      => $this->types()[$type] . ' — default',
                'channel'   => $request->query('channel', 'whatsapp'),
                'type'      => $type,
                'body'      => '',
                'is_active' => false,
            ]);
        }

        return redirect()->route('relationship.templates.edit', $template->id);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $template = MessageTemplate::findOrFail($id);
        $data = $this->validated($request);
        $template->update($data);

        return redirect()
            ->route('relationship.templates.edit', $template->id)
            ->with('success', 'Template saved.');
    }

    public function destroy(int $id): RedirectResponse
    {
        MessageTemplate::findOrFail($id)->delete();

        return redirect()
            ->route('relationship.templates.index')
            ->with('success', 'Template removed.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'      => 'required|string|max:200',
            'channel'   => 'required|in:whatsapp,sms,email',
            'type'      => 'required|in:appointment_reminder,followup,recall,birthday,custom',
            'body'      => 'required|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        // Checkbox inputs are absent from the payload when unchecked.
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Channel enum values, matching the message_templates.channel column. */
    private function channels(): array
    {
        return ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email'];
    }

    /** Type enum values, matching the message_templates.type column. */
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
