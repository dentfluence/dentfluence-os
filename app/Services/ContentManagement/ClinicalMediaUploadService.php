<?php

namespace App\Services\ContentManagement;

use App\Models\ClinicalMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClinicalMediaUploadService
{
    public function __construct(private WatermarkService $watermark) {}

    /**
     * Called by ConsultationController / VisitController after their own upload logic.
     * Registers the file into clinical_media and auto-watermarks it.
     *
     * @param  UploadedFile|string  $file  An UploadedFile OR an already-stored relative path.
     * @param  array  $meta  Keys: patient_id, doctor_id, consultation_id, visit_id,
     *                        treatment_name, tooth_no, treatment_stage, tags (array), disk
     * @return ClinicalMedia
     */
    public function store(UploadedFile|string $file, array $meta = []): ClinicalMedia
    {
        $disk = $meta['disk'] ?? 'local';

        // Store file if an UploadedFile was passed
        if ($file instanceof UploadedFile) {
            $dir  = 'cms/originals/' . ($meta['patient_id'] ?? 'unknown');
            $path = $file->store($dir, $disk);
            $filename = $file->getClientOriginalName();
            $size     = $file->getSize();
            $mime     = $file->getMimeType();
            $type     = $this->detectMediaType($file);
        } else {
            // Already stored — just index it
            $path     = $file;
            $filename = basename($file);
            $size     = null;
            $mime     = null;
            $type     = $meta['media_type'] ?? $this->detectMediaTypeFromPath($path);
        }

        // Auto-suggest tags
        $tags = $this->buildTags($meta, $type);

        // Watermark
        $watermarkedPath = $this->watermark->apply($path, [
            'disk'         => $disk,
            'doctor_name'  => $meta['doctor_name'] ?? null,
            'patient_name' => $meta['patient_name'] ?? null,
            'date'         => now()->format('d M Y'),
        ]);

        return ClinicalMedia::create([
            'patient_id'       => $meta['patient_id']       ?? null,
            'doctor_id'        => $meta['doctor_id']        ?? null,
            'consultation_id'  => $meta['consultation_id']  ?? null,
            'visit_id'         => $meta['visit_id']         ?? null,
            'treatment_name'   => $meta['treatment_name']   ?? null,
            'tooth_no'         => $meta['tooth_no']         ?? null,
            'treatment_stage'  => $meta['treatment_stage']  ?? null,
            'media_type'       => $type,
            'original_path'    => $path,
            'watermarked_path' => $watermarkedPath,
            'disk'             => $disk,
            'searchable_tags'  => $tags,
            'original_filename'=> $filename,
            'file_size'        => $size,
            'mime_type'        => $mime,
            'upload_date'      => now()->toDateString(),
        ]);
    }

    /** Detect media type from UploadedFile mime */
    private function detectMediaType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        if (str_starts_with($mime, 'image/'))      return 'photo';
        if ($mime === 'video/mp4')                  return 'video';
        if ($mime === 'application/pdf')            return 'pdf';
        if (str_contains($mime, 'dicom'))           return 'cbct';
        return 'photo';
    }

    private function detectMediaTypeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'heic' => 'photo',
            'mp4', 'mov', 'avi'                   => 'video',
            'pdf'                                 => 'pdf',
            'dcm', 'dicom'                        => 'cbct',
            'stl'                                 => 'scan',
            default                               => 'photo',
        };
    }

    private function buildTags(array $meta, string $mediaType): array
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
