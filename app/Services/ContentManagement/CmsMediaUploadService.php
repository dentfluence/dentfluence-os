<?php

namespace App\Services\ContentManagement;

use App\Models\CmsMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CmsMediaUploadService
 *
 * Handles storing a file into cms_media, auto-watermarking it, and setting
 * the record to marketing_status=pending until all 3 tags are provided.
 *
 * Usage:
 *   $media = app(CmsMediaUploadService::class)->store($uploadedFile, [
 *       'patient_id'   => 12,
 *       'doctor_id'    => 3,
 *       'doctor_name'  => 'Smith',
 *       'patient_name' => 'Jane Doe',   // only added to watermark if cms.watermark.include_patient_name = true
 *       'disk'         => 'local',
 *   ]);
 */
class CmsMediaUploadService
{
    public function __construct(private WatermarkService $watermark) {}

    /**
     * Store the uploaded file, watermark it, and return a cms_media record
     * in marketing_status=pending (waiting for tag completion).
     *
     * @param  UploadedFile|string  $file   UploadedFile, or an already-stored relative path.
     * @param  array                $meta   See phpdoc above for recognised keys.
     */
    public function store(UploadedFile|string $file, array $meta = []): CmsMedia
    {
        $disk = $meta['disk'] ?? 'local';

        // ── 1. Store file ────────────────────────────────────
        if ($file instanceof UploadedFile) {
            $dir      = 'cms/originals/' . ($meta['patient_id'] ?? 'unknown');
            $path     = $file->store($dir, $disk);
            $filename = $file->getClientOriginalName();
            $size     = $file->getSize();
            $mime     = $file->getMimeType();
            $type     = $this->detectMediaType($file);
        } else {
            $path     = $file;
            $filename = basename($file);
            $size     = null;
            $mime     = null;
            $type     = $meta['media_type'] ?? $this->detectMediaTypeFromPath($path);
        }

        // ── 2. Auto-watermark ────────────────────────────────
        $watermarkedPath = $this->watermark->apply($path, [
            'disk'         => $disk,
            'doctor_name'  => $meta['doctor_name']  ?? null,
            'patient_name' => $meta['patient_name'] ?? null,
            'date'         => now()->format('d M Y'),
        ]);

        // ── 3. Create cms_media record ────────────────────────
        // consent_status defaults to 'pending', marketing_status to 'pending'
        // photo_type and tag_treatment_type are null until the tag modal is filled.
        $media = CmsMedia::create([
            'patient_id'        => $meta['patient_id']       ?? null,
            'consultation_id'   => $meta['consultation_id']  ?? null,
            'visit_id'          => $meta['visit_id']         ?? null,
            'doctor_id'         => $meta['doctor_id']        ?? null,
            'treatment_name'    => $meta['treatment_name']   ?? null,
            'tooth_no'          => $meta['tooth_no']         ?? null,
            'treatment_stage'   => $meta['treatment_stage']  ?? null,
            'media_type'        => $type,
            'original_filename' => $filename,
            'original_path'     => $path,
            'watermarked_path'  => $watermarkedPath,
            'watermark_applied' => (bool) $watermarkedPath,
            'file_size'         => $size,
            'mime_type'         => $mime,
            'searchable_tags'   => $this->buildSearchableTags($meta, $type),
            'upload_date'       => now(),
            // Tagging fields — empty until post-upload modal is submitted
            'consent_status'    => 'pending',
            'photo_type'        => null,
            'tag_treatment_type'=> null,
            'marketing_status'  => 'pending',
        ]);

        return $media;
    }

    /**
     * Apply (or update) the three required tags on an existing record.
     * Once all three are set and consent=given, marketing_status becomes 'approved'.
     * If consent=not_given, marketing_status becomes 'rejected'.
     *
     * @param  CmsMedia  $media
     * @param  array     $tags  Keys: consent_status, photo_type, tag_treatment_type
     */
    public function applyTags(CmsMedia $media, array $tags): CmsMedia
    {
        $media->consent_status     = $tags['consent_status']     ?? $media->consent_status;
        $media->photo_type         = $tags['photo_type']         ?? $media->photo_type;
        $media->tag_treatment_type = $tags['tag_treatment_type'] ?? $media->tag_treatment_type;

        // Auto-resolve marketing_status when all tags are present
        if ($media->isFullyTagged()) {
            $media->marketing_status = match($media->consent_status) {
                'given'     => 'approved',
                'not_given' => 'rejected',
                default     => 'pending',   // consent still 'pending' — wait
            };
        }

        $media->save();
        return $media;
    }

    // ── Private helpers ───────────────────────────────────────

    private function detectMediaType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        if (str_starts_with($mime, 'image/')) return 'photo';
        if ($mime === 'video/mp4')            return 'video';
        if ($mime === 'application/pdf')      return 'pdf';
        if (str_contains($mime, 'dicom'))     return 'cbct';
        return 'photo';
    }

    private function detectMediaTypeFromPath(string $path): string
    {
        return match(strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg', 'png', 'webp', 'heic' => 'photo',
            'mp4', 'mov', 'avi'                   => 'video',
            'pdf'                                 => 'pdf',
            'dcm', 'dicom'                        => 'cbct',
            'stl'                                 => 'scan',
            default                               => 'photo',
        };
    }

    private function buildSearchableTags(array $meta, string $mediaType): array
    {
        $tags = $meta['tags'] ?? [];

        if (!empty($meta['treatment_name'])) {
            $tags[] = Str::slug($meta['treatment_name']);
        }
        if (!empty($meta['tooth_no'])) {
            $tags[] = 'tooth-' . $meta['tooth_no'];
        }
        if (!empty($meta['treatment_stage'])) {
            $tags[] = $meta['treatment_stage'];
        }
        $tags[] = $mediaType;

        return array_values(array_unique(array_filter($tags)));
    }
}
