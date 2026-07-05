<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;

/**
 * SUPERSEDED 2026-07-06 — Message Templates moved to the Relationship/PRE
 * module (App\Http\Controllers\Relationship\TemplateController, same
 * behaviour, new namespace/routes/views). Sumit's call: Templates is a PRE
 * concern (Recall/Birthday/Anniversary copy), not Communication OS.
 *
 * Original implementation archived at
 * under_review/pre_consolidation_2026_07_06/TemplateController.php.
 *
 * No routes point at this class anymore — routes/communication.php now
 * redirects communication.templates.* straight to relationship.templates.*.
 */
class TemplateController extends Controller
{
    //
}
