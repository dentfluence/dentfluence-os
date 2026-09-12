<?php

namespace App\Services\ClinicalLibrary;

use App\Models\ClinicalFile;
use App\Models\WatermarkSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 10 — ClinicalLibrary WatermarkService
 *
 * Generates a watermarked COPY of a clinical image file.
 * The original file (ClinicalFile::$path) is NEVER touched — ever.
 *
 * Watermarked copy is stored at:
 *   {same directory as original}/wm_{original_filename}.{ext}
 * on the same disk as the original.
 *
 * Returns the relative path of the watermarked copy, or null on failure.
 *
 * Configurable via WatermarkSetting (public/settings/watermark.json),
 * with sensible config() defaults when settings haven't been saved yet.
 *
 * Supported elements (each independently toggled):
 *   - Clinic name
 *   - Doctor name
 *   - Treatment / procedure
 *   - Stage
 *   - Tooth number
 *   - Date
 *   - Logo image (optional, loaded from public/images/clinic-logo.*)
 */
class WatermarkService
{
    /**
     * Longest edge of the watermarked copy, in pixels.
     *
     * The stamped file is what every grid, thumbnail and case view loads all day;
     * the untouched original stays on disk as the archive. Capping the copy at
     * this size takes a 3.2 MB clinical photo to roughly 400 KB, which roughly
     * halves total storage instead of doubling it, and makes the library load
     * noticeably faster. Raise it only with a storage number in hand.
     */
    private const DISPLAY_MAX_EDGE = 2000;

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Generate a watermarked copy for the given ClinicalFile.
     *
     * @param  ClinicalFile  $file   The file to watermark (its path is read-only).
     * @param  array         $overrides  Runtime overrides (e.g. ['doctor_name' => 'Smith']).
     *                                  These take priority over WatermarkSetting values.
     * @return string|null  Relative path of the watermarked copy, or null on failure.
     */
    public function generate(ClinicalFile $file, array $overrides = []): ?string
    {
        // Only image types can be watermarked
        if (! $file->isImage()) {
            return null;
        }

        // Require Intervention Image v3
        if (! class_exists(\Intervention\Image\ImageManager::class)) {
            Log::warning('WatermarkService: intervention/image not installed. Run: composer require intervention/image');
            return null;
        }

        // ── 1. Config + text are resolved BEFORE the image is touched ────────
        // so that a switched-off (or empty) watermark costs nothing at all.
        $config = $this->buildConfig($file, $overrides);

        if (! $config['enabled']) {
            return null;
        }

        $text = $this->buildWatermarkText($file, $config);

        if ($text === '' && ! $config['show_logo']) {
            // Nothing to stamp — do not write a second copy of the original.
            return null;
        }

        try {
            // ── 2. Load original image ────────────────────────────────────────
            $absolutePath = Storage::disk($file->disk)->path($file->path);

            if (! file_exists($absolutePath)) {
                Log::warning("WatermarkService: original file not found at [{$absolutePath}] for ClinicalFile #{$file->id}");
                return null;
            }

            $driver  = $this->resolveDriver();
            $manager = new \Intervention\Image\ImageManager($driver);
            $image   = $manager->read($absolutePath);

            // Scale BEFORE stamping so the text is sized against the final image,
            // not against a 6000px original it will never be seen at.
            if (max($image->width(), $image->height()) > self::DISPLAY_MAX_EDGE) {
                $image->scaleDown(self::DISPLAY_MAX_EDGE, self::DISPLAY_MAX_EDGE);
            }

            // ── 3. Apply logo (if configured and file exists) ─────────────────
            if ($config['show_logo'] && $config['logo_path']) {
                $this->applyLogo($image, $config);
            }

            // ── 4. Apply text watermark ───────────────────────────────────────
            if ($text !== '') {
                $this->applyTextWatermark($image, $text, $config);
            }

            // ── 5. Write watermarked copy — NEVER overwrite the original ──────
            $watermarkedPath = $this->buildWatermarkedPath($file->path);
            $encoded         = $image->toJpeg((int) ($config['quality'] ?? 88));

            Storage::disk($file->disk)->put($watermarkedPath, (string) $encoded);

            Log::info("WatermarkService: generated [{$watermarkedPath}] for ClinicalFile #{$file->id}");

            return $watermarkedPath;

        } catch (\Throwable $e) {
            Log::warning("WatermarkService: failed for ClinicalFile #{$file->id} — {$e->getMessage()}");
            return null;
        }
    }

    // ── Configuration ──────────────────────────────────────────────────────────

    /**
     * Merge WatermarkSetting (JSON store) with runtime overrides.
     *
     * Priority: $overrides > WatermarkSetting > config() defaults
     */
    private function buildConfig(ClinicalFile $file, array $overrides): array
    {
        // Base defaults (used when settings/watermark.json doesn't exist yet).
        //
        // CEO ruling 12 Sep 2026: the watermark carries the CLINIC NAME and the
        // TREATMENT, and nothing else. Doctor name, stage, tooth number and date
        // are still supported switches but default OFF — a stamp that spans the
        // whole frame stops being a credit line and becomes damage to the photo.
        //
        // PATIENT NAME IS NOT A SWITCH AND NEVER WILL BE. These images are shared
        // for marketing, teaching and case discussion; burning a patient's name
        // into the pixels is irreversible and is a DPDP exposure the consent
        // workflow cannot undo. See buildWatermarkText().
        $defaults = [
            // Text elements — each can be toggled independently
            'enabled'           => true,
            'show_clinic_name'  => true,
            'show_treatment'    => true,
            'show_doctor_name'  => false,
            'show_stage'        => false,
            'show_tooth_number' => false,
            'show_date'         => false,
            // Logo
            'show_logo'         => false,
            'logo_path'         => null,   // absolute path to logo file
            // Position: top-left | top-right | bottom-left | bottom-right | center
            'position'          => 'bottom-right',
            // Style
            'opacity'           => 0.70,   // 0.0 – 1.0
            'font_size'         => 22,
            'font_path'         => null,   // absolute path to a .ttf; null => GD's tiny built-in font
            'quality'           => 82,     // JPEG quality of the display copy (the original is untouched)
            // Clinic name fallback (if not in settings, read from app config)
            'clinic_name'       => config('app.clinic_name', config('app.name', 'Dentfluence')),
        ];

        // Merge saved settings from WatermarkSetting JSON store. The settings
        // screen posts its own key names (wm_clinic_name, wm_position, …), so
        // they are translated here rather than leaving two vocabularies that
        // silently never meet — which is exactly why no saved setting has ever
        // had an effect on a generated watermark until now.
        $saved = $this->normaliseSavedSettings(WatermarkSetting::all());

        // Auto-resolve logo path from public storage
        if (empty($saved['logo_path'])) {
            $saved['logo_path'] = $this->resolveLogoPath();
        }

        $config = array_merge($defaults, $saved, $overrides);

        // A bundled font is what makes font_size mean anything: without a .ttf
        // the GD driver falls back to a fixed-size bitmap face that ignores both
        // size and alignment, which is how the stamp ended up mid-frame.
        if (empty($config['font_path'])) {
            $config['font_path'] = $this->resolveFontPath();
        }

        // Opacity may arrive as 0–1 (config) or 10–100 (the settings slider).
        $config['opacity'] = $config['opacity'] > 1
            ? min(1.0, $config['opacity'] / 100)
            : $config['opacity'];

        $config['position'] = $this->normalisePosition($config['position']);

        // Inject doctor name from file relationships if not explicitly overridden
        if (empty($config['doctor_name']) && $file->relationLoaded('visit') && $file->visit?->doctor) {
            $config['doctor_name'] = $file->visit->doctor->name;
        }

        return $config;
    }

    // ── Text Building ──────────────────────────────────────────────────────────

    /**
     * Build the watermark text string from enabled elements.
     *
     * Default output: "Tulip Dental  |  Root Canal"
     *
     * There is deliberately NO patient-name branch here. Do not add one: a name
     * burned into an exported image cannot be withdrawn later, and every other
     * patient-identifying surface in this module (Case Library, Education) is
     * anonymised precisely so these files can be shared.
     */
    private function buildWatermarkText(ClinicalFile $file, array $config): string
    {
        $parts = [];

        if ($config['show_clinic_name'] && ! empty($config['clinic_name'])) {
            $parts[] = $config['clinic_name'];
        }

        if ($config['show_doctor_name'] && ! empty($config['doctor_name'])) {
            $parts[] = 'Dr. ' . $config['doctor_name'];
        }

        if ($config['show_treatment'] && ! empty($file->procedure)) {
            $parts[] = $file->procedure;
        }

        if ($config['show_stage'] && ! empty($file->stage) && $file->stage !== 'general') {
            $parts[] = ucfirst($file->stage);
        }

        if ($config['show_tooth_number'] && ! empty($file->tooth_number)) {
            $parts[] = 'Tooth ' . $file->tooth_number;
        }

        if ($config['show_date']) {
            $date    = $file->captured_at ?? $file->created_at ?? now();
            $parts[] = $date->format('d M Y');
        }

        return implode('  |  ', $parts);
    }

    // ── Image Manipulation ─────────────────────────────────────────────────────

    /**
     * Apply semi-transparent text in the configured corner.
     */
    private function applyTextWatermark(\Intervention\Image\Interfaces\ImageInterface $image, string $text, array $config): void
    {
        $width  = $image->width();
        $height = $image->height();
        $margin = 14;
        $size   = (int) ($config['font_size'] ?? 12);
        $alpha  = (int) round(($config['opacity'] ?? 0.70) * 255); // 0–255 for Intervention v3
        $color  = \Intervention\Image\Colors\Rgb\Color::create(255, 255, 255, $alpha);

        [$x, $y, $hAlign, $vAlign] = $this->resolvePosition($config['position'], $width, $height, $margin);

        $fontPath = $config['font_path'] ?? null;

        $image->text($text, $x, $y, function (\Intervention\Image\Typography\FontFactory $font) use ($size, $color, $hAlign, $vAlign, $fontPath) {
            if ($fontPath) {
                $font->filename($fontPath);
            }
            $font->size($size);
            $font->color($color);
            $font->align($hAlign);
            $font->valign($vAlign);
        });
    }

    /**
     * Overlay a logo image in the opposite corner from the text, or as configured.
     */
    private function applyLogo(\Intervention\Image\Interfaces\ImageInterface $image, array $config): void
    {
        $logoPath = $config['logo_path'];

        if (! $logoPath || ! file_exists($logoPath)) {
            return;
        }

        try {
            $driver  = $this->resolveDriver();
            $manager = new \Intervention\Image\ImageManager($driver);
            $logo    = $manager->read($logoPath);

            // Scale logo to max 120px wide, preserving aspect ratio
            $logo->scaleDown(120);

            $margin = 12;
            $lw     = $logo->width();
            $lh     = $logo->height();
            $iw     = $image->width();
            $ih     = $image->height();

            // Place logo in top-left by default (opposite of bottom-right text)
            $px = ($config['position'] === 'bottom-left') ? $iw - $lw - $margin : $margin;
            $py = ($config['position'] === 'top-left')    ? $ih - $lh - $margin : $margin;

            $image->place($logo, 'top-left', $px, $py);

        } catch (\Throwable $e) {
            // Logo failure should never block the text watermark
            Log::warning("WatermarkService: logo overlay failed — {$e->getMessage()}");
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Resolve x, y, horizontal align, vertical align from position string.
     */
    private function resolvePosition(string $position, int $width, int $height, int $margin): array
    {
        return match ($position) {
            'top-left'     => [$margin,          $margin,           'left',  'top'],
            'top-right'    => [$width - $margin,  $margin,           'right', 'top'],
            'bottom-left'  => [$margin,          $height - $margin, 'left',  'bottom'],
            'center'       => [(int)($width / 2), (int)($height / 2), 'center', 'middle'],
            default        => [$width - $margin,  $height - $margin, 'right', 'bottom'], // bottom-right
        };
    }

    /**
     * Resolve the Intervention Image driver.
     * Prefers GD (always available in Laragon); falls back to Imagick if GD missing.
     */
    private function resolveDriver(): \Intervention\Image\Interfaces\DriverInterface
    {
        if (extension_loaded('gd')) {
            return new \Intervention\Image\Drivers\Gd\Driver();
        }
        if (extension_loaded('imagick')) {
            return new \Intervention\Image\Drivers\Imagick\Driver();
        }
        throw new \RuntimeException('WatermarkService: neither GD nor Imagick extension is loaded.');
    }

    /**
     * Translate the settings screen's key names into the service's own, and
     * drop anything unknown. Accepts both spellings so an older settings file
     * keeps working.
     *
     * @param  array<string,mixed>  $saved
     * @return array<string,mixed>
     */
    private function normaliseSavedSettings(array $saved): array
    {
        $map = [
            'wm_enabled'      => 'enabled',
            'wm_clinic_name'  => 'show_clinic_name',
            'wm_treatment'    => 'show_treatment',
            'wm_doctor_name'  => 'show_doctor_name',
            'wm_stage'        => 'show_stage',
            'wm_tooth_number' => 'show_tooth_number',
            'wm_date'         => 'show_date',
            'wm_logo'         => 'show_logo',
            'wm_position'     => 'position',
            'wm_opacity'      => 'opacity',
            'wm_font_size'    => 'font_size',
        ];

        $out = [];

        foreach ($saved as $key => $value) {
            $target = $map[$key] ?? $key;

            // wm_patient_name is accepted by the old settings form and is
            // deliberately discarded here — see buildWatermarkText().
            if ($key === 'wm_patient_name' || $target === 'show_patient_name') {
                continue;
            }

            if (str_starts_with($target, 'show_') || $target === 'enabled') {
                $out[$target] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $out[$target] = $value;
        }

        return $out;
    }

    /**
     * Accept both machine values ('bottom-right') and the settings screen's
     * human labels ('Bottom Right'). Anything unrecognised falls back to
     * bottom-right rather than centre — a watermark across the middle of a
     * clinical photo destroys the thing it is supposed to protect.
     */
    private function normalisePosition(mixed $position): string
    {
        $slug = str_replace(' ', '-', strtolower(trim((string) $position)));

        return in_array($slug, ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'], true)
            ? $slug
            : 'bottom-right';
    }

    /**
     * Absolute path to the bundled watermark typeface, or null if it is missing
     * (in which case GD's built-in bitmap font is used and font_size is ignored).
     */
    private function resolveFontPath(): ?string
    {
        $candidates = [
            resource_path('fonts/DejaVuSans-Bold.ttf'),
            public_path('fonts/DejaVuSans-Bold.ttf'),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Look for a clinic logo in common locations under public storage.
     * Returns absolute path or null.
     */
    private function resolveLogoPath(): ?string
    {
        $candidates = [
            public_path('images/clinic-logo.png'),
            public_path('images/clinic-logo.jpg'),
            public_path('images/logo.png'),
            storage_path('app/public/clinic-logo.png'),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build the watermarked file path — sibling of the original, prefixed with "wm_".
     *
     * Example: patients/5/clinical-files/abc123.jpg
     *       →  patients/5/clinical-files/wm_abc123.jpg
     *
     * Always outputs .jpg regardless of input extension (JPEG is the watermark output format).
     */
    private function buildWatermarkedPath(string $originalPath): string
    {
        $dir      = dirname($originalPath);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);

        return ($dir !== '.') ? "{$dir}/wm_{$filename}.jpg" : "wm_{$filename}.jpg";
    }
}
