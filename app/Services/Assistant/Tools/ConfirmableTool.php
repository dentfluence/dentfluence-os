<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;

/**
 * ConfirmableTool — a write tool whose action must be confirmed before it runs
 * (used for clinical/financial categories). Adds preview(): a human-readable
 * description of what WILL happen, shown on the confirm card before execution.
 */
interface ConfirmableTool extends AssistantTool
{
    /** One-line description of the pending action, e.g. "Add a clinical note to Runali Kadam". */
    public function preview(array $args, User $user): string;
}
