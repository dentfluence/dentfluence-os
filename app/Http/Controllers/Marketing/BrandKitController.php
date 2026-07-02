<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\BrandKit;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class BrandKitController extends Controller
{
    private const CLINIC_ID  = 1;
    private const LOGO_DISK  = 'public';
    private const LOGO_PATH  = 'marketing/logos/1';

    // -------------------------------------------------------------------------
    // Show — load brand kit for the clinic
    // -------------------------------------------------------------------------
    public function index(): View
    {
        $kit = BrandKit::forClinic(self::CLINIC_ID);

        // Shape data to match what the Blade view expects
        $brandKit = [
            'clinic_name'       => $kit->clinic_name,
            'phone'             => $kit->phone,
            'email'             => $kit->email,
            'website'           => $kit->website,
            'address'           => $kit->address,
            'whatsapp'          => $kit->whatsapp_number,
            'instagram'         => $kit->instagram_handle,
            'facebook_url'      => $kit->facebook_page,
            'google_biz_url'    => $kit->google_business_name,
            'colors'            => collect($kit->colors ?? [])->map(fn($c) => [
                'label' => $c['name']  ?? 'Color',
                'hex'   => $c['hex']   ?? '#6366f1',
            ])->toArray(),
            'heading_font'      => $kit->font_primary,
            'body_font'         => $kit->font_secondary,
            'cta_text'          => is_array($kit->default_ctas) ? ($kit->default_ctas[0] ?? 'Book Appointment') : 'Book Appointment',
            'cta_url'           => $kit->website,
            'hashtags'          => $kit->default_hashtags ?? [],
            'ai_tone'           => $kit->ai_tone,
            'brand_description' => $kit->ai_brand_voice_notes,
            'logo_primary'      => $kit->logo_primary ? Storage::disk(self::LOGO_DISK)->url($kit->logo_primary) : null,
            'logo_light'        => $kit->logo_light   ? Storage::disk(self::LOGO_DISK)->url($kit->logo_light)   : null,
            'logo_dark'         => $kit->logo_dark    ? Storage::disk(self::LOGO_DISK)->url($kit->logo_dark)    : null,
            'logo_icon'         => $kit->logo_icon    ? Storage::disk(self::LOGO_DISK)->url($kit->logo_icon)    : null,
        ];

        return view('marketing.brand-kit.index', compact('brandKit'));
    }

    // -------------------------------------------------------------------------
    // Store Logo — AJAX single-logo upload (called per upload zone)
    // -------------------------------------------------------------------------
    public function storeLogo(Request $request): JsonResponse
    {
        $request->validate([
            'field' => 'required|in:logo_primary,logo_light,logo_dark,logo_icon',
            'file'  => 'required|file|mimes:png,jpg,jpeg,svg+xml,svg|max:5120',
        ]);

        $kit   = BrandKit::forClinic(self::CLINIC_ID);
        $field = $request->input('field');

        // Delete old file if exists
        if ($kit->$field) {
            Storage::disk(self::LOGO_DISK)->delete($kit->$field);
        }

        // Store new file
        $path = $request->file('file')->store(self::LOGO_PATH, self::LOGO_DISK);

        $kit->update([$field => $path, 'updated_by' => auth()->id()]);

        return response()->json([
            'success' => true,
            'url'     => Storage::disk(self::LOGO_DISK)->url($path),
            'field'   => $field,
        ]);
    }

    // -------------------------------------------------------------------------
    // Update — upsert brand kit
    // -------------------------------------------------------------------------
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'clinic_name'        => 'nullable|string|max:255',
            'phone'              => 'nullable|string|max:30',
            'email'              => 'nullable|email',
            'website'            => 'nullable|url',
            'address'            => 'nullable|string',
            'whatsapp_number'    => 'nullable|string|max:30',
            'instagram_handle'   => 'nullable|string|max:100',
            'facebook_page'      => 'nullable|string|max:255',
            'google_business_name'=> 'nullable|string|max:255',
            'font_primary'       => 'nullable|string|max:100',
            'font_secondary'     => 'nullable|string|max:100',
            'default_ctas'       => 'nullable|array',
            'default_hashtags'   => 'nullable|array',
            'ai_tone'            => 'nullable|in:professional,friendly,educational,motivational',
            'ai_brand_voice_notes'=> 'nullable|string',
            'ai_focus_treatments'=> 'nullable|array',
            'colors'             => 'nullable|array',
            // Logo upload
            'logo_primary'       => 'nullable|file|image|max:5120',
        ]);

        $kit = BrandKit::forClinic(self::CLINIC_ID);

        // Handle logo upload
        if ($request->hasFile('logo_primary')) {
            // Remove old logo
            if ($kit->logo_primary) {
                Storage::disk(self::LOGO_DISK)->delete($kit->logo_primary);
            }
            $validated['logo_primary'] = $request->file('logo_primary')
                ->store(self::LOGO_PATH, self::LOGO_DISK);
        } else {
            unset($validated['logo_primary']); // don't overwrite with null
        }

        $kit->update(array_merge($validated, ['updated_by' => auth()->id()]));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Brand Kit saved.');
    }
}
