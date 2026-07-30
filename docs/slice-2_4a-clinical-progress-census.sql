-- ============================================================================
-- SLICE 2.4a — CLINICAL PROGRESS CENSUS (READ ONLY)
-- ============================================================================
-- Every statement is a SELECT. Nothing is written, updated or deleted.
-- Run on the VPS against the production database.
--
--   cd /opt/dentfluence
--   docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" dentfluence \
--     < docs/slice-2_4a-clinical-progress-census.sql
--
-- (or paste block by block into any read-only client)
-- ============================================================================

SELECT '1. VISIT COVERAGE — can a visit be attributed to a plan?' AS section;
SELECT
  COUNT(*)                                              AS total_visits,
  SUM(treatment_plan_id IS NOT NULL)                    AS visits_linked_to_a_plan,
  SUM(treatment_plan_id IS NULL)                        AS visits_with_no_plan,
  ROUND(100 * SUM(treatment_plan_id IS NOT NULL) / NULLIF(COUNT(*),0), 1) AS coverage_pct
FROM treatment_visits
WHERE deleted_at IS NULL;

SELECT '2. VISIT ITEM COVERAGE — can the WORK be attributed to a plan ITEM?' AS section;
-- This is the real derivation path. A visit says "someone came in";
-- a visit ITEM linked to a plan item says "this specific treatment was worked on".
SELECT
  COUNT(*)                                              AS total_visit_items,
  SUM(treatment_plan_item_id IS NOT NULL)               AS linked_to_a_plan_item,
  SUM(treatment_plan_item_id IS NULL)                   AS unlinked_ad_hoc_work,
  ROUND(100 * SUM(treatment_plan_item_id IS NOT NULL) / NULLIF(COUNT(*),0), 1) AS coverage_pct
FROM treatment_visit_items;

SELECT '3. PLANS vs VISITS' AS section;
SELECT
  (SELECT COUNT(*) FROM treatment_plans)                                        AS total_plans,
  (SELECT COUNT(DISTINCT treatment_plan_id) FROM treatment_visits
     WHERE treatment_plan_id IS NOT NULL AND deleted_at IS NULL)                AS plans_with_at_least_one_visit,
  (SELECT COUNT(*) FROM treatment_plans WHERE status = 'completed')             AS plans_marked_completed,
  (SELECT COUNT(*) FROM treatment_plans p WHERE p.status = 'completed'
     AND NOT EXISTS (SELECT 1 FROM treatment_visits v
                     WHERE v.treatment_plan_id = p.id AND v.deleted_at IS NULL)) AS completed_with_NO_visit;

SELECT '4. VISITS PER PLAN — is one visit one treatment, or many?' AS section;
SELECT visits_per_plan, COUNT(*) AS number_of_plans FROM (
  SELECT treatment_plan_id, COUNT(*) AS visits_per_plan
  FROM treatment_visits
  WHERE treatment_plan_id IS NOT NULL AND deleted_at IS NULL
  GROUP BY treatment_plan_id
) x GROUP BY visits_per_plan ORDER BY visits_per_plan;

SELECT '5. REPEAT WORK ON THE SAME PLAN ITEM (multi-session treatment)' AS section;
-- If one plan item appears in several visits, "completed" cannot be inferred
-- from "a visit item exists" — the course spans sessions.
SELECT visit_items_per_plan_item, COUNT(*) AS number_of_plan_items FROM (
  SELECT treatment_plan_item_id, COUNT(*) AS visit_items_per_plan_item
  FROM treatment_visit_items
  WHERE treatment_plan_item_id IS NOT NULL
  GROUP BY treatment_plan_item_id
) y GROUP BY visit_items_per_plan_item ORDER BY visit_items_per_plan_item;

SELECT '6. CONTRADICTIONS — visits attached to plans that should have none' AS section;
SELECT
  SUM(p.status = 'cancelled') AS visits_on_CANCELLED_plans,
  SUM(p.status = 'pending')   AS visits_on_PENDING_plans,
  SUM(p.accepted_at IS NULL)  AS visits_on_NEVER_ACCEPTED_plans
FROM treatment_visits v
JOIN treatment_plans p ON p.id = v.treatment_plan_id
WHERE v.deleted_at IS NULL;

SELECT '7. COMPLETION SOURCE — billing vs clinical, do they agree?' AS section;
SELECT
  COUNT(*)                                                          AS completed_plans,
  SUM(all_items_invoiced)                                           AS billing_would_say_complete,
  SUM(has_visit)                                                    AS has_at_least_one_visit,
  SUM(all_items_invoiced = 1 AND has_visit = 0)                     AS completed_by_BILLING_ONLY,
  SUM(all_items_invoiced = 0 AND has_visit = 1)                     AS completed_by_VISIT_ONLY
FROM (
  SELECT p.id,
    (SELECT COUNT(*) = 0 FROM treatment_plan_items i
       WHERE i.treatment_plan_id = p.id AND i.billing_progress <> 'invoiced') AS all_items_invoiced,
    EXISTS (SELECT 1 FROM treatment_visits v
       WHERE v.treatment_plan_id = p.id AND v.deleted_at IS NULL)             AS has_visit
  FROM treatment_plans p WHERE p.status = 'completed'
) z;

SELECT '8. IS ITEM STATUS ALIVE? (Slice 2.1 said inert — confirm in production)' AS section;
SELECT status, COUNT(*) AS items FROM treatment_plan_items GROUP BY status;

SELECT '9. ITEM BILLING PROGRESS DISTRIBUTION' AS section;
SELECT billing_progress, COUNT(*) AS items FROM treatment_plan_items GROUP BY billing_progress;

SELECT '10. VISIT STATUS DISTRIBUTION' AS section;
SELECT status, COUNT(*) AS visits FROM treatment_visits WHERE deleted_at IS NULL GROUP BY status;
