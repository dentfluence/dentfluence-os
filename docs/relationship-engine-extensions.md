# Relationship Engine — Extension Points

> **Created:** 2026-07-02  
> **Author:** Sumit / Dentfluence  
> **Purpose:** Reference for wiring future Dentfluence products into the Relationship Engine without changing its internal structure.

The Relationship Engine is built API-first and event-driven. Every future product (Marketing Engine, Patient App, third-party PMS, AI/Tulip) has a documented entry point below. No schema changes are required to enable any of these integrations.

---

## a. Marketing Engine Hooks

The Marketing Engine listens to events fired by the Relationship Engine and triggers campaigns, sequences, or messages in response.

### Events to listen for

| Event key | Fired when | Suggested Marketing action |
|---|---|---|
| `lead.created` | A new lead enters the system (any source) | Start welcome campaign; assign to drip sequence |
| `relationship.dormant` | Score engine marks a relationship dormant (no activity for N days) | Re-engagement campaign trigger |
| `opportunity.declined` | A TreatmentOpportunity status → `declined` | Start nurture sequence (follow-up in 30/60/90 days) |
| `appointment.no_show` | Appointment status → `no_show` | Send recovery message within 2 hours |
| `membership.expiring` | Membership within N days of expiry | Renewal campaign trigger |

All events are written to the `activities` table via `ActivityEngine::log()`. The Marketing Engine should listen using a Laravel Event listener or a scheduled query against the `activities` table filtered by `event` key.

### API endpoint for batch consumption

```
GET /api/v1/relationships?filter=dormant&score_below=40
```

Returns all relationships that are dormant AND have a score below 40 — the primary re-engagement list. Paginated, Sanctum-authenticated, no schema changes needed.

> **Implementation note:** The `filter=dormant` scope is `Relationship::dormant()` (already defined on the model). `score_below=40` is a simple `where('score', '<', 40)` that the RelationshipController `index()` method (when built) will support as a query param.

### How to wire

```php
// In a Laravel Event listener or Job:
use App\Services\Relationship\ActivityEngine;

ActivityEngine::log($relationship, 'marketing.campaign_sent', $actor, [
    'campaign_id' => $campaign->id,
    'channel'     => 'whatsapp',
]);
// This writes back into the unified timeline — the clinic can see what the
// Marketing Engine did, alongside clinical and financial events.
```

---

## b. Patient App Hooks

The Patient App is a future self-service channel. Patients will be able to book appointments, view their timeline, and submit feedback.

### Reading patient data

```
GET /api/v1/relationships/{id}
```

When called with a **patient-scoped token** (a Sanctum token issued to the patient rather than a staff user), this endpoint returns the patient's own relationship profile: journeys, timeline, score, membership status. No new endpoint needed — the existing controller already returns everything the Patient App needs.

### Events the Patient App should emit

When the Patient App causes a meaningful action, it should write it back via the activity log endpoint so it appears on the clinic's timeline:

```
POST /api/v1/relationships/{id}/activity
```

| Patient action | `event` value to send | `description` |
|---|---|---|
| Books their own appointment | `appointment.self_booked` | "Patient self-booked via Patient App" |
| Submits a feedback rating | `feedback.submitted` | "Patient submitted feedback (rating: N)" |
| Views their treatment plan | `treatment_plan.viewed` | "Patient viewed treatment plan" |
| Accepts a treatment plan | `treatment_plan.accepted` | "Patient accepted treatment plan via app" |

### Actor type for Patient App actions

```php
// In ActivityEngine::log(), pass actor_type as 'patient_app' via metadata:
ActivityEngine::log(
    subject:     $relationship,
    event:       'appointment.self_booked',
    actor:       null,                          // no staff actor
    metadata:    ['actor_type' => 'patient_app', 'patient_id' => $patient->id],
    description: 'Patient self-booked via Patient App',
);
```

The `activities` table already has `actor_type` and `actor_id` as polymorphic columns. `actor_type = 'patient_app'` is a valid string value — no migration needed.

---

## c. Third-Party PMS Hooks

Clinics migrating from another PMS (e.g., Dentrix, Curve Hero, Clinicea) can bulk-import relationships so the Relationship Engine immediately knows about all historical patients.

### Bulk import endpoint

```
POST /api/v1/relationships/import
```

> **Note:** This endpoint is not yet built. Add it to `RelationshipController` when migration tooling is needed.

### Expected request body

```json
{
  "relationships": [
    {
      "name":            "Priya Sharma",
      "phone":           "9876543210",
      "email":           "priya@example.com",
      "source":          "dentrix_import",
      "last_visit_date": "2024-11-15"
    }
  ]
}
```

### Field mapping guide

| PMS field | Dentfluence field | Notes |
|---|---|---|
| Patient name | `name` | Required |
| Mobile / phone | `phone` | Used for deduplication (primary key) |
| Email | `email` | Used for deduplication (secondary) |
| Referral source / lead source | `source` | Free text; use `pms_import` as default |
| Last visit / last appointment | `last_visit_date` | Stored in `metadata`; used to seed recall engine |
| Outstanding balance | `metadata.outstanding` | Informational; not synced to invoices |

### Deduplication

The import endpoint should call `RelationshipEngine::findOrCreate()` for each record. This method already handles deduplication by phone → email → create. If a record with the same phone exists, it is updated rather than duplicated. No duplicate relationships will be created.

```php
// Import logic (one record at a time):
$relationship = app(RelationshipEngine::class)->findOrCreate([
    'name'   => $row['name'],
    'phone'  => $row['phone'],
    'email'  => $row['email'],
    'source' => $row['source'] ?? 'pms_import',
]);

// Log the import event for traceability:
app(ActivityEngine::class)->log(
    $relationship,
    'relationship.imported',
    null,
    ['source' => $row['source'], 'last_visit_date' => $row['last_visit_date'] ?? null],
    $relationship->id,
    'Relationship imported from ' . ($row['source'] ?? 'PMS'),
);
```

---

## d. AI / Tulip Hooks

Tulip is Dentfluence's internal AI assistant. The Relationship Engine is already structured to give Tulip everything it needs with zero schema changes.

### What Tulip needs and where to get it

| Tulip need | Where it comes from |
|---|---|
| Full person context for any patient/lead | `GET /api/v1/relationships/{id}` — profile + journeys + recent activities in one call |
| Complete event history for context window | `GET /api/v1/relationships/{id}/timeline` — paginated, filterable by event type |
| Today's call list for reception coaching | `GET /api/v1/relationships/today` — TodayActionsEngine output |
| Search for a person by name or phone | `GET /api/v1/relationships/search?q=` |

### How Tulip should write back

When Tulip generates a call brief, sends a message, or takes an automated action, it should write the event back so it appears on the clinic timeline:

```
POST /api/v1/relationships/{id}/activity
{
  "event": "ai.call_brief_generated",
  "description": "Tulip generated a recall call brief",
  "metadata": {
    "brief_summary": "Patient is due for a 6-month recall. Last visited Nov 2024 for scaling.",
    "actor_type": "tulip_ai"
  }
}
```

### No schema changes needed for AI V2

The `activities.metadata` column is a JSON column — it accepts any structured payload Tulip wants to store. The `activities.event` column uses dot-notation keys (`ai.call_brief_generated`, `ai.message_sent`, etc.) which are already supported by `ActivityEngine` and the `ofEvent()` scope.

> Tulip reads the timeline, acts, writes back. One loop. No new tables.

---

## Summary Table

| Product | Read from | Write to | Schema changes |
|---|---|---|---|
| Marketing Engine | `activities` table (event listener) + `/api/v1/relationships?filter=dormant` | `ActivityEngine::log()` | None |
| Patient App | `GET /api/v1/relationships/{id}` + `/timeline` | `POST /api/v1/relationships/{id}/activity` | None |
| Third-party PMS | — | `POST /api/v1/relationships/import` (to build) + `RelationshipEngine::findOrCreate()` | None |
| AI / Tulip | `GET /api/v1/relationships/{id}` + `/timeline` + `/today` | `POST /api/v1/relationships/{id}/activity` | None |

---

*This document is the authoritative reference for Relationship Engine extension points. Update it whenever a new integration is designed or built.*
