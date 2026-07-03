<?php

namespace Tests\Feature\Relationship;

use App\Models\AppNotification;
use App\Models\Relationship;
use App\Models\RelationshipNotification;
use App\Models\User;
use App\Services\Relationship\NotificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — Notification store decision.
 *
 * app_notifications is THE canonical single store (topbar bell reads it).
 * relationship_notifications stays as an internal metadata/dedup table, not
 * a second user-facing store — its write is isolated (own try/catch) so a
 * problem there can never look like the user's actual notification failed.
 */
class NotificationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function frontDeskUser(): User
    {
        return User::factory()->create(['role' => 'front_desk', 'branch_id' => 1, 'is_active' => true]);
    }

    public function test_notify_writes_both_the_canonical_and_the_metadata_table(): void
    {
        $user = $this->frontDeskUser();

        app(NotificationEngine::class)->notify(
            type:           'recall_due',
            relationshipId: null,
            recipients:     [$user->id],
            title:          'Recall due',
            body:           'Patient recall is due today.',
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'title'   => 'Recall due',
        ]);
        $this->assertDatabaseHas('relationship_notifications', [
            'recipient_id' => $user->id,
            'type'         => 'recall_due',
            'title'        => 'Recall due',
        ]);
    }

    public function test_duplicate_notification_within_24h_is_suppressed(): void
    {
        $user = $this->frontDeskUser();
        $engine = app(NotificationEngine::class);

        // Must be a REAL relationship — relationship_notifications.relationship_id
        // is a foreign key, so a made-up id would silently fail that insert
        // (caught by the fault-isolation try/catch) and the dedup check would
        // never find anything to suppress against.
        $relationship = Relationship::create(['name' => 'Dedup Test', 'phone' => '900' . random_int(1000, 9999)]);

        $engine->notify(type: 'recall_due', relationshipId: $relationship->id, recipients: [$user->id], title: 'First');
        $engine->notify(type: 'recall_due', relationshipId: $relationship->id, recipients: [$user->id], title: 'Second — should be suppressed');

        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());
        $this->assertSame(1, RelationshipNotification::where('recipient_id', $user->id)->count());
    }

    public function test_notify_role_resolves_all_active_users_with_that_role(): void
    {
        $frontDesk1 = $this->frontDeskUser();
        $frontDesk2 = $this->frontDeskUser();
        $doctor     = User::factory()->create(['role' => 'doctor', 'branch_id' => 1, 'is_active' => true]);

        app(NotificationEngine::class)->notifyRole('front_desk', 'membership_expiring', null, 'Membership expiring soon');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $frontDesk1->id]);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $frontDesk2->id]);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $doctor->id]);
    }
}
