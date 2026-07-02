<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * InvalidTransitionException — thrown by RelationshipJourney::transition()
 * when a state transition is not allowed by the VALID_TRANSITIONS map.
 *
 * Callers (PrmController, OpportunityController, etc.) should catch this and
 * return a 422 JSON response with the exception message.
 */
class InvalidTransitionException extends RuntimeException
{
    //
}
