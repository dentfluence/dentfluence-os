<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClinicalFile;
use App\Models\ClinicalMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SecureMediaController (Phase A — Security)
 * ------------------------------------------
 * Streams patient clinical files (x-rays, scans, intake forms, photos, PDFs)
 * through an AUTHENTICATED route instead of a public /storage URL.
 *
 * Why this exists:
 *   Previously these files lived on the "public" disk and were served at
 *   /storage/clinical/{patient_id}/... — anyone who guessed a patient id could
 *   download medical images with no login. That is a DPDP / privacy breach.
 *   Now every request passes through 'auth', a branch-ownership check, and
 *   (for downloads) an audit-log entry.
 *
 * Routes (see routes/web.php):
 *   GET /secure-media/file/{clinicalFile}      → clinical_files row
 *   GET /secure-media/legacy/{clinicalMedia}   → clinical_media row (older table)
 *
 * Query params:
 *   ?v=wm  → serve the watermarked copy (if one exists), else the original
 *   ?dl=1  → force a file download (attachment) AND write an audit-log entry
 *            (without it, the file streams inline — used by <img> tags/thumbnails)
 */
class SecureMediaController extends Controller
{
    /** Serve a Phase-9 clinical_files record. */
    public function file(Request $request, ClinicalFile $clinicalFile): StreamedResponse
    {
        $this->authorizeBranch($clinicalFile->patient?->branch_id);

        $path = ($request->query('v') === 'wm' && $clinicalFile->watermarked_path)
            ? $clinicalFile->watermarked_path
            : $clinicalFile->path;

        return $this->stream(
            disk:     $clinicalFile->disk ?: 'local',
            path:     $path,
            filename: $clinicalFile->original_filename ?: basename((string) $path),
            request:  $request,
            model:    $clinicalFile,
        );
    }

    /** Serve a legacy clinical_media record. */
    public function legacy(Request $request, ClinicalMedia $clinicalMedia): StreamedResponse
    {
        $this->authorizeBranch($clinicalMedia->patient?->branch_id);

        $path = ($request->query('v') === 'wm' && $clinicalMedia->watermarked_path)
            ? $clinicalMedia->watermarked_path
            : $clinicalMedia->original_path;

        return $this->stream(
            disk:     $clinicalMedia->disk ?: 'local',
            path:     $path,
            filename: $clinicalMedia->original_filename ?: basename((string) $path),
            request:  $request,
            model:    $clinicalMedia,
        );
    }

    /**
     * Ensure the logged-in user may see files for this patient's branch.
     * Admins see everything; everyone else is locked to their own branch.
     */
    private function authorizeBranch(?int $patientBranchId): void
    {
        $user = Auth::user();

        if ($user && $user->isAdminRole()) {
            return;
        }

        // If the patient (or branch) is missing, fail closed.
        if (! $user || $patientBranchId === null || $user->branch_id !== $patientBranchId) {
            abort(403, 'You do not have access to this file.');
        }
    }

    /**
     * Stream the file from its disk. Inline by default; attachment when ?dl=1.
     * Downloads (not inline thumbnail loads) are written to the audit log.
     */
    private function stream(string $disk, ?string $path, string $filename, Request $request, $model): StreamedResponse
    {
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found.');
        }

        $isDownload = $request->boolean('dl');

        if ($isDownload) {
            $this->audit($model, $request);
            return Storage::disk($disk)->download($path, $filename);
        }

        // Inline (for <img>/<embed>): let the browser render it in place.
        return Storage::disk($disk)->response($path, $filename, [
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    /** Record a download in the tamper-aware audit trail. */
    private function audit($model, Request $request): void
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => 'downloaded',
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'module'         => 'clinical_files',
            'old_values'     => null,
            'new_values'     => ['patient_id' => $model->patient_id],
            'device_type'    => 'web',
            'ip_address'     => $request->ip(),
            'user_agent'     => (string) $request->userAgent(),
        ]);
    }
}
