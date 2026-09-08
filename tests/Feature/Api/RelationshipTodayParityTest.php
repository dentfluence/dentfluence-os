<?php

namespace Tests\Feature\Api;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-5 (Android V1.1) — GET /relationships/today returns the SAME rows the web
 * Today's Actions board shows: due-today window with done rows faded, and
 * the Settings > Today's Actions visibility rules applied.
 *
 * The engine is mocked: what it generates is TodayActionsEngine's business,
 * tested elsewhere. This pins only how the API calls it and what it strips.
 */
class RelationshipTodayParityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function rows(): array
    {
        return [
            'recall_calls' => [
                ['id' => 1, 'patient_name' => 'A', 'meta' => ['purpose' => 'recall_general']],
                ['id' => 2, 'patient_name' => 'B', 'meta' => ['purpose' => 'recall_birthday']],   // stripped by default
                ['id' => 3, 'patient_name' => 'C', 'meta' => ['purpose' => 'recall_general'], 'done' => ['outcome' => 'spoke']],
            ],
            'tasks' => [
                ['id' => 4, 'title' => 'Call lab', 'suggested_action' => 'Wish happy birthday'],   // stripped by default
                ['id' => 5, 'title' => 'Call lab'],
            ],
            'missed_calls' => [
                ['id' => 6],
            ],
        ];
    }

    public function test_default_window_is_today_with_done_rows_and_visibility_applied(): void
    {
        $this->mock(TodayActionsEngine::class, function ($m) {
            $m->shouldReceive('generate')->once()->with(true, 'today')->andReturn($this->rows());
        });
        AppSetting::set('today.show.missed_calls', '0');

        Sanctum::actingAs($this->admin(), ['*']);
        $res = $this->getJson('/api/v1/relationships/today')->assertOk();

        $data = $res->json('data');
        $this->assertArrayNotHasKey('missed_calls', $data, 'hidden category must not reach the phone');
        $this->assertSame([1, 3], array_column($data['recall_calls'], 'id'), 'birthday row stripped, done row kept');
        $this->assertSame([5], array_column($data['tasks'], 'id'));

        $res->assertJsonPath('meta.window', 'today')
            ->assertJsonPath('meta.totals.recall_calls', 1)
            ->assertJsonPath('meta.done_totals.recall_calls', 1)
            ->assertJsonPath('meta.grand_total', 2);
    }

    public function test_overdue_window_maps_to_the_pending_calls_view(): void
    {
        $this->mock(TodayActionsEngine::class, function ($m) {
            $m->shouldReceive('generate')->once()->with(false, 'overdue')->andReturn(['recall_calls' => []]);
        });

        Sanctum::actingAs($this->admin(), ['*']);
        $this->getJson('/api/v1/relationships/today?window=overdue')->assertOk()
            ->assertJsonPath('meta.window', 'overdue');
    }

    public function test_an_unknown_window_falls_back_to_today(): void
    {
        $this->mock(TodayActionsEngine::class, function ($m) {
            $m->shouldReceive('generate')->once()->with(true, 'today')->andReturn([]);
        });

        Sanctum::actingAs($this->admin(), ['*']);
        $this->getJson('/api/v1/relationships/today?window=everything')->assertOk()
            ->assertJsonPath('meta.window', 'today');
    }
}
