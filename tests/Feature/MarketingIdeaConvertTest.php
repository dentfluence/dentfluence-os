<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Marketing\Idea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Marketing module — Idea Bank → convert idea to Campaign (automation chain)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  1. Saving a marketing idea creates it in the Idea Bank.
 *  2. Converting that idea to a campaign must, in one action:
 *       - create a draft Campaign carrying the idea's title, and
 *       - mark the original idea as "converted" (so it can't be re-used by
 *         mistake and the link back to the campaign is recorded).
 *  This is a one-act-fires-another chain — exactly the kind that breaks silently.
 */
class MarketingIdeaConvertTest extends TestCase
{
    use RefreshDatabase;

    public function test_converting_an_idea_creates_a_campaign_and_marks_it_converted(): void
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\CheckModulePermission::class,
            \App\Http\Middleware\EnsureMarketingActive::class,
        ]);

        $user = User::factory()->create(['branch_id' => 1]);
        $title = 'DuskIdea' . now()->format('His');

        // 1. Save an idea to the Idea Bank
        $resp = $this->actingAs($user)->post(route('marketing.ideas.store'), [
            'title'        => $title,
            'content_type' => 'post',
        ]);
        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mkt_ideas', ['title' => $title, 'status' => 'idea']);

        $idea = Idea::where('title', $title)->firstOrFail();

        // 2. Convert it to a campaign
        $resp2 = $this->actingAs($user)->post(route('marketing.ideas.convert-campaign', $idea));
        $resp2->assertSessionHasNoErrors();

        // A draft campaign was created from the idea
        $this->assertDatabaseHas('mkt_campaigns', [
            'name'   => $title,
            'status' => 'draft',
        ]);

        // The idea is now flagged as converted and linked to the campaign
        $this->assertDatabaseHas('mkt_ideas', [
            'id'           => $idea->id,
            'status'       => 'converted',
            'converted_to' => 'campaign',
        ]);
    }
}
