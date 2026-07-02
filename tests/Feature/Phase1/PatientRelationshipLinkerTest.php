<?php

namespace Tests\Feature\Phase1;

use App\Domain\Events\DomainEventBus;
use App\Models\Patient;
use App\Services\Relationship\PatientRelationshipLinker;
use App\Services\Relationship\RelationshipEngine;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream A — the linker's FLAG GATE.
 *
 * This is also the characterization guarantee for Phase 1 Sprint 1: with the
 * flag OFF (default), patient creation does NOT touch the Relationship Engine.
 * A fake engine records whether it was called, so no DB/patient columns are
 * needed.
 */
class PatientRelationshipLinkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_flag_off_is_a_noop(): void
    {
        $engine = new SpyRelationshipEngine();
        $linker = new PatientRelationshipLinker($engine, app(DomainEventBus::class));

        $linker->link(new Patient()); // unsaved stub

        $this->assertFalse($engine->linkPatientCalled, 'Engine must NOT be called when flag is off.');
    }

    public function test_flag_on_invokes_the_engine(): void
    {
        // Flip the flag via the real override API. NOTE: flag keys contain dots
        // (e.g. 'identity.link_patient'), so config()->set() cannot target them
        // (it would treat the dots as nested keys). Feature::set writes the flat
        // override key correctly.
        Feature::set('identity.link_patient', true);

        $engine = new SpyRelationshipEngine();
        $linker = new PatientRelationshipLinker($engine, app(DomainEventBus::class));

        $linker->link(new Patient());

        $this->assertTrue($engine->linkPatientCalled, 'Engine must be called when flag is on.');
    }
}

/**
 * Test double: records the call without any DB work.
 */
class SpyRelationshipEngine extends RelationshipEngine
{
    public bool $linkPatientCalled = false;

    public function linkPatient(Patient $patient): void
    {
        $this->linkPatientCalled = true;
    }
}
