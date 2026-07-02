<?php

namespace Tests\Feature\Relationship;

use App\Models\User;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream F (slice F3) — PRM becomes secondary.
 *
 * Behind the `prm.secondary` flag the legacy PRM entry points redirect to the
 * PRE lead pipeline, but PRM stays fully reachable via ?legacy=1. Default off =
 * PRM primary, unchanged.
 */
class PrmSecondaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // legacy PRM views use @vite; stub it so they render in tests
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    public function test_prm_board_renders_normally_when_flag_off(): void
    {
        $response = $this->actingAs($this->admin())->get(route('prm.board'));

        $response->assertOk(); // default: PRM primary, no redirect
    }

    public function test_prm_board_redirects_to_pre_when_secondary(): void
    {
        Feature::set('prm.secondary', true);

        $response = $this->actingAs($this->admin())->get(route('prm.board'));

        $response->assertRedirect(route('relationship.pipeline'));
    }

    public function test_prm_board_stays_reachable_with_legacy_param(): void
    {
        Feature::set('prm.secondary', true);

        $response = $this->actingAs($this->admin())->get(route('prm.board', ['legacy' => 1]));

        $response->assertOk(); // still reachable — no redirect
    }

    public function test_pipeline_shows_legacy_link_only_when_secondary(): void
    {
        $user = $this->admin();

        // Off by default — no legacy link.
        $this->actingAs($user)->get(route('relationship.pipeline'))
            ->assertOk()
            ->assertDontSee('Legacy PRM board');

        // On — the link back to the legacy board appears.
        Feature::set('prm.secondary', true);
        $this->actingAs($user)->get(route('relationship.pipeline'))
            ->assertOk()
            ->assertSee('Legacy PRM board');
    }
}
