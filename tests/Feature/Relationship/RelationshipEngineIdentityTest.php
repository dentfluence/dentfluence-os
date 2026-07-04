<?php

namespace Tests\Feature\Relationship;

use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Services\Relationship\RelationshipEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patients are matched by clinic ID, never by phone/email alone — a
 * household commonly shares one registered contact number, so two genuinely
 * distinct patients must never end up on the same Relationship. Only a
 * still-unclaimed Relationship (a Lead's inquiry with no patient yet) may be
 * reused, which preserves the Lead -> Patient conversion journey.
 */
class RelationshipEngineIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function patient(string $name, string $phone): Patient
    {
        return Patient::create(['name' => $name, 'phone' => $phone]);
    }

    private function lead(string $name, string $phone): Lead
    {
        return Lead::withoutEvents(fn () => Lead::create(['name' => $name, 'phone' => $phone]));
    }

    public function test_two_patients_sharing_a_phone_get_separate_relationships(): void
    {
        $dad = $this->patient('Dad Sharma', '9990001111');
        $kid = $this->patient('Kid Sharma', '9990001111');

        $engine = app(RelationshipEngine::class);
        $engine->linkPatient($dad);
        $engine->linkPatient($kid);

        $dad->refresh();
        $kid->refresh();

        $this->assertNotNull($dad->relationship_id);
        $this->assertNotNull($kid->relationship_id);
        $this->assertNotSame($dad->relationship_id, $kid->relationship_id, 'Two patients must never share a Relationship, even with the same phone.');
        $this->assertSame(2, Relationship::count());
    }

    public function test_patient_reuses_an_unclaimed_relationship_from_a_lead(): void
    {
        $lead = $this->lead('Priya', '9990002222');
        $engine = app(RelationshipEngine::class);
        $engine->linkLead($lead);

        $patient = $this->patient('Priya', '9990002222');
        $engine->linkPatient($patient);

        $lead->refresh();
        $patient->refresh();

        $this->assertSame($lead->relationship_id, $patient->relationship_id, 'Converting a Lead to a Patient must keep the same Relationship (preserves inquiry history).');
        $this->assertSame(1, Relationship::count());
    }

    public function test_patient_does_not_reuse_a_relationship_already_claimed_by_another_patient(): void
    {
        $first = $this->patient('First Patient', '9990003333');
        $engine = app(RelationshipEngine::class);
        $engine->linkPatient($first);

        $second = $this->patient('Second Patient', '9990003333');
        $engine->linkPatient($second);

        $first->refresh();
        $second->refresh();

        $this->assertNotSame($first->relationship_id, $second->relationship_id);
        $this->assertSame(2, Relationship::count());
    }
}
