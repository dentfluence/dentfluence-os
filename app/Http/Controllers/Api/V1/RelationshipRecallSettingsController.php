<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\MessageTemplate;
use App\Models\TreatmentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RelationshipRecallSettingsController (API v1)
 * -----------------------------------------------
 * Mobile face of the Recall / Birthday section of the Relationship (PRE)
 * Settings page (web: App\Http\Controllers\Relationship\SettingsController —
 * index()'s Recall/Birthday slice + saveRecallGeneral()/saveTreatmentRecall()/
 * saveBirthday()). Same AppSetting keys, same TreatmentType column, same
 * validation — no behaviour drift between web and mobile. Anniversary
 * tracking does NOT exist anywhere in this codebase (rejected by the
 * client) and is intentionally absent here too.
 *
 *   GET  /api/v1/relationship/recall-settings                       → current config
 *   POST /api/v1/relationship/recall-settings/general               → save General Recall
 *   POST /api/v1/relationship/recall-settings/treatment/{treatmentType} → save one treatment's override
 *   POST /api/v1/relationship/recall-settings/birthday               → save Birthday settings
 */
class RelationshipRecallSettingsController extends ApiController
{
    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationship/recall-settings
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // General Recall — same keys as SettingsController::index().
        $recallGeneralDays = (int) AppSetting::get('recall.general_days', 180);
        $recallChannels = [
            'whatsapp' => AppSetting::get('recall.channel_whatsapp', '1') === '1',
            'sms'      => AppSetting::get('recall.channel_sms', '0') === '1',
            'email'    => AppSetting::get('recall.channel_email', '0') === '1',
        ];

        // Treatment-wise Recall.
        $recallTreatmentTypes = TreatmentType::query()->active()->get(['id', 'name', 'recall_after_days']);

        // Birthday.
        $birthdayEnabled = AppSetting::get('recall.birthday_enabled', '1') === '1';
        $birthdayWindowDays = (int) AppSetting::get(
            'recall.birthday_window_days',
            config('relationship_rules.today_actions.birthday_window_days', 1)
        );

        // "Using default vs custom message" — same lookup as SettingsController::index()
        // (most-recently-active template of that type; gear icons deep-link to
        // relationship.templates.forType, which auto-creates a stub if none exists).
        $recallTemplate   = MessageTemplate::query()->ofType('recall')->active()->first();
        $birthdayTemplate = MessageTemplate::query()->ofType('birthday')->active()->first();

        return $this->success([
            'general' => [
                'general_days' => $recallGeneralDays,
                'channels'     => $recallChannels,
            ],
            'treatment_types' => $recallTreatmentTypes->map(fn (TreatmentType $t) => [
                'id'                => $t->id,
                'name'              => $t->name,
                'recall_after_days' => $t->recall_after_days,
            ])->values(),
            'birthday' => [
                'enabled'     => $birthdayEnabled,
                'window_days' => $birthdayWindowDays,
            ],
            'templates' => [
                'recall'   => $recallTemplate ? ['id' => $recallTemplate->id, 'is_active' => $recallTemplate->is_active] : null,
                'birthday' => $birthdayTemplate ? ['id' => $birthdayTemplate->id, 'is_active' => $birthdayTemplate->is_active] : null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/recall-settings/general
    // ─────────────────────────────────────────────────────────────────────

    /** Save General Recall periodicity + per-channel enable flags — mirrors SettingsController::saveRecallGeneral(). */
    public function saveGeneral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'general_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        AppSetting::set('recall.general_days', (string) $data['general_days'], 'recall');
        AppSetting::set('recall.channel_whatsapp', $request->boolean('channel_whatsapp') ? '1' : '0', 'recall');
        AppSetting::set('recall.channel_sms', $request->boolean('channel_sms') ? '1' : '0', 'recall');
        AppSetting::set('recall.channel_email', $request->boolean('channel_email') ? '1' : '0', 'recall');

        return $this->success(null, 'General recall settings saved.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/recall-settings/treatment/{treatmentType}
    // ─────────────────────────────────────────────────────────────────────

    /** Save one treatment type's "recall after N days" override — mirrors SettingsController::saveTreatmentRecall(). */
    public function saveTreatment(Request $request, int $treatmentType): JsonResponse
    {
        $data = $request->validate([
            'recall_after_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $type = TreatmentType::findOrFail($treatmentType);
        $type->update(['recall_after_days' => $data['recall_after_days'] ?? null]);

        return $this->success(null, "Recall periodicity saved for {$type->name}.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/recall-settings/birthday
    // ─────────────────────────────────────────────────────────────────────

    /** Save Birthday reminder enable + window (days) — mirrors SettingsController::saveBirthday(). */
    public function saveBirthday(Request $request): JsonResponse
    {
        $data = $request->validate([
            'window_days' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        AppSetting::set('recall.birthday_enabled', $request->boolean('enabled') ? '1' : '0', 'recall');
        AppSetting::set('recall.birthday_window_days', (string) $data['window_days'], 'recall');

        return $this->success(null, 'Birthday reminder settings saved.');
    }
}
