<?php

namespace App\Services\Cms;

use App\Models\ClinicalMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ClinicalMediaService
 *
 * Handles storing uploaded clinical files (photos, X-rays, scans, OPG, PDFs)
 * and creating the corresponding ClinicalMedia record.
 *
 * Usage:
 *   $mediaService->register($uploadedFile, [
 *       'patient_id'      => 1,
 *       'source_type'     => Consultation::class,
 *       'source_id'       => 25,
 *       'treatment_name'  => 'Apical Periodontitis',
 *       'tooth_no'        => '26',
 *       'treatment_stage' => 'before',
 *       'visit_date'      => '2026-06-06',
 *       'media_type'      => 'photo',
 *       'tags'            => ['photo', 'before'],
 *   ]);
 */
class ClinicalMediaService
{
    /**
     * Store a file and register it as a ClinicalMedia record.
     *
     * @param  UploadedFile  $file
     * @param  array         $context
     * @return ClinicalMedia
     */
    public function register(UploadedFile $file, array $context): ClinicalMedia
    {
        $patientId = $context['patient_id'];
        $mediaType = $context['media_type'] ?? 'photo';

        // ── Determine storage path ────────────────────────────────────────────
        // Store under: clinical/{patient_id}/{media_type}/{date}_{uuid}.{ext}
        $ext       = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename  = now()->format('Ymd') . '_' . Str::uuid() . '.' . $ext;
        $directory = "clinical/{$patientId}/{$mediaType}";
        // Security (Phase A): clinical files go to the PRIVATE disk and are served
        // only via the authenticated SecureMediaController route — never publicly.
        $disk      = 'local';

        $path = $file->storeAs($directory, $filename, $disk);

        // ── Create ClinicalMedia record ───────────────────────────────────────
        $media = ClinicalMedia::create([
            'patient_id'       => $patientId,
            'doctor_id'        => Auth::id(),
            'consultation_id'  => $this->extractSourceId($context, 'App\Models\Consultation'),
            'visit_id'         => $this->extractSourceId($context, 'App\Models\TreatmentVisit'),
            'treatment_name'   => $context['treatment_name'] ?? null,
            'tooth_no'         => $context['tooth_no'] ?? null,
            'treatment_stage'  => $context['treatment_stage'] ?? 'before',
            'media_type'       => $mediaType,
            'original_path'    => $path,
            'watermarked_path' => null,
            'disk'             => $disk,
            'tags'             => $context['tags'] ?? [],
            'searchable_tags'  => $context['tags'] ?? [],
            'original_filename'=> $file->getClientOriginalName(),
            'file_size'        => $file->getSize(),
            'mime_type'        => $file->getMimeType(),
            'notes'            => $context['notes'] ?? null,
            'visit_date'       => $context['visit_date'] ?? now()->toDateString(),
            'upload_date'      => now()->toDateString(),
            'media_date'       => $context['visit_date'] ?? now()->toDateString(),
        ]);

        return $media;
    }

    /**
     * Extract source_id when source_type matches.
     */
    private function extractSourceId(array $context, string $type): ?int
    {
        if (isset($context['source_type']) && $context['source_type'] === $type) {
            return $context['source_id'] ?? null;
        }
        return null;
    }
}
