<?php

namespace App\Services\ContentManagement;

use App\Models\CmsMedia;
use Illuminate\Support\Facades\Log;

class MediaIndexService
{
    public function __construct(
        private TagService      $tagService,
        private WatermarkService $watermarkService,
    ) {}

    // Called after consultation is saved
    // $consultation = the saved Consultation model
    // $uploads      = array of uploaded file paths from the consultation
    public function indexFromConsultation(object $consultation, array $uploads = []): void
    {
        try {
            foreach ($uploads as $upload) {
                $this->createMediaRecord([
                    'patient_id'        => $consultation->patient_id,
                    'consultation_id'   => $consultation->id,
                    'visit_id'          => null,
                    'doctor_id'         => $consultation->created_by ?? auth()->id(),
                    'treatment_name'    => $consultation->treatment_name ?? null,
                    'tooth_no'          => $consultation->tooth_area ?? null,
                    'treatment_stage'   => 'before_treatment',
                    'treatment_status'  => 'ongoing',
                    'media_type'        => $this->detectType($upload['path'] ?? ''),
                    'original_filename' => $upload['name'] ?? null,
                    'original_path'     => $upload['path'] ?? null,
                    'file_size'         => $upload['size'] ?? null,
                    'mime_type'         => $upload['mime'] ?? null,
                    'upload_date'       => now(),
                    'treatment_start_date' => now()->toDateString(),
                ]);
            }
        } catch (\Throwable $e) {
            // Never break consultation save — just log
            Log::error('CMS MediaIndexService failed: ' . $e->getMessage());
        }
    }

    // Called after visit is saved
    public function indexFromVisit(object $visit, array $uploads = []): void
    {
        try {
            foreach ($uploads as $upload) {
                $this->createMediaRecord([
                    'patient_id'       => $visit->patient_id,
                    'consultation_id'  => null,
                    'visit_id'         => $visit->id,
                    'doctor_id'        => $visit->doctor_id ?? auth()->id(),
                    'treatment_name'   => $visit->treatment_name ?? null,
                    'tooth_no'         => $visit->tooth_no ?? null,
                    'treatment_stage'  => $upload['stage'] ?? 'during_treatment',
                    'treatment_status' => 'ongoing',
                    'media_type'       => $this->detectType($upload['path'] ?? ''),
                    'original_filename'=> $upload['name'] ?? null,
                    'original_path'    => $upload['path'] ?? null,
                    'file_size'        => $upload['size'] ?? null,
                    'mime_type'        => $upload['mime'] ?? null,
                    'upload_date'      => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('CMS MediaIndexService (visit) failed: ' . $e->getMessage());
        }
    }

    private function createMediaRecord(array $data): CmsMedia
    {
        // Auto-generate searchable tags
        $tags = $this->tagService->resolveTags(
            array_filter([
                $data['treatment_name'],
                $data['tooth_no'],
                $data['treatment_stage'],
            ])
        );
        $data['searchable_tags'] = $tags;

        $media = CmsMedia::create($data);

        // Apply watermark asynchronously (or sync for now)
        if ($media->original_path) {
            $this->watermarkService->apply($media);
        }

        return $media;
    }

    // Detect media type from file extension
    private function detectType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match(true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic']) => 'photo',
            in_array($ext, ['mp4', 'mov', 'avi', 'webm'])                 => 'video',
            $ext === 'pdf'                                                  => 'pdf',
            $ext === 'dcm'                                                  => 'xray',
            $ext === 'stl'                                                  => 'scan',
            default                                                         => 'other',
        };
    }
}
