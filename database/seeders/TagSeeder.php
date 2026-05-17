<?php
// ─── database/seeders/TagSeeder.php ──────────────────────────────────────

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            // ── Financial ───────────────────────────────────────────────
            ['group' => 'Financial', 'name' => 'High Value',          'color' => '#92400e', 'bg_color' => '#fef3c7', 'is_system' => true],
            ['group' => 'Financial', 'name' => 'Price Sensitive',      'color' => '#b45309', 'bg_color' => '#fffbeb'],
            ['group' => 'Financial', 'name' => 'Insurance Patient',    'color' => '#1e40af', 'bg_color' => '#eff6ff'],
            ['group' => 'Financial', 'name' => 'EMI Preferred',        'color' => '#065f46', 'bg_color' => '#ecfdf5'],
            ['group' => 'Financial', 'name' => 'Outstanding Balance',  'color' => '#991b1b', 'bg_color' => '#fef2f2'],

            // ── Treatment Interest ────────────────────────────────────────
            ['group' => 'Treatment Interest', 'name' => 'Implant Prospect',      'color' => '#5b21b6', 'bg_color' => '#ede9fe'],
            ['group' => 'Treatment Interest', 'name' => 'Aligner Prospect',      'color' => '#1d4ed8', 'bg_color' => '#dbeafe'],
            ['group' => 'Treatment Interest', 'name' => 'Veneer Prospect',       'color' => '#0e7490', 'bg_color' => '#ecfeff'],
            ['group' => 'Treatment Interest', 'name' => 'Smile Design Interest', 'color' => '#be185d', 'bg_color' => '#fdf2f8'],
            ['group' => 'Treatment Interest', 'name' => 'Whitening Interest',    'color' => '#ca8a04', 'bg_color' => '#fefce8'],
            ['group' => 'Treatment Interest', 'name' => 'Full Mouth Rehab',      'color' => '#7c3aed', 'bg_color' => '#f5f3ff'],

            // ── Behavior ─────────────────────────────────────────────────
            ['group' => 'Behavior', 'name' => 'Nervous Patient',       'color' => '#9f1239', 'bg_color' => '#fff1f2'],
            ['group' => 'Behavior', 'name' => 'Needs Explanation',     'color' => '#1e3a5f', 'bg_color' => '#f0f9ff'],
            ['group' => 'Behavior', 'name' => 'Evening Preferred',     'color' => '#4c1d95', 'bg_color' => '#f5f3ff'],
            ['group' => 'Behavior', 'name' => 'Morning Preferred',     'color' => '#78350f', 'bg_color' => '#fffbeb'],
            ['group' => 'Behavior', 'name' => 'Frequently Cancels',    'color' => '#7f1d1d', 'bg_color' => '#fef2f2'],
            ['group' => 'Behavior', 'name' => 'Punctual',              'color' => '#14532d', 'bg_color' => '#f0fdf4'],

            // ── Relationship ─────────────────────────────────────────────
            ['group' => 'Relationship', 'name' => 'Referral Patient',  'color' => '#5b21b6', 'bg_color' => '#ede9fe'],
            ['group' => 'Relationship', 'name' => 'Family Patient',    'color' => '#166534', 'bg_color' => '#f0fdf4'],
            ['group' => 'Relationship', 'name' => 'VIP Patient',       'color' => '#92400e', 'bg_color' => '#fef3c7', 'is_system' => true],
            ['group' => 'Relationship', 'name' => 'Long Term Patient', 'color' => '#1e40af', 'bg_color' => '#eff6ff'],
            ['group' => 'Relationship', 'name' => 'Pediatric',         'color' => '#be185d', 'bg_color' => '#fdf2f8'],

            // ── Recall ────────────────────────────────────────────────────
            ['group' => 'Recall', 'name' => 'Recall Due',              'color' => '#b45309', 'bg_color' => '#fffbeb'],
            ['group' => 'Recall', 'name' => 'Recall Overdue',          'color' => '#991b1b', 'bg_color' => '#fef2f2'],
            ['group' => 'Recall', 'name' => 'Follow-up Sensitive',     'color' => '#0e7490', 'bg_color' => '#ecfeff'],
            ['group' => 'Recall', 'name' => 'WhatsApp Preferred',      'color' => '#166534', 'bg_color' => '#f0fdf4'],
        ];

        foreach ($tags as $i => $tag) {
            Tag::firstOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($tag['name'])],
                array_merge($tag, [
                    'is_system'  => $tag['is_system'] ?? false,
                    'sort_order' => $i,
                ])
            );
        }
    }
}
