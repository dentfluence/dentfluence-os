<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WatermarkSetting;

/**
 * CmsController — what is left of the old Content Manager backend.
 *
 * index(), clinical(), marketing(), patientView() and sharedViewData() are gone
 * (P4). index() had no route at all and carried ~150 lines duplicating
 * ClinicalLibraryController's case-grouping; the other three rendered the same
 * Blade with the pre-P3 variable shape and would now fail on it, since that view
 * expects $activeTab and $files. Their URLs still work — routes/cms.php redirects
 * them to the tab they meant, so a bookmark lands somewhere sensible instead of
 * on an error.
 *
 * Watermark settings are the only thing this class still owns.
 */
class CmsController extends Controller
{
    /**
     * POST /content-management/watermark-settings
     *
     * Saves every key WatermarkService actually reads. It used to save five keys
     * that the service never looked at (it spoke a different vocabulary
     * entirely), posted from field names that did not exist anywhere on the
     * settings page — so no watermark setting had ever changed a single pixel.
     *
     * There is deliberately NO patient-name option, and the legacy
     * wm_patient_name key is discarded by the service if an old settings file
     * still carries one. A name burned into an image cannot be withdrawn later,
     * and these files exist to be shared.
     */
    public function saveWatermarkSettings(Request $request)
    {
        $validated = $request->validate([
            'wm_position'    => ['required', \Illuminate\Validation\Rule::in(['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'])],
            'wm_opacity'     => ['required', 'integer', 'min:10', 'max:100'],
            'wm_font_size'   => ['required', 'integer', 'min:10', 'max:120'],
            'watermark_logo' => ['nullable', 'image', 'max:2048'],
        ]);

        // Unchecked boxes are absent from the POST, so each one is read
        // explicitly — otherwise switching an element OFF would silently do
        // nothing, which is exactly how this screen behaved before.
        $data = [
            'wm_enabled'      => $request->boolean('wm_enabled'),
            'wm_clinic_name'  => $request->boolean('wm_clinic_name'),
            'wm_treatment'    => $request->boolean('wm_treatment'),
            'wm_doctor_name'  => $request->boolean('wm_doctor_name'),
            'wm_stage'        => $request->boolean('wm_stage'),
            'wm_tooth_number' => $request->boolean('wm_tooth_number'),
            'wm_date'         => $request->boolean('wm_date'),
            'wm_logo'         => $request->boolean('wm_logo'),
            'wm_position'     => $validated['wm_position'],
            'wm_opacity'      => (int) $validated['wm_opacity'],
            'wm_font_size'    => (int) $validated['wm_font_size'],
        ];

        if ($request->hasFile('watermark_logo')) {
            // Same path WatermarkService::resolveLogoPath() looks in. The two
            // used to point at different folders, so an uploaded logo was
            // saved and then never found.
            \Illuminate\Support\Facades\Storage::disk('public')
                ->putFileAs('settings', $request->file('watermark_logo'), 'watermark_logo.png');
        }

        WatermarkSetting::save($data);

        return back()->with('success', 'Watermark settings saved. New uploads are stamped with these — use "Re-stamp existing files" to apply them to what is already in the library.');
    }
}
