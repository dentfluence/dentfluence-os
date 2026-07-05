<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;

/**
 * SUPERSEDED 2026-07-06 — Recall/Birthday/Anniversary Settings moved to the
 * Relationship/PRE module (App\Http\Controllers\Relationship\
 * SettingsController::saveRecallGeneral/saveTreatmentRecall/saveBirthday/
 * saveAnniversary, folded into the relationship.settings page). Sumit's call:
 * these settings are PRE concerns, not Communication OS.
 *
 * Original implementation archived at
 * under_review/pre_consolidation_2026_07_06/RecallSettingsController.php.
 *
 * No routes point at this class anymore — routes/communication.php now
 * redirects communication.recall-settings.* straight to relationship.settings
 * (and to relationship.settings.recall-general/.recall-treatment/
 * .recall-birthday/.recall-anniversary for the POST actions).
 */
class RecallSettingsController extends Controller
{
    //
}
