<?php

namespace Database\Seeders;

use App\Models\DocumentationProtocol;
use App\Models\DocumentationProtocolStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DocumentationProtocolSeeder — Phase 11
 *
 * Seeds the 6 default clinical documentation protocols.
 * Run: php artisan db:seed --class=DocumentationProtocolSeeder
 *
 * Safe to re-run: uses firstOrCreate on procedure_type to avoid duplicates.
 */
class DocumentationProtocolSeeder extends Seeder
{
    public function run(): void
    {
        $protocols = [

            // ── 1. Root Canal Treatment (6 steps) ───────────────────────────
            [
                'name'          => 'Root Canal Treatment Protocol',
                'procedure_type'=> 'Root Canal',
                'description'   => 'Standard documentation for endodontic RCT procedures.',
                'apply_to_new_visits' => true,
                'is_active'     => true,
                'sort_order'    => 1,
                'steps' => [
                    ['name' => 'Pre-op IOPA X-ray',          'file_type' => 'xray',    'stage' => 'before',   'is_required' => true,  'sort_order' => 1, 'description' => 'Periapical X-ray before treatment.'],
                    ['name' => 'Pre-treatment Clinical Photo','file_type' => 'photo',   'stage' => 'before',   'is_required' => false, 'sort_order' => 2, 'description' => 'Frontal / occlusal photo before isolation.'],
                    ['name' => 'Working Length X-ray',        'file_type' => 'xray',    'stage' => 'during',   'is_required' => true,  'sort_order' => 3, 'description' => 'File in canal at working length.'],
                    ['name' => 'Master Cone Verification',    'file_type' => 'xray',    'stage' => 'during',   'is_required' => true,  'sort_order' => 4, 'description' => 'Master GP cone seated X-ray.'],
                    ['name' => 'Post-obturation X-ray',       'file_type' => 'xray',    'stage' => 'after',    'is_required' => true,  'sort_order' => 5, 'description' => 'Final X-ray confirming obturation length.'],
                    ['name' => 'Patient Consent Form',        'file_type' => 'consent', 'stage' => 'before',   'is_required' => true,  'sort_order' => 6, 'description' => 'Signed RCT consent.'],
                ],
            ],

            // ── 2. Dental Implant (8 steps) ─────────────────────────────────
            [
                'name'          => 'Dental Implant Protocol',
                'procedure_type'=> 'Implant',
                'description'   => 'Full implant workflow from planning through osseointegration.',
                'apply_to_new_visits' => true,
                'is_active'     => true,
                'sort_order'    => 2,
                'steps' => [
                    ['name' => 'Pre-op OPG / CBCT',           'file_type' => 'cbct',    'stage' => 'before',   'is_required' => true,  'sort_order' => 1, 'description' => 'Full-arch or regional CBCT for implant planning.'],
                    ['name' => 'Pre-op Clinical Photo',        'file_type' => 'photo',   'stage' => 'before',   'is_required' => true,  'sort_order' => 2, 'description' => 'Frontal smile and occlusal arch photo.'],
                    ['name' => 'Intra-op Osteotomy Photo',     'file_type' => 'photo',   'stage' => 'during',   'is_required' => false, 'sort_order' => 3, 'description' => 'Osteotomy site before implant placement.'],
                    ['name' => 'Implant Placement X-ray',      'file_type' => 'xray',    'stage' => 'during',   'is_required' => true,  'sort_order' => 4, 'description' => 'Peri-apical confirming implant depth and angulation.'],
                    ['name' => 'Suture Photo',                 'file_type' => 'photo',   'stage' => 'during',   'is_required' => false, 'sort_order' => 5, 'description' => 'Sutured site after implant placement.'],
                    ['name' => 'Patient Consent Form',         'file_type' => 'consent', 'stage' => 'before',   'is_required' => true,  'sort_order' => 6, 'description' => 'Signed implant surgery consent.'],
                    ['name' => 'Osseointegration Check X-ray', 'file_type' => 'xray',    'stage' => 'followup', 'is_required' => true,  'sort_order' => 7, 'description' => 'X-ray at 3–6 month review confirming osseointegration.'],
                    ['name' => 'Final Crown Delivery Photo',   'file_type' => 'photo',   'stage' => 'after',    'is_required' => true,  'sort_order' => 8, 'description' => 'Final crown seated — frontal and occlusal.'],
                ],
            ],

            // ── 3. Crown Preparation (5 steps) ──────────────────────────────
            [
                'name'          => 'Crown Preparation Protocol',
                'procedure_type'=> 'Crown',
                'description'   => 'Documentation protocol for crown prep and delivery.',
                'apply_to_new_visits' => true,
                'is_active'     => true,
                'sort_order'    => 3,
                'steps' => [
                    ['name' => 'Pre-prep X-ray',              'file_type' => 'xray',    'stage' => 'before',   'is_required' => true,  'sort_order' => 1, 'description' => 'Periapical before crown preparation.'],
                    ['name' => 'Pre-prep Clinical Photo',      'file_type' => 'photo',   'stage' => 'before',   'is_required' => false, 'sort_order' => 2, 'description' => 'Pre-op smile and occlusal photo.'],
                    ['name' => 'Impression / Digital Scan',   'file_type' => 'stl',     'stage' => 'during',   'is_required' => true,  'sort_order' => 3, 'description' => 'Impression or intraoral scan STL file.'],
                    ['name' => 'Lab Slip',                    'file_type' => 'lab_slip', 'stage' => 'during',  'is_required' => true,  'sort_order' => 4, 'description' => 'Lab work order with shade, material, instructions.'],
                    ['name' => 'Final Crown Delivery Photo',  'file_type' => 'photo',   'stage' => 'after',    'is_required' => true,  'sort_order' => 5, 'description' => 'Post-delivery smile and occlusal photo.'],
                ],
            ],

            // ── 4. Extraction (3 steps) ──────────────────────────────────────
            [
                'name'          => 'Extraction Protocol',
                'procedure_type'=> 'Extraction',
                'description'   => 'Minimum documentation for simple and surgical extractions.',
                'apply_to_new_visits' => true,
                'is_active'     => true,
                'sort_order'    => 4,
                'steps' => [
                    ['name' => 'Pre-op X-ray',                'file_type' => 'xray',    'stage' => 'before',   'is_required' => true,  'sort_order' => 1, 'description' => 'Periapical or OPG showing tooth to be extracted.'],
                    ['name' => 'Patient Consent Form',         'file_type' => 'consent', 'stage' => 'before',   'is_required' => true,  'sort_order' => 2, 'description' => 'Signed extraction consent form.'],
                    ['name' => 'Post-extraction Socket Photo', 'file_type' => 'photo',   'stage' => 'after',    'is_required' => false, 'sort_order' => 3, 'description' => 'Socket after tooth removal — for records.'],
                ],
            ],

            // ── 5. Aligner / Orthodontic Treatment (6 steps) ────────────────
            [
                'name'          => 'Aligner Treatment Protocol',
                'procedure_type'=> 'Aligner',
                'description'   => 'Clear aligner documentation from records to final result.',
                'apply_to_new_visits' => true,
                'is_active'     => true,
                'sort_order'    => 5,
                'steps' => [
                    ['name' => 'Pre-treatment OPG',           'file_type' => 'opg',     'stage' => 'before',   'is_required' => true,  'sort_order' => 1, 'description' => 'Full-arch OPG before aligner therapy.'],
                    ['name' => 'Pre-treatment Intraoral Scan', 'file_type' => 'stl',    'stage' => 'before',   'is_required' => true,  'sort_order' => 2, 'description' => 'Digital impressions for aligner fabrication.'],
                    ['name' => 'Pre-treatment Smile Photo',   'file_type' => 'photo',   'stage' => 'before',   'is_required' => true,  'sort_order' => 3, 'description' => 'Frontal smile + profile + occlusal photo set.'],
                    ['name' => 'Patient Consent Form',         'file_type' => 'consent', 'stage' => 'before',   'is_required' => true,  'sort_order' => 4, 'description' => 'Signed aligner treatment consent.'],
                    ['name' => 'Mid-treatment Progress Photo', 'file_type' => 'photo',  'stage' => 'during',   'is_required' => false, 'sort_order' => 5, 'description' => 'Progress smile photo at mid-point.'],
                    ['name' => 'Final Result Photo',           'file_type' => 'photo',  'stage' => 'after',    'is_required' => true,  'sort_order' => 6, 'description' => 'Post-treatment full smile + occlusal photos.'],
                ],
            ],

            // ── 6. Scaling & Polishing (2 steps) ────────────────────────────
            [
                'name'          => 'Scaling & Polishing Protocol',
                'procedure_type'=> 'Scaling',
                'description'   => 'Basic periodontal maintenance documentation.',
                'apply_to_new_visits' => false,
                'is_active'     => true,
                'sort_order'    => 6,
                'steps' => [
                    ['name' => 'Pre-scaling Clinical Photo',  'file_type' => 'photo',   'stage' => 'before',   'is_required' => false, 'sort_order' => 1, 'description' => 'Gingival condition and calculus deposits before scaling.'],
                    ['name' => 'Post-scaling Photo',          'file_type' => 'photo',   'stage' => 'after',    'is_required' => false, 'sort_order' => 2, 'description' => 'Post-scaling gingival result.'],
                ],
            ],

        ];

        foreach ($protocols as $protocolData) {
            $steps = $protocolData['steps'];
            unset($protocolData['steps']);

            // firstOrCreate prevents duplicate seeding
            $protocol = DocumentationProtocol::firstOrCreate(
                ['procedure_type' => $protocolData['procedure_type']],
                $protocolData
            );

            // Sync steps: only insert steps that don't already exist (by name)
            foreach ($steps as $stepData) {
                DocumentationProtocolStep::firstOrCreate(
                    [
                        'protocol_id' => $protocol->id,
                        'name'        => $stepData['name'],
                    ],
                    array_merge($stepData, ['protocol_id' => $protocol->id])
                );
            }
        }

        $this->command->info('DocumentationProtocolSeeder: 6 protocols seeded.');
    }
}
