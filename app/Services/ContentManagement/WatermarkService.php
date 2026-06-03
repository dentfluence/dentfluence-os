<?php

namespace App\Services\ContentManagement;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WatermarkService
{
    /**
     * Apply watermark to an image and store in watermarked path.
     * Returns the watermarked storage path, or null on failure.
     */
    public function apply(string $originalPath, array $options = []): ?string
    {
        $disk = $options['disk'] ?? 'local';

        // Check Intervention Image is available
        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            // Graceful fallback: log and return null
            \Log::warning('CMS WatermarkService: intervention/image not installed. Skipping watermark.');
            return null;
        }

        try {
            $manager = new \Intervention\Image\ImageManager(
                new \Intervention\Image\Drivers\Gd\Driver()
            );

            $absolutePath = Storage::disk($disk)->path($originalPath);
            $image = $manager->read($absolutePath);

            $text = $this->buildWatermarkText($options);
            $this->applyTextWatermark($image, $text);

            $watermarkedPath = $this->buildWatermarkedPath($originalPath);
            $encoded = $image->toJpeg(90);

            Storage::disk($disk)->put($watermarkedPath, (string) $encoded);

            return $watermarkedPath;
        } catch (\Throwable $e) {
            \Log::warning('CMS WatermarkService: failed to watermark ' . $originalPath . ' — ' . $e->getMessage());
            return null;
        }
    }

    private function buildWatermarkText(array $options): string
    {
        $parts = [];

        $clinicName = $options['clinic_name'] ?? config('cms.watermark.clinic_name');
        if ($clinicName) $parts[] = $clinicName;

        $doctorName = $options['doctor_name'] ?? null;
        if ($doctorName) $parts[] = 'Dr. ' . $doctorName;

        $patientName = $options['patient_name'] ?? null;
        if ($patientName && config('cms.watermark.include_patient_name', false)) {
            $parts[] = $patientName;
        }

        $date = $options['date'] ?? now()->format('d M Y');
        if ($date) $parts[] = $date;

        return implode('  |  ', $parts);
    }

    /**
     * Draw subtle corner text watermark on the image.
     * Uses GD directly if Intervention fonts are unavailable.
     */
    private function applyTextWatermark($image, string $text): void
    {
        // We write in the bottom-right corner, semi-transparent white
        // The Intervention Image v3 API:
        $image->text($text, $image->width() - 12, $image->height() - 10, function ($font) {
            $font->size(11);
            $font->color([255, 255, 255, 0.65]);
            $font->align('right');
            $font->valign('bottom');
        });
    }

    private function buildWatermarkedPath(string $originalPath): string
    {
        $dir      = dirname($originalPath);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $ext      = pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'jpg';

        return $dir . '/wm_' . $filename . '.' . $ext;
    }
}
