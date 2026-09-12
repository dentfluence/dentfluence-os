<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\ClinicalFile;
use App\Models\TreatmentPlanItem;
use App\Services\ClinicalLibrary\ClinicalFileUploadService;
use App\Services\ClinicalLibrary\ClinicalLibrarySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Clinical Library — mobile face.
 *
 * Every endpoint here is a thin skin over something the web app already uses.
 * That is the whole point: this module's entire bug history is two surfaces
 * that were supposed to agree and quietly did not — the Content Manager's
 * treatment filter against the page beside it, the settings screen against the
 * watermark service, the upload modal's format list against the allowlist that
 * enforces it. So search() does not contain a query. It hands the request to
 * ClinicalLibrarySearchService, the same object the web drawer and the Content
 * Manager filter bar go through, and returns what comes back.
 *
 * options() exists so the PHONE holds no vocabulary of its own. Before this,
 * the Add Document screen shipped its own eight categories and its own list of
 * seven file extensions — a fifth vocabulary and a sixth format list, both
 * already wrong (it offered no .avif, .xls, .xlsx or .stl, and its
 * "Prescription" and "Insurance" both landed as file_type 'other'). A list
 * compiled into an APK cannot be corrected by deploying the server.
 *
 * BRANCH SCOPE: reads here are locked to the caller's own branch, matching
 * PatientProfileController::find() — every other mobile read already works
 * that way, so not scoping would be the anomaly. Note that the WEB library
 * search is NOT branch-scoped today; a two-branch clinic sees one pooled list
 * there and two separate ones here. That is a product decision (one shared
 * library or one per branch?) and is deliberately left alone rather than
 * settled by whoever wrote this endpoint.
 */
class ClinicalLibraryController extends ApiController
{
    /**
     * GET /api/v1/clinical-library/options
     *
     * Every vocabulary the tagging and search UI needs, from the constants that
     * enforce them. The phone caches this per session and hardcodes nothing.
     */
    public function options(): JsonResponse
    {
        return $this->success([
            'treatments' => collect(ClinicalFile::TREATMENT_CATEGORIES)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),

            'stages' => collect(ClinicalFile::STAGES)->map(fn ($s) => [
                'key'   => $s,
                'label' => $s === 'followup' ? 'Follow-up' : ucfirst($s),
            ])->values(),

            'file_types' => collect(ClinicalFile::FILE_TYPES)->map(fn ($t) => [
                'key'   => $t,
                'label' => (new ClinicalFile(['file_type' => $t]))->file_type_label,
            ])->values(),

            'statuses' => [
                ['key' => TreatmentPlanItem::PROGRESS_PENDING,   'label' => 'Pending'],
                ['key' => TreatmentPlanItem::PROGRESS_PARTIAL,   'label' => 'Ongoing'],
                ['key' => TreatmentPlanItem::PROGRESS_COMPLETED, 'label' => 'Completed'],
                ['key' => TreatmentPlanItem::PROGRESS_INVOICED,  'label' => 'Invoiced'],
            ],

            // regions first, then every tooth by quadrant — the same list the
            // web drawer and Content Manager filter bar offer.
            'teeth' => ClinicalLibrarySearchService::toothOptions(),

            // The file picker's allowlist and the per-format caps, so the phone
            // never offers a format the server will refuse (it used to) and
            // never lets a 40 MB photo start uploading before failing (ditto).
            'upload' => [
                'allowed_extensions' => ClinicalFileUploadService::allowedExtensions(),
                'max_kb'             => ClinicalFileUploadService::MAX_KB_BY_EXTENSION,
            ],
        ], '');
    }

    /**
     * GET /api/v1/clinical-library/search
     *
     * One line of text plus optional explicit filters, exactly as the web
     * drawer sends them. `interpreted` comes back so the phone can show what
     * the box was understood to mean as removable chips — a search that
     * silently reinterprets what you typed is worse than one that asks.
     */
    public function search(Request $request, ClinicalLibrarySearchService $search): JsonResponse
    {
        $input = $this->searchInput($request);

        $page = $search->search($input);

        return $this->success([
            'files'       => collect($page->items())->map(fn (ClinicalFile $f) => $this->shape($f))->values(),
            'interpreted' => $search->interpretation($request->query('q')),
            'total'       => $page->total(),
            'page'        => $page->currentPage(),
            'last_page'   => $page->lastPage(),
        ], '');
    }

    /**
     * GET /api/v1/clinical-library/showcase?mode=browse|present
     *
     * browse   — the clinician's own case library. Internal, so it carries the
     *            patient's name and says plainly whether a case may be shown.
     * present  — what a patient in the chair is allowed to see. THIS IS A
     *            DIFFERENT ACT, and the gate is enforced here rather than in
     *            the app: only files whose consent is given AND whose marketing
     *            approval is approved, and the payload carries no patient name
     *            and no patient id at all. If the filtering lived in the phone,
     *            the next screen someone writes would leak it; a name that
     *            reaches the device has already left the clinic.
     *
     * Cases are grouped server-side (patient + treatment) into before/after
     * sets, because "showcase" means a case, not a pile of files.
     */
    public function showcase(Request $request, ClinicalLibrarySearchService $search): JsonResponse
    {
        $present = $request->query('mode') === 'present';

        $input = $this->searchInput($request);
        $input['eligible_case_library'] = 1;
        $input['per_page'] = 96;

        if ($present) {
            $input['consent']   = 'given';
            $input['marketing'] = 'approved';
        }

        $files = collect($search->search($input)->items());

        $cases = $files
            ->groupBy(fn (ClinicalFile $f) => $f->patient_id . '|' . ($f->treatment_category ?: $f->procedure))
            ->map(function ($group) use ($present) {
                /** @var \Illuminate\Support\Collection<int,ClinicalFile> $group */
                $first = $group->first();

                $case = [
                    // A stable handle for the UI that is not the patient id.
                    'key'        => substr(sha1((string) $first->patient_id . '|' . (string) $first->treatment_category), 0, 12),
                    'treatment'  => $first->treatment_category_label ?: $first->procedure,
                    'teeth'      => $group->pluck('tooth_number')->filter()->unique()->implode(', ') ?: null,
                    'count'      => $group->count(),
                    'before'     => $group->where('stage', 'before')->map(fn ($f) => $this->shape($f, $present))->values(),
                    'after'      => $group->whereIn('stage', ['after', 'followup'])->map(fn ($f) => $this->shape($f, $present))->values(),
                    'other'      => $group->whereNotIn('stage', ['before', 'after', 'followup'])->map(fn ($f) => $this->shape($f, $present))->values(),
                ];

                if (! $present) {
                    // Internal view only. Both of these exist so the clinician
                    // can see WHY a case cannot be presented yet, instead of it
                    // simply being absent from the present list with no reason.
                    $case['patient_id']   = $first->patient_id;
                    $case['patient_name'] = $first->patient?->name;
                    $case['presentable']  = $group->contains(
                        fn ($f) => $f->consent_status === 'given' && $f->marketing_status === 'approved'
                    );
                    $case['blocked_by']   = $case['presentable'] ? null : $this->blockedReason($group);
                }

                return $case;
            })
            ->values()
            // A case with nothing to show is not a case.
            ->filter(fn ($c) => ($c['before']->isNotEmpty() || $c['after']->isNotEmpty() || $c['other']->isNotEmpty()))
            ->values();

        return $this->success([
            'mode'  => $present ? 'present' : 'browse',
            'cases' => $cases,
            'total' => $cases->count(),
        ], '');
    }

    /**
     * GET /api/v1/clinical-library/files/{file}/raw[?v=wm]
     *
     * The bytes, for a token-authenticated caller.
     *
     * The web app serves these through SecureMediaController, which sits behind
     * the `auth` (session) middleware — a bearer token does not satisfy it, so
     * the phone could upload a photo and then never display one. That is why
     * every mobile list in this app has been text and icons. This is the same
     * stream and the same branch check, reachable with the token the app
     * already holds.
     */
    public function raw(Request $request, ClinicalFile $file): StreamedResponse
    {
        $this->authorizeBranch($request, $file);

        $path = ($request->query('v') === 'wm' && $file->watermarked_path)
            ? $file->watermarked_path
            : $file->path;

        $disk = $file->disk ?: 'local';

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            $this->refuse(404, 'File not found.');
        }

        return Storage::disk($disk)->response(
            $path,
            $file->original_filename ?: basename((string) $path),
            ['Content-Disposition' => 'inline; filename="' . addslashes($file->original_filename ?: 'file') . '"']
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Translate the request into the shape ClinicalLibrarySearchService takes.
     * Nothing is interpreted here — that is the service's job, and doing any of
     * it twice is how the two web surfaces came to disagree.
     */
    private function searchInput(Request $request): array
    {
        $input = $request->only([
            'q', 'tooth', 'stage', 'file_type', 'treatment', 'treatment_category',
            'status', 'patient_id', 'doctor_id', 'tag', 'consent', 'marketing',
            'from', 'to', 'page', 'per_page',
            'eligible_marketing', 'eligible_education', 'eligible_teaching',
            'eligible_research', 'eligible_case_library',
        ]);

        // Locked to the caller's branch — see the class docblock.
        $input['branch_id'] = $request->user()->branch_id;

        return $input;
    }

    /**
     * One file, as the phone needs it.
     *
     * @param  bool  $anonymous  Strip everything that identifies the patient.
     *                           Set for `present` mode, where the payload is
     *                           about to be shown to somebody else's patient.
     */
    private function shape(ClinicalFile $f, bool $anonymous = false): array
    {
        $data = [
            'id'                       => $f->id,
            'title'                    => $f->title ?: $f->original_filename,
            'file_type'                => $f->file_type,
            'file_type_label'          => $f->file_type_label,
            'procedure'                => $f->procedure,
            'treatment_category'       => $f->treatment_category,
            'treatment_category_label' => $f->treatment_category_label,
            'tooth'                    => $f->tooth_number,
            'stage'                    => $f->stage,
            'stage_label'              => $f->stage_label,
            'captured_at'              => $f->captured_at?->format('d M Y'),
            'is_image'                 => $f->isImage(),
            'no_preview_reason'        => $f->no_preview_reason,
            'file_size_human'          => $f->file_size_human,
            // Always the stamped copy when one exists: a file being looked at
            // outside the patient's own record should carry the clinic's mark.
            'url'                      => route('api.clinical-library.raw', [$f->id, 'v' => 'wm']),
        ];

        if ($anonymous) {
            return $data;
        }

        return $data + [
            'patient_id'               => $f->patient_id,
            'patient_name'             => $f->patient?->name,
            'filename'                 => $f->original_filename,
            'notes'                    => $f->notes,
            'tags'                     => $f->tags ?? [],
            'consent_status'           => $f->consent_status,
            'marketing_status'         => $f->marketing_status,
            'is_marketing_eligible'    => (bool) $f->is_marketing_eligible,
            'is_education_eligible'    => (bool) $f->is_education_eligible,
            'is_teaching_eligible'     => (bool) $f->is_teaching_eligible,
            'is_research_eligible'     => (bool) $f->is_research_eligible,
            'is_case_library_eligible' => (bool) $f->is_case_library_eligible,
        ];
    }

    /** Why this case cannot be presented, in words a receptionist can act on. */
    private function blockedReason(\Illuminate\Support\Collection $group): string
    {
        $consent = $group->contains(fn ($f) => $f->consent_status === 'given');
        $approved = $group->contains(fn ($f) => $f->marketing_status === 'approved');

        return match (true) {
            ! $consent && ! $approved => 'Needs patient consent and marketing approval',
            ! $consent                => 'Needs patient consent',
            default                   => 'Waiting for marketing approval',
        };
    }

    /**
     * Same rule as PatientProfileController::find() — the caller's own branch,
     * with no admin bypass, so a token can never widen what the phone can read.
     */
    private function authorizeBranch(Request $request, ClinicalFile $file): void
    {
        $branchId = $file->patient?->branch_id;

        if ($branchId === null || $branchId !== $request->user()->branch_id) {
            $this->refuse(403, 'You do not have access to this file.');
        }
    }

    /**
     * Refuse with a real status code.
     *
     * abort() is NOT used here. On this API an aborted HttpException comes back
     * to the caller as a 500 — the exception handlers in bootstrap/app.php
     * render JSON for the types they know about and this is not one of them, so
     * a deliberate 403 arrives looking like a server crash. That is exactly why
     * PatientProfileController::find() throws an HttpResponseException with its
     * own JsonResponse instead of calling abort(404), and this follows the same
     * convention rather than inventing a second one.
     *
     * Caught by a test: `and cannot fetch one from another branch` asserted 403
     * and received 500. The branch check was working; only the status code the
     * phone would have seen was wrong — which is the kind of thing nothing on
     * screen ever reveals.
     */
    private function refuse(int $status, string $message): never
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => [],
            ], $status)
        );
    }
}
