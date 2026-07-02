<?php

namespace App\Http\Controllers;

use App\Models\LabCase;
use App\Models\LabVendor;
use App\Models\LabVendorContact;
use App\Models\LabVendorService;
use Illuminate\Http\Request;

/**
 * LabVendorController — lab vendor master (Lab Module v2, Phase 1 enhanced).
 *
 * Vendor CRUD:
 *   GET    /lab-vendors            index()
 *   POST   /lab-vendors            store()
 *   PUT    /lab-vendors/{vendor}   update()
 *   DELETE /lab-vendors/{vendor}   destroy()
 *
 * Contacts (Phase 1):
 *   POST   /lab-vendors/{labVendor}/contacts                    storeContact()
 *   PUT    /lab-vendors/{labVendor}/contacts/{contact}          updateContact()
 *   DELETE /lab-vendors/{labVendor}/contacts/{contact}          destroyContact()
 *
 * Services (Phase 1):
 *   POST   /lab-vendors/{labVendor}/services                    storeService()
 *   PUT    /lab-vendors/{labVendor}/services/{service}          updateService()
 *   DELETE /lab-vendors/{labVendor}/services/{service}          destroyService()
 */
class LabVendorController extends Controller
{
    /* ══════════════════════════════════════════════════════════
       VENDOR CRUD
    ══════════════════════════════════════════════════════════ */

    public function index(Request $request)
    {
        $vendors = LabVendor::with(['contacts', 'services'])
            ->orderBy('name')
            ->get()
            ->map(fn (LabVendor $v) => [
                'id'                      => $v->id,
                'name'                    => $v->name,
                'contact_person'          => $v->contact_person,
                'phone'                   => $v->phone,
                'whatsapp_number'         => $v->whatsapp_number,
                'email'                   => $v->email,
                'digital_email'           => $v->digital_email,
                'address'                 => $v->address,
                'specialties'             => $v->specialties ?? [],
                'default_turnaround_days' => $v->default_turnaround_days,
                'payment_terms'           => $v->payment_terms,
                'is_active'               => $v->is_active,
                'notes'                   => $v->notes,
                // Phase 1 — contacts & services
                'contacts'                => $v->contacts->values(),
                'services'                => $v->services->values(),
                // Vendor analytics (computed live)
                'total_cases'             => $v->totalCases(),
                'total_spend'             => $v->totalSpend(),
                'avg_turnaround'          => $v->averageTurnaroundDays(),
                'delay_rate'              => $v->delayRate(),
                'remake_rate'             => $v->remakeRate(),
                'last_order_date'         => $v->lastOrderDate(),
                // Vendor Intelligence (Phase B)
                'quality_score'           => $v->avgQualityScore(),
                'active_case_count'       => $v->activeCaseCount(),
                'vendor_score'            => $v->vendorScore(),
                'recommendation_badge'    => $v->recommendationBadge(),
                'recommendation_badge_color' => $v->recommendationBadgeColor(),
            ]);

        if ($request->expectsJson() || !view()->exists('lab.vendors')) {
            return response()->json($vendors);
        }

        return view('lab.vendors', compact('vendors'));
    }

    public function store(Request $request)
    {
        $data = $this->validateVendor($request);
        $data['created_by'] = auth()->id();

        $vendor = LabVendor::create($data);

        // Phase 1: auto-sync to Finance so lab spend flows to Finance module
        $vendor->syncToFinance();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'vendor' => $vendor]);
        }

        return back()->with('success', "Lab vendor \"{$vendor->name}\" added and synced to Finance.");
    }

    public function update(Request $request, LabVendor $labVendor)
    {
        $labVendor->update($this->validateVendor($request, $labVendor->id));

        // Phase 1: keep Finance mirror in sync
        $labVendor->refresh()->syncToFinance();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'vendor' => $labVendor]);
        }

        return back()->with('success', 'Lab vendor updated.');
    }

    /** Deactivate (kept for history); blocks deactivation with open cases */
    public function destroy(Request $request, LabVendor $labVendor)
    {
        $openCases = $labVendor->cases()->whereIn('status', LabCase::OPEN_STATUSES)->count();

        if ($openCases > 0) {
            $msg = "Cannot deactivate — {$openCases} open case(s) with this lab.";

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $labVendor->update(['is_active' => false]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', "\"{$labVendor->name}\" deactivated.");
    }

    /* ══════════════════════════════════════════════════════════
       CONTACTS — Phase 1
    ══════════════════════════════════════════════════════════ */

    public function storeContact(Request $request, LabVendor $labVendor)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'role'       => 'nullable|string|max:80',
            'phone'      => 'nullable|string|max:20',
            'whatsapp'   => 'nullable|string|max:20',
            'email'      => 'nullable|email|max:150',
            'is_primary' => 'nullable|boolean',
            'notes'      => 'nullable|string|max:500',
        ]);

        // If this contact is primary, clear existing primary flag
        if (!empty($data['is_primary'])) {
            $labVendor->contacts()->update(['is_primary' => false]);
        }

        $contact = $labVendor->contacts()->create($data);

        return response()->json(['success' => true, 'contact' => $contact]);
    }

    public function updateContact(Request $request, LabVendor $labVendor, LabVendorContact $contact)
    {
        abort_if($contact->lab_vendor_id !== $labVendor->id, 404);

        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'role'       => 'nullable|string|max:80',
            'phone'      => 'nullable|string|max:20',
            'whatsapp'   => 'nullable|string|max:20',
            'email'      => 'nullable|email|max:150',
            'is_primary' => 'nullable|boolean',
            'notes'      => 'nullable|string|max:500',
        ]);

        if (!empty($data['is_primary'])) {
            $labVendor->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }

        $contact->update($data);

        return response()->json(['success' => true, 'contact' => $contact]);
    }

    public function destroyContact(Request $request, LabVendor $labVendor, LabVendorContact $contact)
    {
        abort_if($contact->lab_vendor_id !== $labVendor->id, 404);
        $contact->delete();

        return response()->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════════
       SERVICES — Phase 1
    ══════════════════════════════════════════════════════════ */

    public function storeService(Request $request, LabVendor $labVendor)
    {
        $data = $request->validate([
            'service_name'    => 'required|string|max:150',
            'category'        => 'nullable|string|max:100',
            'default_rate'    => 'required|numeric|min:0',
            'unit'            => 'nullable|string|max:40',
            'turnaround_days' => 'nullable|integer|min:1|max:90',
            'notes'           => 'nullable|string|max:500',
            'is_active'       => 'nullable|boolean',
        ]);

        $service = $labVendor->services()->create($data);

        return response()->json(['success' => true, 'service' => $service]);
    }

    public function updateService(Request $request, LabVendor $labVendor, LabVendorService $service)
    {
        abort_if($service->lab_vendor_id !== $labVendor->id, 404);

        $data = $request->validate([
            'service_name'    => 'required|string|max:150',
            'category'        => 'nullable|string|max:100',
            'default_rate'    => 'required|numeric|min:0',
            'unit'            => 'nullable|string|max:40',
            'turnaround_days' => 'nullable|integer|min:1|max:90',
            'notes'           => 'nullable|string|max:500',
            'is_active'       => 'nullable|boolean',
        ]);

        $service->update($data);

        return response()->json(['success' => true, 'service' => $service]);
    }

    public function destroyService(Request $request, LabVendor $labVendor, LabVendorService $service)
    {
        abort_if($service->lab_vendor_id !== $labVendor->id, 404);
        $service->delete();

        return response()->json(['success' => true]);
    }

    /* ══════════════════════════════════════════════════════════
       PRIVATE HELPERS
    ══════════════════════════════════════════════════════════ */

    private function validateVendor(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                    => 'required|string|max:150|unique:lab_vendors,name' . ($ignoreId ? ",{$ignoreId}" : ''),
            'contact_person'          => 'nullable|string|max:150',
            'phone'                   => 'nullable|string|max:20',
            'whatsapp_number'         => 'nullable|string|max:20',
            'email'                   => 'nullable|email|max:150',
            'digital_email'           => 'nullable|email|max:150',
            'address'                 => 'nullable|string|max:500',
            'specialties'             => 'nullable|array',
            'specialties.*'           => 'string|max:100',
            'notes'                   => 'nullable|string|max:1000',
            'default_turnaround_days' => 'nullable|integer|min:1|max:365',
            'payment_terms'           => 'nullable|in:per_case,monthly_account',
            'is_active'               => 'boolean',
        ]);
    }
}
