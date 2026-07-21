<?php

namespace App\Services\Patient;

/**
 * PatientMergeManifest — the declared, classified coverage for a patient merge.
 *
 * A patient's history hangs off TWO parents:
 *   1. patient_id  — direct children (moved by PatientMergeService).
 *   2. relationship_id — the PRE cascade (delegated to Relationship\MergeService).
 *
 * Every table that references a patient (directly or via the relationship) MUST
 * be classified into exactly one bucket below. `patients:merge-coverage`
 * reconciles this manifest against the LIVE schema and fails if any real
 * patient_id / relationship_id table is left unclassified — so we can never
 * silently fragment a record. This is the single source of truth for slice 2's
 * re-parenting; edit this list, not the service body.
 *
 * NOTE: tables that carry BOTH patient_id and relationship_id (tasks,
 * treatment_opportunities, today_actions, patient_journeys) legitimately appear
 * in both a patient_id bucket and the relationship list — both columns must end
 * up pointing at the master.
 */
final class PatientMergeManifest
{
    /**
     * Direct patient_id children moved blindly (loser -> master), each guarded
     * by Schema::hasColumn at move time. Non-financial, non-special.
     */
    public const CHILD_TABLES = [
        'appointments',
        'consultations',
        'consultation_coha_reports',
        'treatment_plans',
        'treatment_visits',
        'treatment_visit_items',
        'implant_placements',
        'prescriptions',
        'lab_cases',
        'clinical_files',
        'clinical_media',
        'patient_notes',
        'patient_relationship_notes',
        'patient_alerts',
        'patient_communications',
        'patient_consents',
        'consent_logs',
        'treatment_consents',
        'patient_allergies',
        'data_requests',
        'voice_notes',
        'reviews',
        'presentations',
        'wa_threads',
        'communication_queue',
        'follow_ups',
        'today_actions',
        'patient_journeys',
        'treatment_opportunities',
        'tasks',
        'cms_treatment_cases',
        'cms_media',
    ];

    /**
     * Money / ledger children. Moved like children, but surfaced separately in
     * the preview and reconciled on the confirmation screen (financial history
     * must follow the patient and totals must add up).
     */
    public const MONEY_TABLES = [
        'invoices',
        'receipts',
        'final_bills',
        'billing_prompts',
        'emi_schedules',
        'coupon_usage',
        'invoice_payments',
        'wallet_transactions',
        'finance_transactions',
        'finance_income_entries',
        'membership_benefit_logs',
    ];

    /**
     * Special-rule entities — NOT a blind column move; each has bespoke logic
     * (slice 3):
     *   wallets                    -> sum balances into master + ledger entry
     *   finance_patient_memberships-> keep later-expiry/higher-tier, log the other
     *   patient_identifiers        -> one ABHA per patient; block if both verified
     *   patient_links              -> re-point patient_id AND linked_patient_id;
     *                                 reconcile duplicate pairs (guardian OR,
     *                                 more-specific relationship_type wins, keep
     *                                 master notes), drop self-links. Guardian↔ward
     *                                 merges are blocked up-front — never a silent
     *                                 drop (Patients Phase 3, Slice 1).
     *   patient_tag                -> tag pivot; re-point patient_id, drop rows
     *                                 that would collide with the master's tags
     */
    public const SPECIAL_TABLES = [
        'wallets',
        'finance_patient_memberships',
        'patient_identifiers',
        'patient_links',
        'patient_tag',
    ];

    /**
     * Deliberately NOT moved. `patients` is the loser row itself (archived by the
     * merge core, not re-parented). Immutable audit history stays with the
     * original record; shadow tables are irrelevant; search_index is rebuilt for
     * the master and dedup_candidates are resolved/closed — neither is re-pointed.
     */
    public const SKIP_TABLES = [
        'patients',
        'audit_logs',
        'automation_shadow_log',
        'workflow_shadow_log',
        'search_index',
        'dedup_candidates',
    ];

    /**
     * relationship_id cascade — delegated to Relationship\MergeService. Listed
     * here as the coverage we EXPECT that service to move (its own TARGET_TABLES
     * is currently narrower — the coverage command surfaces the difference).
     */
    public const RELATIONSHIP_TABLES = [
        'leads',
        'treatment_opportunities',
        'tasks',
        'relationship_journeys',
        'activities',
        'relationship_notifications',
        'relationship_contact_log',
        'relationship_rule_logs',
        'today_actions',
        'insight_signals',
        'workflow_instances',
        'patient_journeys',
    ];

    /** Every table we consider "accounted for" on the patient_id axis. */
    public static function patientIdCovered(): array
    {
        return array_unique(array_merge(
            self::CHILD_TABLES,
            self::MONEY_TABLES,
            self::SPECIAL_TABLES,
            self::SKIP_TABLES,
        ));
    }

    /** Every table we consider "accounted for" on the relationship_id axis. */
    public static function relationshipIdCovered(): array
    {
        return array_unique(array_merge(
            self::RELATIONSHIP_TABLES,
            self::SKIP_TABLES,
        ));
    }
}
