<?php

namespace Tests\Feature\ClinicalLibrary;

use App\Models\TreatmentPlanItem;
use App\Services\ClinicalLibrary\ClinicalLibrarySearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ClinicalLibrary\Concerns\BuildsClinicalFiles;
use Tests\TestCase;

/**
 * "Find me that file" — the one place that answers it.
 *
 * One service backs both the dashboard drawer and the Content Manager filter
 * bar, because those two surfaces used to carry their own query logic and
 * disagreed: the Content Manager matched a hardcoded specialty list against
 * free-text `procedure` while the page beside it filtered `treatment_category`.
 *
 * Two behaviours below are regressions of bugs found on 12 Sep and are the
 * reason this file exists at all:
 *   · a lab case files ONE row for the whole case, so tooth_number reads
 *     "24, 25" — an exact match finds neither tooth;
 *   · setting a filter used to widen the result instead of narrowing it, which
 *     is the opposite of what setting a filter means.
 */
class ClinicalLibrarySearchTest extends TestCase
{
    use RefreshDatabase;
    use BuildsClinicalFiles;

    private ClinicalLibrarySearchService $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = app(ClinicalLibrarySearchService::class);
    }

    /** @return array<int,int> ids the search returned */
    private function ids(array $input): array
    {
        return $this->search->search($input)->pluck('id')->sort()->values()->all();
    }

    // ── Reading the box ───────────────────────────────────────────────────────

    /**
     * A dentist types "26" and means a tooth, not a quantity. Nothing else in
     * the box is a bare two-digit FDI code, so nothing else can be mistaken for
     * one — which is why the number needs no prefix or dropdown.
     */
    public function test_a_bare_fdi_number_is_read_as_a_tooth(): void
    {
        $this->assertSame(['26'], $this->search->parse('26')['tooth']);
        $this->assertSame(['51'], $this->search->parse('51')['tooth'], 'deciduous codes too — Tulip Kids uses the same library');
        $this->assertSame([], $this->search->parse('99')['tooth'], '99 is not a tooth');
        $this->assertSame(['99'], $this->search->parse('99')['text'], 'and so it stays a word to match');
    }

    /** One line of plain text, split into the dimensions it actually names. */
    public function test_a_sentence_is_split_into_the_dimensions_it_names(): void
    {
        $parsed = $this->search->parse('Sharma implant after');

        $this->assertSame(['implant'], $parsed['treatment_category']);
        $this->assertSame(['after'], $parsed['stage']);
        $this->assertSame(['Sharma'], $parsed['text'], 'the leftover word is the patient, and stays as typed');
    }

    public function test_the_words_clinicians_actually_type_are_understood(): void
    {
        $this->assertSame(['after'], $this->search->parse('post-op')['stage']);
        $this->assertSame(['before'], $this->search->parse('preop')['stage']);
        $this->assertSame(['xray'], $this->search->parse('iopa')['file_type']);
        $this->assertSame(['opg'], $this->search->parse('panoramic')['file_type']);
        $this->assertSame(['aligner'], $this->search->parse('aligners')['treatment_category'], 'the plural people actually type');
        $this->assertSame(
            [TreatmentPlanItem::PROGRESS_COMPLETED],
            $this->search->parse('done')['status']
        );
    }

    /** A search that silently reinterprets what you typed is worse than one that asks. */
    public function test_everything_it_decided_comes_back_as_a_chip(): void
    {
        $chips = collect($this->search->interpretation('26 implant after sharma'));

        $this->assertSame('Tooth 26', $chips->firstWhere('type', 'tooth')['label']);
        $this->assertSame('Implant', $chips->firstWhere('type', 'treatment')['label']);
        $this->assertSame('After', $chips->firstWhere('type', 'stage')['label']);
        $this->assertSame('"sharma"', $chips->firstWhere('type', 'text')['label']);
    }

    // ── The lab case bug ──────────────────────────────────────────────────────

    /**
     * THE 12 SEP BUG. A lab case attaches one file for the whole case and
     * stamps every tooth it covers into one column: "24, 25". An exact match
     * finds neither, and a LIKE '%25%' would also return tooth 125 and any file
     * captured in 2025. FIND_IN_SET against the de-spaced value is the only
     * thing that matches a single tooth and a list identically.
     */
    public function test_a_multi_tooth_lab_row_is_found_by_each_tooth_it_names(): void
    {
        $patient = $this->makePatient();
        $bridge  = $this->makeFile($patient, ['tooth_number' => '24, 25', 'procedure' => 'Crown & Bridge']);
        $single  = $this->makeFile($patient, ['tooth_number' => '36']);

        $this->assertSame([$bridge->id], $this->ids(['q' => '24']));
        $this->assertSame([$bridge->id], $this->ids(['q' => '25']), 'the second tooth in the list is not a lesser tooth');
        $this->assertSame([$single->id], $this->ids(['q' => '36']));
        $this->assertSame([], $this->ids(['q' => '45']), 'and a tooth nobody filed returns nothing, not everything');
    }

    public function test_a_tooth_search_does_not_match_a_number_that_merely_contains_it(): void
    {
        $patient = $this->makePatient();
        $this->makeFile($patient, ['tooth_number' => '15']);

        $this->assertSame([], $this->ids(['tooth' => ['51']]), '15 and 51 are different teeth in different jaws');
    }

    // ── Arches and quadrants ──────────────────────────────────────────────────

    /**
     * A dentist thinks "upper arch", not "11,12,13,…". A full-arch case is
     * filed tooth by tooth, so the filter has to speak both.
     */
    public function test_an_arch_expands_to_its_own_teeth_and_no_others(): void
    {
        $patient    = $this->makePatient();
        $upper      = $this->makeFile($patient, ['tooth_number' => '11']);
        $upperDecid = $this->makeFile($patient, ['tooth_number' => '65']);
        $lower      = $this->makeFile($patient, ['tooth_number' => '36']);

        $maxillary = $this->ids(['tooth' => ['maxillary']]);

        $this->assertContains($upper->id, $maxillary);
        $this->assertContains($upperDecid->id, $maxillary, 'deciduous teeth are in the arch too, or every paediatric file hides');
        $this->assertNotContains($lower->id, $maxillary);

        $this->assertSame([$lower->id], $this->ids(['tooth' => ['q3']]), 'lower left is a quadrant, not half the mouth');
        $this->assertCount(3, $this->ids(['tooth' => ['full_mouth']]));
    }

    public function test_the_tooth_dropdown_offers_the_same_teeth_to_both_screens(): void
    {
        $options = ClinicalLibrarySearchService::toothOptions();

        $this->assertArrayHasKey('full_mouth', $options['regions']);
        $this->assertArrayHasKey('maxillary', $options['regions']);
        $this->assertContains(18, $options['quadrants']['Upper right']);
        $this->assertContains(48, $options['quadrants']['Lower right']);
        $this->assertContains(75, $options['quadrants']['Lower left'], 'deciduous codes belong on the chart');
    }

    // ── Filters must narrow ───────────────────────────────────────────────────

    /**
     * THE OTHER 12 SEP BUG. Type "after", then set the Stage filter to Before.
     * Merging the two returns both — which is the opposite of what setting a
     * filter means. Every filter is optional; every filter that IS set must
     * only ever reduce the result.
     */
    public function test_an_explicit_filter_beats_a_word_the_box_guessed(): void
    {
        $patient = $this->makePatient();
        $before  = $this->makeFile($patient, ['stage' => 'before']);
        $after   = $this->makeFile($patient, ['stage' => 'after']);

        $this->assertSame([$after->id],  $this->ids(['q' => 'after']));
        $this->assertSame([$before->id], $this->ids(['q' => 'after', 'stage' => ['before']]), 'the chip the user set wins');
        $this->assertNotContains($after->id, $this->ids(['q' => 'after', 'stage' => ['before']]));
    }

    public function test_two_filters_narrow_instead_of_widening(): void
    {
        $patient  = $this->makePatient();
        $wanted   = $this->makeFile($patient, ['tooth_number' => '26', 'stage' => 'before']);
        $sameTooth = $this->makeFile($patient, ['tooth_number' => '26', 'stage' => 'after']);
        $sameStg  = $this->makeFile($patient, ['tooth_number' => '36', 'stage' => 'before']);

        $result = $this->ids(['tooth' => ['26'], 'stage' => ['before']]);

        $this->assertSame([$wanted->id], $result);
        $this->assertNotContains($sameTooth->id, $result);
        $this->assertNotContains($sameStg->id, $result);
    }

    /** "sharma implant" must not return every Sharma, and every implant. */
    public function test_leftover_words_are_anded_not_ored(): void
    {
        $sharma = $this->makePatient(['name' => 'Anita Sharma']);
        $patel  = $this->makePatient(['name' => 'Rahul Patel']);

        $wanted = $this->makeFile($sharma, ['procedure' => 'Implant placement', 'treatment_category' => null]);
        $other  = $this->makeFile($sharma, ['procedure' => 'Scaling', 'treatment_category' => null]);
        $third  = $this->makeFile($patel,  ['procedure' => 'Implant placement', 'treatment_category' => null]);

        // "placement" is not a category, a stage or a file type, so it stays a
        // word — which is the case worth testing: two free words, both required.
        $result = $this->ids(['q' => 'sharma placement']);

        $this->assertNotContains($other->id, $result, 'a Sharma with no implant is not a match');
        $this->assertNotContains($third->id, $result, 'an implant on someone else is not a match either');
        $this->assertSame([$wanted->id], $result);
    }

    public function test_a_patient_can_be_found_by_name_or_by_phone(): void
    {
        $patient = $this->makePatient(['name' => 'Anita Sharma', 'phone' => '9822011223']);
        $file    = $this->makeFile($patient);
        $this->makeFile($this->makePatient(['name' => 'Rahul Patel']));

        $this->assertSame([$file->id], $this->ids(['q' => 'sharma']));
        $this->assertSame([$file->id], $this->ids(['q' => '9822011223']), 'reception searches by phone, not by spelling');
    }

    // ── Treatment status ──────────────────────────────────────────────────────

    /**
     * Status is not a property of the file — it belongs to the plan item the
     * file is attached to. A file with no plan item therefore has no status and
     * is correctly excluded when one is asked for, rather than silently swept in.
     */
    public function test_treatment_status_comes_from_the_plan_item_behind_the_file(): void
    {
        $patient   = $this->makePatient();
        $pendingIt = $this->makePlanItem($patient, TreatmentPlanItem::PROGRESS_PENDING);
        $doneItem  = $this->makePlanItem($patient, TreatmentPlanItem::PROGRESS_COMPLETED);

        $pending  = $this->makeFile($patient, ['treatment_plan_item_id' => $pendingIt->id]);
        $done     = $this->makeFile($patient, ['treatment_plan_item_id' => $doneItem->id]);
        $unlinked = $this->makeFile($patient);

        $this->assertSame([$pending->id], $this->ids(['q' => 'pending']));
        $this->assertSame([$done->id],    $this->ids(['q' => 'completed']));
        $this->assertNotContains($unlinked->id, $this->ids(['q' => 'pending']), 'no plan item means no status, not "pending"');
    }

    // ── Lanes ─────────────────────────────────────────────────────────────────

    public function test_a_lane_filter_returns_only_files_flagged_for_that_lane(): void
    {
        $patient   = $this->makePatient();
        $teaching  = $this->makeFile($patient, ['is_teaching_eligible' => true]);
        $marketing = $this->makeFile($patient, ['is_marketing_eligible' => true]);

        $this->assertSame([$teaching->id],  $this->ids(['eligible_teaching' => 1]));
        $this->assertSame([$marketing->id], $this->ids(['eligible_marketing' => 1]));
    }

    /** A deleted file is gone from every surface, not just the one it was deleted from. */
    public function test_a_soft_deleted_file_is_invisible_to_search(): void
    {
        $patient = $this->makePatient();
        $kept    = $this->makeFile($patient, ['tooth_number' => '26']);
        $removed = $this->makeFile($patient, ['tooth_number' => '26']);

        $removed->delete();

        $this->assertSame([$kept->id], $this->ids(['q' => '26']));
    }
}
