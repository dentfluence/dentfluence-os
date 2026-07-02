<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Sensible default colors per treatment category name (case-insensitive match)
    private array $categoryColors = [
        'consultation'        => '#3b82f6', // blue
        'crown'               => '#f59e0b', // amber
        'bridge'              => '#f59e0b',
        'crown & bridge'      => '#f59e0b',
        'periodontics'        => '#10b981', // emerald
        'orthodontics'        => '#8b5cf6', // violet
        'implant'             => '#ef4444', // red
        'implantology'        => '#ef4444',
        'root canal'          => '#f97316', // orange
        'rct'                 => '#f97316',
        'endodontics'         => '#f97316',
        'cosmetic'            => '#ec4899', // pink
        'cosmetic dentistry'  => '#ec4899',
        'pedodontics'         => '#06b6d4', // cyan
        'pediatric'           => '#06b6d4',
        'oral surgery'        => '#6366f1', // indigo
        'surgery'             => '#6366f1',
        'dentures'            => '#84cc16', // lime
        'prosthodontics'      => '#84cc16',
        'whitening'           => '#eab308', // yellow
        'scaling'             => '#14b8a6', // teal
        'teeth cleaning'      => '#14b8a6',
        'x-ray'               => '#64748b', // slate
        'radiology'           => '#64748b',
        'emergency'           => '#dc2626', // red-600
    ];

    // Default palette for doctors (cycles through if more doctors than colors)
    private array $doctorPalette = [
        '#6366f1', // indigo
        '#f59e0b', // amber
        '#10b981', // emerald
        '#ec4899', // pink
        '#3b82f6', // blue
        '#f97316', // orange
        '#8b5cf6', // violet
        '#14b8a6', // teal
    ];

    public function up(): void
    {
        // ── Treatment categories ──────────────────────────────────────
        $categories = DB::table('treatment_categories')->whereNull('color')->orWhere('color', '')->get();

        foreach ($categories as $cat) {
            $nameLower = strtolower($cat->name);
            $color = null;

            // Try exact then substring match
            foreach ($this->categoryColors as $key => $hex) {
                if (str_contains($nameLower, $key)) {
                    $color = $hex;
                    break;
                }
            }

            if ($color) {
                DB::table('treatment_categories')->where('id', $cat->id)->update(['color' => $color]);
            } else {
                // Fallback: pick from palette based on id
                $palette = array_values($this->categoryColors);
                DB::table('treatment_categories')->where('id', $cat->id)
                    ->update(['color' => $palette[$cat->id % count($palette)]]);
            }
        }

        // ── Doctors ───────────────────────────────────────────────────
        $doctors = DB::table('users')->whereNull('color')->orWhere('color', '')->get();
        $i = 0;
        foreach ($doctors as $doctor) {
            DB::table('users')->where('id', $doctor->id)
                ->update(['color' => $this->doctorPalette[$i % count($this->doctorPalette)]]);
            $i++;
        }
    }

    public function down(): void
    {
        // Nothing to reverse — colors are user-editable
    }
};
