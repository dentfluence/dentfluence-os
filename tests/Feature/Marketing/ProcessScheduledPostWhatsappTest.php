<?php

namespace Tests\Feature\Marketing;

use App\Jobs\Marketing\ProcessScheduledPost;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PostSchedule;
use App\Models\Marketing\PostVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 — Marketing WhatsApp silent no-op bug.
 *
 * Before this fix, a WhatsApp variant (selectable in the compose form —
 * PublishController validates 'whatsapp' as an allowed platform) fell
 * through ProcessScheduledPost::dispatchToPlatform()'s generic
 * "no connection" / "platform_not_implemented" branches, both of which
 * return success:true. The post would show as "published" on WhatsApp
 * while nothing was ever sent — the real WhatsApp Business API isn't
 * configured. Now it fails honestly with a clear reason.
 *
 * Also covers a second bug found while fixing the first: the per-variant
 * result used to be written to a 'meta' key, which isn't a real column on
 * mkt_post_variants (the column is platform_specific_meta) — Eloquent
 * silently dropped it, so external_id/publish_error were never actually
 * saved anywhere the UI could show them.
 */
class ProcessScheduledPostWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function scheduledWhatsappPost(): PostSchedule
    {
        $user = $this->user();

        $post = MarketingPost::create([
            'clinic_id'    => 1,
            'caption'      => 'Test WhatsApp broadcast',
            'content_type' => 'post',
            'platforms'    => ['whatsapp'],
            'status'       => 'scheduled',
            'assignee_id'  => $user->id,
            'created_by'   => $user->id,
            'updated_by'   => $user->id,
        ]);

        PostVariant::create([
            'post_id'    => $post->id,
            'platform'   => 'whatsapp',
            'status'     => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return PostSchedule::create([
            'post_id'      => $post->id,
            'scheduled_at' => now(),
            'status'       => 'pending',
            'created_by'   => $user->id,
            'updated_by'   => $user->id,
        ]);
    }

    public function test_whatsapp_variant_fails_honestly_instead_of_silently_succeeding(): void
    {
        $schedule = $this->scheduledWhatsappPost();

        (new ProcessScheduledPost($schedule->id))->handle();

        $variant = PostVariant::where('post_id', $schedule->post_id)->where('platform', 'whatsapp')->first();

        $this->assertSame('failed', $variant->status,
            'A WhatsApp variant must be marked failed, not silently published, since nothing was actually sent.');
        $this->assertNull($variant->published_at);
        $this->assertNotNull($variant->publish_error, 'The failure reason must actually be saved, not silently dropped.');
        $this->assertStringContainsString('WhatsApp', $variant->publish_error);
    }

    public function test_publish_result_is_saved_to_real_columns_not_the_nonexistent_meta_key(): void
    {
        $schedule = $this->scheduledWhatsappPost();

        (new ProcessScheduledPost($schedule->id))->handle();

        $variant = PostVariant::where('post_id', $schedule->post_id)->where('platform', 'whatsapp')->first();

        // platform_specific_meta is the real column (was previously written to
        // a non-existent 'meta' key and silently dropped by Eloquent).
        $this->assertIsArray($variant->platform_specific_meta);
        $this->assertArrayHasKey('publish_result', $variant->platform_specific_meta);
    }
}
