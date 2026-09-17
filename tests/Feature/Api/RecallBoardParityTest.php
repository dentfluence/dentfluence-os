<?php

namespace Tests\Feature\Api;

use App\Models\CommunicationQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-13 (Android V1.1) — GET /relationships/pipelines/recalls returns the
 * SAME rows, in the SAME order, with the SAME strip numbers as the web
 * recall board (RecallPipelineController::index). Each case sits on one
 * way the two could drift apart again.
 */
class RecallBoardParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function recall(array $attrs): CommunicationQueue
    {
        return CommunicationQueue::create(array_merge([
            'person_name'   => 'Recall Person',
            'phone'         => '9200000000',
            'channel'       => 'call',
            'comm_type'     => 'existing_patient',
            'direction'     => 'inbound',
            'purpose'       => 'recall_no_visit',
            'source_engine' => 'recall',
            'status'        => 'pending',
            'priority'      => 'medium',
        ], $attrs));
    }

    private function pending(array $data): array
    {
        return collect($data)->firstWhere('key', 'pending');
    }

    public function test_ignored_rows_are_hidden_by_default_and_shown_on_request(): void
    {
        $this->recall(['person_name' => 'Visible']);
        $this->recall(['person_name' => 'Hidden', 'ignored_at' => now()]);

        Sanctum::actingAs($this->admin(), ['*']);

        $res = $this->getJson('/api/v1/relationships/pipelines/recalls')->assertOk()->json();
        $this->assertSame(['Visible'], array_column($this->pending($res['data'])['items'], 'person_name'));
        $this->assertSame(2, $res['meta']['total'], 'the strip counts the base set, as the web does');
        $this->assertSame(1, $res['meta']['types'][0]['count'], 'a type chip honours the ignored rule');
        $this->assertFalse($res['meta']['show_ignored']);

        $shown = $this->getJson('/api/v1/relationships/pipelines/recalls?show_ignored=1')->assertOk()->json();
        $items = $this->pending($shown['data'])['items'];
        $this->assertEqualsCanonicalizing(['Visible', 'Hidden'], array_column($items, 'person_name'));
        $hidden = collect($items)->firstWhere('person_name', 'Hidden');
        $this->assertTrue($hidden['is_ignored']);
    }

    public function test_rows_inside_a_group_follow_the_web_order(): void
    {
        $this->recall(['person_name' => 'Low early',   'priority' => 'low',    'follow_up_date' => '2026-01-01']);
        $this->recall(['person_name' => 'High late',   'priority' => 'high',   'follow_up_date' => '2026-03-01']);
        $this->recall(['person_name' => 'High early',  'priority' => 'high',   'follow_up_date' => '2026-02-01']);
        $this->recall(['person_name' => 'Medium none', 'priority' => 'medium']);

        Sanctum::actingAs($this->admin(), ['*']);
        $data = $this->getJson('/api/v1/relationships/pipelines/recalls')->assertOk()->json('data');

        $this->assertSame(
            ['High early', 'High late', 'Medium none', 'Low early'],
            array_column($this->pending($data)['items'], 'person_name'),
            'High → Medium → Low, then earliest follow-up first — the web list order, not follow-up-date order'
        );
    }

    public function test_type_chips_carry_the_web_labels_and_filter_the_rows(): void
    {
        $this->recall(['person_name' => 'A', 'purpose' => 'recall_post_op']);
        $this->recall(['person_name' => 'B', 'purpose' => 'recall_post_op']);
        $this->recall(['person_name' => 'C', 'purpose' => 'recall_birthday']);
        $this->recall(['person_name' => 'Not a recall', 'purpose' => 'appointment', 'source_engine' => 'manual']);

        Sanctum::actingAs($this->admin(), ['*']);
        $meta = $this->getJson('/api/v1/relationships/pipelines/recalls')->assertOk()->json('meta');

        $this->assertSame(
            [
                ['key' => 'recall_post_op',  'label' => 'Post-Op Follow-up', 'count' => 2],
                ['key' => 'recall_birthday', 'label' => 'Birthday Recall',   'count' => 1],
            ],
            $meta['types'],
            'busiest first, labels from CommunicationQueue::RECALL_TYPE_LABELS'
        );
        $this->assertSame(3, $meta['total'], 'a non-recall queue row never reaches the recall board');

        $filtered = $this->getJson('/api/v1/relationships/pipelines/recalls?type=recall_birthday')->assertOk()->json('data');
        $this->assertSame(['C'], array_column($this->pending($filtered)['items'], 'person_name'));
        $this->assertSame('Birthday Recall', $this->pending($filtered)['items'][0]['type_label']);
    }

    public function test_strip_numbers_match_the_web_definitions(): void
    {
        $this->recall(['person_name' => 'open']);
        $this->recall(['person_name' => 'overdue flag',   'is_overdue' => true]);
        $this->recall(['person_name' => 'overdue status', 'status' => 'overdue']);
        $this->recall(['person_name' => 'closed now',     'status' => 'closed', 'is_overdue' => true]);
        $old = $this->recall(['person_name' => 'closed long ago', 'status' => 'closed']);
        CommunicationQueue::where('id', $old->id)->update(['updated_at' => now()->subMonths(2)]);

        Sanctum::actingAs($this->admin(), ['*']);
        $meta = $this->getJson('/api/v1/relationships/pipelines/recalls')->assertOk()->json('meta');

        $this->assertSame(5, $meta['total']);
        $this->assertSame(3, $meta['open_count']);
        $this->assertSame(2, $meta['overdue_count'], 'a closed row never counts as overdue, whatever its flag says');
        $this->assertSame(1, $meta['closed_this_month']);
    }
}
