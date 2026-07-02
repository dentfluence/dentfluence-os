<?php

namespace App\Services\Relationship;

/**
 * GuardDecision — structured result of a CommunicationGuard evaluation.
 *
 * Phase 0 foundation. Lets the Guard return WHY a message was allowed or
 * blocked (for the Decision Log and future explainability) instead of a bare
 * boolean. The legacy canContact() still returns a bool by reading ->allowed().
 */
final class GuardDecision
{
    /**
     * @param array<string,mixed> $factors  Which checks ran and their outcome.
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
        public readonly array $factors = [],
    ) {
    }

    public static function allow(array $factors = []): self
    {
        return new self(true, null, $factors);
    }

    public static function block(string $reason, array $factors = []): self
    {
        return new self(false, $reason, $factors);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason'  => $this->reason,
            'factors' => $this->factors,
        ];
    }
}
