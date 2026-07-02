<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabCasePrescription — structured clinical prescription for one lab case.
 *
 * One-to-one with LabCase. Dynamic clinical fields are stored as JSON
 * and vary by treatment category. The FIELD_SCHEMA constant defines
 * what fields each category exposes — consumed by the Blade builder.
 *
 * Smart suggestions are auto-generated based on category and remind
 * the dentist to include relevant records (photos, scans, etc.).
 */
class LabCasePrescription extends Model
{
    protected $fillable = [
        'lab_case_id', 'template_id',
        'material', 'shade', 'stump_shade',
        'clinical_fields', 'smart_suggestions',
        'suggestions_acknowledged', 'special_instructions',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'clinical_fields'          => 'array',
        'smart_suggestions'        => 'array',
        'suggestions_acknowledged' => 'boolean',
    ];

    // ── Clinical field schema — single source of truth ───────────────────
    //
    // Each key is a treatment category (must match LabCase::WORK_CATEGORIES).
    // Each value is an array of field definitions consumed by the Blade builder.
    //
    // Types: text | select | textarea | boolean | number
    // 'options' required for select type.

    public const FIELD_SCHEMA = [

        'Crown & Bridge' => [
            ['key' => 'margin_type',    'label' => 'Margin Type',       'type' => 'select',
             'options' => ['Subgingival (0.5mm)', 'Subgingival (1mm)', 'Equigingival', 'Supragingival', 'Shoulder', 'Chamfer', 'Feather Edge']],
            ['key' => 'contacts',       'label' => 'Contacts',          'type' => 'select',
             'options' => ['Tight', 'Medium', 'Open', 'No Contact Required']],
            ['key' => 'occlusion',      'label' => 'Occlusion',         'type' => 'select',
             'options' => ['In Occlusion', 'Out of Occlusion (10 microns)', 'Light Contact', 'Heavy Contact', 'Canine Guidance', 'Group Function']],
            ['key' => 'translucency',   'label' => 'Translucency',      'type' => 'select',
             'options' => ['Low (Opaque)', 'Medium', 'High (Translucent)', 'Ultra High (Full Translucent)']],
            ['key' => 'surface_texture','label' => 'Surface Texture',   'type' => 'select',
             'options' => ['Smooth', 'Minimal Characterization', 'High Characterization', 'Match Existing Teeth']],
            ['key' => 'staining',       'label' => 'Staining / Glaze',  'type' => 'select',
             'options' => ['Full Glaze', 'Matte Finish', 'Minimal Staining', 'Custom Characterization', 'None']],
            ['key' => 'pontic_design',  'label' => 'Pontic Design (Bridge)', 'type' => 'select',
             'options' => ['', 'Ovate', 'Modified Ridge Lap', 'Saddle', 'Sanitary', 'Conical']],
            ['key' => 'connector_size', 'label' => 'Connector Size (Bridge)',  'type' => 'select',
             'options' => ['', '9 mm²', '12 mm²', '16 mm²', 'As per lab recommendation']],
            ['key' => 'stump_shade_note', 'label' => 'Stump Shade Notes', 'type' => 'text',   'placeholder' => 'e.g. Discoloured, post-core, heavily restored'],
            ['key' => 'opposing_arch',  'label' => 'Opposing Arch',     'type' => 'select',
             'options' => ['Natural Teeth', 'Full Denture', 'Partial Denture', 'Implant Supported', 'Night Guard']],
            ['key' => 'try_in_required','label' => 'Try-in Required',   'type' => 'boolean'],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea', 'placeholder' => 'Any other clinical instructions for the lab…'],
        ],

        'Implant Prosthesis' => [
            ['key' => 'implant_system', 'label' => 'Implant System',    'type' => 'select',
             'options' => ['Nobel Biocare', 'Straumann', 'Osstem', 'Megagen', 'Zimmer Biomet', 'Dentsply Sirona', 'BioHorizons', 'Other']],
            ['key' => 'implant_platform','label' => 'Platform Size',    'type' => 'select',
             'options' => ['3.0 mm', '3.5 mm', '4.0 mm', '4.1 mm', '4.5 mm', '4.8 mm', '5.0 mm', '5.5 mm', '6.0 mm']],
            ['key' => 'retention',      'label' => 'Retention Type',    'type' => 'select',
             'options' => ['Screw Retained', 'Cement Retained']],
            ['key' => 'ti_base',        'label' => 'Ti-Base Required',  'type' => 'boolean'],
            ['key' => 'scanbody_used',  'label' => 'Scanbody Used',     'type' => 'text',     'placeholder' => 'e.g. Osstem TSBA, Nobel Multi-unit'],
            ['key' => 'emergence_profile','label' => 'Emergence Profile','type' => 'select',
             'options' => ['Narrow', 'Standard', 'Wide', 'Customized']],
            ['key' => 'torque_value',   'label' => 'Torque Value',      'type' => 'text',     'placeholder' => 'e.g. 35 Ncm'],
            ['key' => 'screw_access',   'label' => 'Screw Access Position', 'type' => 'select',
             'options' => ['Palatal / Lingual', 'Central Fossa', 'Buccal (unavoidable)', 'As per lab']],
            ['key' => 'occlusion',      'label' => 'Occlusion',         'type' => 'select',
             'options' => ['In Occlusion', 'Out of Occlusion (10 microns)', 'Light Contact', 'No Occlusal Contact']],
            ['key' => 'margin_type',    'label' => 'Margin / Finish Line', 'type' => 'select',
             'options' => ['Subgingival', 'Equigingival', 'Supragingival', 'Follow Emergence Profile']],
            ['key' => 'material',       'label' => 'Crown Material',    'type' => 'select',
             'options' => ['Full Zirconia', 'Layered Zirconia', 'E-max', 'PFM', 'Full Metal', 'PMMA (Temp)']],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea', 'placeholder' => 'Implant position, angulation, tissue shape…'],
        ],

        'Removable Prosthesis' => [
            ['key' => 'vdo',            'label' => 'Vertical Dimension of Occlusion (VDO)', 'type' => 'select',
             'options' => ['Maintain Existing VDO', 'Increase by 2mm', 'Increase by 3mm', 'Increase by 4mm', 'Increase by 5mm', 'Decrease by 2mm', 'As per wax rim']],
            ['key' => 'midline',        'label' => 'Midline',           'type' => 'select',
             'options' => ['Centered', 'Shift Right by 1mm', 'Shift Right by 2mm', 'Shift Left by 1mm', 'Shift Left by 2mm', 'As per existing']],
            ['key' => 'smile_line',     'label' => 'Smile Line',        'type' => 'select',
             'options' => ['High Smile Line', 'Medium Smile Line', 'Low Smile Line', 'Follow Lip Line']],
            ['key' => 'lip_support',    'label' => 'Lip Support',       'type' => 'select',
             'options' => ['Adequate', 'Needs Moderate Support', 'Needs Maximum Support', 'Reduce Bulk']],
            ['key' => 'tooth_mould',    'label' => 'Tooth Mould',       'type' => 'text',     'placeholder' => 'e.g. SR Vivodent PE 3A / Vita MFT / Phonares'],
            ['key' => 'tooth_shade',    'label' => 'Tooth Shade',       'type' => 'text',     'placeholder' => 'e.g. A2, B1, Vita Classical'],
            ['key' => 'gum_shade',      'label' => 'Gum / Flange Color','type' => 'select',
             'options' => ['Light Pink', 'Medium Pink', 'Dark Pink', 'Custom Stippling']],
            ['key' => 'metal_framework','label' => 'Metal Framework (RPD)', 'type' => 'boolean'],
            ['key' => 'clasp_design',   'label' => 'Clasp Design (RPD)','type' => 'select',
             'options' => ['', 'Akers (Circle)', 'RPI', 'Ring Clasp', 'T-Bar', 'Y-Bar', 'As per lab']],
            ['key' => 'try_in_stage',   'label' => 'Try-in Stage Required', 'type' => 'select',
             'options' => ['Wax Try-in', 'Metal Framework Try-in', 'Both', 'Skip to Final']],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea', 'placeholder' => 'Ridge form, undercut, special requests…'],
        ],

        'Veneer' => [
            ['key' => 'preparation',    'label' => 'Preparation Type',  'type' => 'select',
             'options' => ['No Prep (Prepless)', 'Minimal Prep (0.3mm)', 'Conventional Prep (0.5mm)', 'Full Prep (0.7mm+)']],
            ['key' => 'margin_type',    'label' => 'Margin',            'type' => 'select',
             'options' => ['Feather Edge', 'Knife Edge', 'Chamfer', 'Butt Joint']],
            ['key' => 'translucency',   'label' => 'Translucency',      'type' => 'select',
             'options' => ['Low (Opaque)', 'Medium', 'High (Translucent)', 'Ultra Translucent']],
            ['key' => 'surface_texture','label' => 'Surface Texture',   'type' => 'select',
             'options' => ['Smooth', 'Minimal Texture', 'High Texture', 'Match Natural Teeth']],
            ['key' => 'incisal_edge',   'label' => 'Incisal Edge',      'type' => 'select',
             'options' => ['Extend Incisal', 'Shorten by 1mm', 'Shorten by 2mm', 'Maintain Current Length', 'Follow Smile Design']],
            ['key' => 'contacts',       'label' => 'Contacts',          'type' => 'select',
             'options' => ['Tight', 'Medium', 'Open', 'No Change']],
            ['key' => 'smile_design_ref','label' => 'Smile Design Reference', 'type' => 'text', 'placeholder' => 'DSD file name / wax-up reference'],
            ['key' => 'mock_up_done',   'label' => 'Mock-up Done',      'type' => 'boolean'],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea', 'placeholder' => 'Colour mapping, characterisation requests…'],
        ],

        'Inlay / Onlay' => [
            ['key' => 'margin_type',    'label' => 'Margin',            'type' => 'select',
             'options' => ['Butt Joint', 'Beveled', 'Chamfer']],
            ['key' => 'contacts',       'label' => 'Contacts',          'type' => 'select',
             'options' => ['Tight', 'Medium', 'Open']],
            ['key' => 'occlusion',      'label' => 'Occlusion',         'type' => 'select',
             'options' => ['In Occlusion', 'Out of Occlusion', 'Light Contact']],
            ['key' => 'cusp_coverage',  'label' => 'Cusp Coverage',     'type' => 'select',
             'options' => ['Inlay (No Cusp Coverage)', 'Onlay (Cusp Coverage)', 'Overlay (Full Coverage)']],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea'],
        ],

        'Orthodontics' => [
            ['key' => 'arch',           'label' => 'Arch',              'type' => 'select',
             'options' => ['Upper Only', 'Lower Only', 'Both Arches']],
            ['key' => 'ipr_required',   'label' => 'IPR Required',      'type' => 'boolean'],
            ['key' => 'attachment_type','label' => 'Attachment Type',   'type' => 'select',
             'options' => ['', 'Composite Attachments', 'No Attachments', 'Precision Cuts Only', 'Elastics Hooks']],
            ['key' => 'aligner_stages', 'label' => 'Number of Stages',  'type' => 'text',     'placeholder' => 'e.g. 20 upper + 18 lower'],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea', 'placeholder' => 'Expansion, rotation details, bite ramps…'],
        ],

        'Occlusal Guard / Splint' => [
            ['key' => 'coverage',       'label' => 'Coverage',          'type' => 'select',
             'options' => ['Full Arch', 'Anterior Deprogrammer', 'Posterior Only', 'NTI (2-3 teeth)']],
            ['key' => 'thickness',      'label' => 'Thickness',         'type' => 'select',
             'options' => ['1mm', '1.5mm', '2mm', '3mm', '4mm (heavy bruxer)']],
            ['key' => 'hardness',       'label' => 'Material Hardness', 'type' => 'select',
             'options' => ['Soft (EVA)', 'Hard (Acrylic)', 'Dual Laminate (Soft inside / Hard outside)']],
            ['key' => 'ramp',           'label' => 'Anterior Ramp',     'type' => 'boolean'],
            ['key' => 'arch',           'label' => 'Arch',              'type' => 'select',
             'options' => ['Upper', 'Lower']],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea'],
        ],

        'Surgical Guide' => [
            ['key' => 'guide_type',     'label' => 'Guide Type',        'type' => 'select',
             'options' => ['Tooth Supported', 'Tissue Supported', 'Bone Supported', 'Implant Supported']],
            ['key' => 'sleeve_type',    'label' => 'Sleeve Type',       'type' => 'select',
             'options' => ['Metal Sleeve', 'Plastic Sleeve', 'Sleeveless', 'As per kit']],
            ['key' => 'implant_system', 'label' => 'Implant System',    'type' => 'text',     'placeholder' => 'System and diameter for which guide is made'],
            ['key' => 'software_used',  'label' => 'Planning Software', 'type' => 'select',
             'options' => ['', 'Nobel Clinician', 'coDiagnostiX', 'Simplant', 'BoneStation', 'Other']],
            ['key' => 'cbct_provided',  'label' => 'CBCT File Provided','type' => 'boolean'],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea'],
        ],

        'Wax-up / Mock-up' => [
            ['key' => 'purpose',        'label' => 'Purpose',           'type' => 'select',
             'options' => ['Diagnostic Wax-up', 'DSD Mock-up', 'Composite Mock-up', 'Patient Approval', 'Treatment Planning']],
            ['key' => 'teeth',          'label' => 'Teeth Involved',    'type' => 'text',     'placeholder' => 'e.g. 13–23 (upper 6 anteriors)'],
            ['key' => 'length_change',  'label' => 'Length Change',     'type' => 'text',     'placeholder' => 'e.g. Extend by 1.5mm, shorten by 1mm'],
            ['key' => 'shape_guide',    'label' => 'Shape Reference',   'type' => 'text',     'placeholder' => 'DSD photo reference / tooth chart'],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea'],
        ],

        'Bleaching Tray' => [
            ['key' => 'reservoir',      'label' => 'Reservoir',         'type' => 'boolean'],
            ['key' => 'scalloped',      'label' => 'Scalloped Margins', 'type' => 'boolean'],
            ['key' => 'thickness',      'label' => 'Tray Thickness',    'type' => 'select',
             'options' => ['Soft (0.9mm)', 'Medium (1mm)', 'Hard (2mm)']],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea'],
        ],

        'Custom Impression Tray' => [
            ['key' => 'stops',          'label' => 'Stops Required',    'type' => 'boolean'],
            ['key' => 'handle_design',  'label' => 'Handle',            'type' => 'select',
             'options' => ['Standard Handle', 'No Handle', 'Extended Handle']],
            ['key' => 'extra_notes',    'label' => 'Additional Notes',  'type' => 'textarea'],
        ],

        'Other' => [
            ['key' => 'extra_notes',    'label' => 'Instructions',      'type' => 'textarea', 'placeholder' => 'Describe the work required…'],
        ],
    ];

    // ── Smart suggestions per category ───────────────────────────────────
    //
    // Shown as friendly reminders when creating a prescription.
    // Never block the workflow.

    public const SMART_SUGGESTIONS = [
        'Crown & Bridge' => [
            '📸 Retracted photo (buccal view)',
            '🦷 Shade photo under natural light',
            '⬆️ Opposing arch scan / model',
            '🔵 Bite scan / occlusal record',
            '📐 Stump shade photo before rubber dam',
        ],
        'Implant Prosthesis' => [
            '🔩 Confirm implant system & platform in prescription',
            '📸 Scanbody in-situ photo',
            '🦷 Shade photo of adjacent natural teeth',
            '📂 STL scan files (upper + lower + bite)',
            '📋 Implant placement report (torque, depth)',
        ],
        'Removable Prosthesis' => [
            '📐 Wax rim / bite record photos',
            '📸 Full face photos (front + profile + smile)',
            '📏 VDO measurement noted in prescription',
            '📋 Midline + smile line marked on record',
            '🦷 Shade + mould selection photo',
        ],
        'Veneer' => [
            '📸 Full smile photo (front)',
            '📸 Retracted buccal photo (prep)',
            '📐 DSD / digital smile design file',
            '🔵 Shade photo under natural light',
            '✅ Composite mock-up approved by patient',
        ],
        'Surgical Guide' => [
            '📂 CBCT DICOM files sent to lab',
            '📂 STL scan (pre-op)',
            '📋 Implant planning report (positions + angulations)',
            '🔩 Confirm implant system + diameter guide is for',
        ],
        'Wax-up / Mock-up' => [
            '📸 Full smile + retracted reference photos',
            '📐 DSD reference if available',
            '📋 Length/shape goals noted in prescription',
        ],
        'Inlay / Onlay'           => ['📐 Bite scan / occlusal record', '📸 Pre-op photo of preparation'],
        'Orthodontics'            => ['📂 STL scans (upper + lower + bite)', '📋 Treatment plan notes'],
        'Occlusal Guard / Splint' => ['📐 Bite record in desired position', '📸 Pre-op photos if relevant'],
        'Bleaching Tray'          => [],
        'Custom Impression Tray'  => [],
        'Other'                   => [],
    ];

    // ── Boot ─────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $rx) {
            $rx->created_by = $rx->created_by ?: auth()->id();
            $rx->updated_by = $rx->updated_by ?: auth()->id();

            // Auto-generate smart suggestions if not already set
            if (empty($rx->smart_suggestions) && $rx->labCase) {
                $category = $rx->labCase->work_category;
                $rx->smart_suggestions = self::SMART_SUGGESTIONS[$category] ?? [];
            }
        });

        static::updating(function (self $rx) {
            $rx->updated_by = auth()->id() ?? $rx->updated_by;
        });
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabPrescriptionTemplate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Return the field definitions for the case's treatment category.
     * Falls back to 'Other' if category not recognised.
     */
    public function fieldSchema(): array
    {
        $category = $this->labCase?->work_category ?? 'Other';
        return self::FIELD_SCHEMA[$category] ?? self::FIELD_SCHEMA['Other'];
    }

    /**
     * Return a field value from clinical_fields JSON, or null.
     */
    public function field(string $key): mixed
    {
        return ($this->clinical_fields ?? [])[$key] ?? null;
    }

    /**
     * True if any clinical field has been filled in.
     */
    public function hasClinicalData(): bool
    {
        $fields = $this->clinical_fields ?? [];
        return collect($fields)->filter(fn($v) => $v !== null && $v !== '' && $v !== false)->isNotEmpty();
    }

    /**
     * Summary line for cards/lists — e.g. "Zirconia · A2 · Subgingival"
     */
    public function summaryLine(): string
    {
        $parts = array_filter([
            $this->material,
            $this->shade ? 'Shade ' . $this->shade : null,
            $this->field('margin_type'),
        ]);
        return implode(' · ', $parts) ?: 'Prescription on file';
    }
}
