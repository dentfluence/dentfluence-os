<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\StaffActivityLog;
use App\Models\EmiProvider;
use App\Models\EmiScheme;
use App\Models\Complaint;
use App\Models\DentalCondition;
use App\Models\Diagnosis;
use App\Models\Investigation;
use App\Models\Material;
use App\Models\Brand;
use App\Models\MedicalCondition;
use App\Models\Medicine;
use App\Models\MessageTemplate;
use App\Models\Module;
use App\Models\PatientSource;
use App\Models\Role;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventorySubType;
use App\Models\Operatory;
use App\Modules\Huddle\Models\HuddleSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    // ── Main settings page (tab is passed via ?tab=) ────────────────────────
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'clinic');
        // Normalize merged staff+roles tab
        if (in_array($activeTab, ['staff', 'roles'])) {
            $activeTab = 'staff-roles';
        }

        $clinic        = AppSetting::group('clinic');
        $notifications = AppSetting::group('notifications');
        $billing       = AppSetting::group('billing');
        $print         = AppSetting::group('print');

        $staff = User::with('roleModel')->orderBy('name')->get();
        $roles = Role::withCount('users')->orderBy('id')->get();
        // Note: modules/allPermissions moved to HR → Roles & Permissions

        // Masters
        $treatments       = Treatment::orderBy('name')->get();
        $complaints       = Complaint::orderBy('name')->get();
        // withCount('treatmentOptions') also feeds the Knowledge Bank tab —
        // same list, just needs the ranked-option count alongside it.
        $diagnoses        = Diagnosis::withCount('treatmentOptions')->orderBy('name')->get();
        $investigations   = Investigation::orderBy('name')->get();
        // Phase 4 — Material/Brand masters (docs/gap-analysis-treatment-planning-knowledge-bank.md)
        $materials        = Material::orderBy('name')->get();
        $brands           = Brand::with('material')->orderBy('name')->get();

        // Clinical
        $medicines        = Medicine::orderBy('name')->get();

        // Patient defaults
        $medicalConditions = MedicalCondition::orderBy('name')->get();
        $dentalConditions  = DentalCondition::orderBy('name')->get();
        $patientSources    = PatientSource::orderBy('name')->get();

        // Growth & Comms
        $messageTemplates  = MessageTemplate::orderBy('name')->get();

        // Inventory settings
        $invCategories    = InventoryCategory::withCount('items')->orderBy('sort_order')->orderBy('name')->get();
        $invLocations     = InventoryLocation::orderBy('sort_order')->orderBy('name')->get();
        $invSubTypes      = InventorySubType::with('category')->orderBy('name')->get();
        $invSettings      = DB::table('inventory_settings')->orderBy('group')->orderBy('id')->get()->keyBy('key');

        // Huddle settings (keyed by setting key)
        $branchId         = auth()->user()?->branch_id;
        $huddleSettings   = HuddleSetting::where('branch_id', $branchId)->get()->pluck('value', 'key');

        // EMI Providers for Finance/Billing tab
        $emiProviders = EmiProvider::with('schemes')->orderBy('name')->get();

        // Operatories (Clinic tab)
        $operatories = Operatory::forBranch($branchId)->ordered()->get();

        // Calendar preferences
        $calendarPrefs = AppSetting::group('calendar');

        // Note: PRE (Relationship Engine) feature flags moved to their own
        // module-scoped settings page (2026-07-03) — see
        // App\Http\Controllers\Relationship\SettingsController and
        // /relationship/settings. Moved so PRE can be sold/configured as a
        // standalone module without depending on this Settings module.
        // App-wide flags that are NOT PRE-specific (Communication Guard,
        // Integrations, Workflow Engine, Search) still belong here — see
        // the "Cross-App Flags" tab below.
        $featureFlags = app(\App\Support\Features\FeatureFlagService::class)->all();
        $flagGroups = [
            'Safety & Guardrails' => ['guard.fail_closed', 'guard.consent_required', 'guard.full_8factor'],
            'Communication'       => ['comm.single_gateway', 'notifications.single_store'],
            'Workflow'            => ['workflow.engine', 'marketing.via_guard'],
            'Search'              => ['search.index'],
            'Integrations'        => ['integration.whatsapp', 'integration.google', 'integration.meta', 'integration.website', 'integration.payments', 'integration.abdm'],
        ];

        return view('settings.index', compact(
            'activeTab', 'clinic', 'notifications', 'billing', 'print',
            'staff', 'roles',
            'treatments', 'complaints', 'diagnoses', 'investigations',
            'materials', 'brands',
            'medicines', 'medicalConditions', 'dentalConditions', 'patientSources',
            'messageTemplates',
            'invCategories', 'invLocations', 'invSubTypes', 'invSettings',
            'huddleSettings', 'emiProviders',
            'operatories', 'calendarPrefs',
            'featureFlags', 'flagGroups'
        ));
    }

    // ── Cross-app feature flags (NOT PRE-specific): toggle one flag globally ──
    // Whitelist-checked against config/features.php so only declared flags
    // can ever be written — never trusts the key from the request alone.
    public function toggleFeatureFlag(Request $request)
    {
        $request->validate([
            'key'     => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        $key = $request->string('key')->toString();

        if (! array_key_exists($key, (array) config('features.flags', []))) {
            return response()->json(['ok' => false, 'message' => 'Unknown flag — refusing to set it.'], 422);
        }

        app(\App\Support\Features\FeatureFlagService::class)->set(
            $key,
            $request->boolean('enabled'),
            null, // global override, not per-clinic
            'Toggled from Settings by ' . (auth()->user()->name ?? 'admin')
        );

        return response()->json([
            'ok'      => true,
            'key'     => $key,
            'enabled' => $request->boolean('enabled'),
        ]);
    }

    // ── Save clinic profile ─────────────────────────────────────────────────
    public function saveClinic(Request $request)
    {
        $data = $request->validate([
            'clinic_name'     => 'required|string|max:120',
            'clinic_tagline'  => 'nullable|string|max:200',
            'clinic_phone'    => 'nullable|string|max:20',
            'clinic_email'    => 'nullable|email|max:120',
            'clinic_address'  => 'nullable|string|max:300',
            'clinic_city'     => 'nullable|string|max:80',
            'clinic_gst_no'   => 'nullable|string|max:20',
            'clinic_logo'     => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('clinic_logo')) {
            $path = $request->file('clinic_logo')->store('settings', 'public');
            $data['clinic_logo'] = $path;
        }

        AppSetting::setMany($data, 'clinic');

        return back()->with('success', 'Clinic profile saved.');
    }

    // ── Save calendar display preferences ─────────────────────────────────
    public function saveCalendarPrefs(Request $request)
    {
        $request->validate([
            'card_style'   => 'required|in:strip,filled',
            'color_source' => 'required|in:doctor,treatment',
        ]);

        AppSetting::setMany([
            'calendar_card_style'   => $request->card_style,
            'calendar_color_source' => $request->color_source,
        ], 'calendar');

        return back()->with('success', 'Calendar preferences saved.');
    }

    // ── Save patient ID settings ───────────────────────────────────────────
    public function savePatientId(Request $request)
    {
        $request->validate([
            'patient_id_auto'   => ['nullable', 'boolean'],
            'patient_id_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-_]*$/'],
            'patient_id_start'  => ['nullable', 'integer', 'min:1'],
            'patient_id_digits' => ['nullable', 'integer', 'min:3', 'max:8'],
        ]);

        AppSetting::setMany([
            'patient_id_auto'   => $request->boolean('patient_id_auto') ? '1' : '0',
            'patient_id_prefix' => strtoupper(trim($request->patient_id_prefix ?? 'DF')),
            'patient_id_start'  => $request->patient_id_start ?? 1,
            'patient_id_digits' => $request->patient_id_digits ?? 5,
        ], 'patients');

        return back()->with('success', 'Patient ID settings saved.');
    }

    // ── Save notifications preferences ─────────────────────────────────────
    public function saveNotifications(Request $request)
    {
        $keys = [
            'notif_appointment_reminder', 'notif_followup_due',
            'notif_new_lead', 'notif_task_assigned',
            'notif_whatsapp', 'notif_sms', 'notif_email',
        ];

        $data = [];
        foreach ($keys as $k) {
            $data[$k] = $request->boolean($k) ? '1' : '0';
        }

        AppSetting::setMany($data, 'notifications');

        return back()->with('success', 'Notification preferences saved.');
    }

    // ── EMI Providers CRUD ──────────────────────────────────────────────────

    public function storeEmiProvider(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'contact' => 'nullable|string|max:200']);
        EmiProvider::create(['name' => $request->name, 'contact' => $request->contact, 'created_by' => auth()->id()]);
        return back()->with('success', 'EMI provider added.');
    }

    public function toggleEmiProvider(EmiProvider $emiProvider)
    {
        $emiProvider->update(['is_active' => !$emiProvider->is_active]);
        return back()->with('success', 'Provider updated.');
    }

    public function storeEmiScheme(Request $request, EmiProvider $emiProvider)
    {
        $request->validate([
            'scheme_name'         => 'required|string|max:100',
            'tenure_months'       => 'required|integer|min:1|max:84',
            'upfront_emis'        => 'required|integer|min:0|max:12',
            'clinic_interest_rate'=> 'required|numeric|min:0|max:50',
            'gst_on_interest'     => 'required|numeric|min:0|max:30',
            'pass_cost_to_patient'=> 'nullable|boolean',
        ]);

        EmiScheme::create([
            'emi_provider_id'       => $emiProvider->id,
            'scheme_name'           => $request->scheme_name,
            'tenure_months'         => $request->tenure_months,
            'upfront_emis'          => $request->upfront_emis,
            'clinic_interest_rate'  => $request->clinic_interest_rate,
            'gst_on_interest'       => $request->gst_on_interest,
            'pass_cost_to_patient'  => (bool) $request->pass_cost_to_patient,
            'created_by'            => auth()->id(),
        ]);

        return back()->with('success', 'Scheme added.');
    }

    public function toggleEmiScheme(EmiScheme $emiScheme)
    {
        $emiScheme->update(['is_active' => !$emiScheme->is_active]);
        return back()->with('success', 'Scheme updated.');
    }

    public function toggleEmiSchemeCostPassthrough(EmiScheme $emiScheme)
    {
        $emiScheme->update(['pass_cost_to_patient' => !$emiScheme->pass_cost_to_patient]);
        return back()->with('success', 'Convenience charge setting updated.');
    }

    // ── AJAX: schemes for a provider (used by payment modal) ────────────────

    public function emiSchemesForProvider(Request $request)
    {
        $request->validate(['provider_id' => 'required|integer|exists:emi_providers,id']);
        $invoiceTotal = (float) ($request->invoice_total ?? 0);

        $schemes = EmiScheme::where('emi_provider_id', $request->provider_id)
            ->where('is_active', true)
            ->get()
            ->map(fn($s) => array_merge(['id' => $s->id], $s->breakdown($invoiceTotal)));

        return response()->json($schemes);
    }

    // ── Save billing settings ───────────────────────────────────────────────
    public function saveBilling(Request $request)
    {
        $data = $request->validate([
            'invoice_prefix'   => 'nullable|string|max:10',
            'invoice_next_no'  => 'nullable|integer|min:1',
            'currency_symbol'  => 'nullable|string|max:5',
            'tax_label'        => 'nullable|string|max:20',
            'tax_rate'         => 'nullable|numeric|min:0|max:100',
            'payment_upi'      => 'nullable|string|max:60',
            'payment_bank'     => 'nullable|string|max:200',
            // Credit-card convenience fee (configurable threshold + rate)
            'cc_convenience_threshold' => 'nullable|numeric|min:0',
            'cc_convenience_rate'      => 'nullable|numeric|min:0|max:100',
            // Monthly revenue target (optional, shown on Finance dashboard)
            'monthly_revenue_target'   => 'nullable|numeric|min:0',
        ]);

        // Default the convenience-fee keys so they always exist after a save
        $data['cc_convenience_threshold'] = $request->input('cc_convenience_threshold', 10000);
        $data['cc_convenience_rate']      = $request->input('cc_convenience_rate', 2.5);

        // Checkbox: absent from the request means "off"
        $data['show_revenue_target'] = $request->boolean('show_revenue_target');

        AppSetting::setMany($data, 'billing');

        return back()->with('success', 'Billing settings saved.');
    }

    // ── Save print settings ─────────────────────────────────────────────────
    public function savePrint(Request $request)
    {
        // Header type
        AppSetting::set('print_header_type', $request->input('print_header_type', 'plain'), 'print');

        // Letterhead image upload
        if ($request->hasFile('print_letterhead')) {
            $path = $request->file('print_letterhead')->store('settings', 'public');
            AppSetting::set('print_letterhead', $path, 'print');
        }

        // Margins (inches)
        foreach (['top', 'bottom', 'left', 'right'] as $side) {
            $key = "print_margin_{$side}";
            if ($request->boolean("print_margin_{$side}_enabled")) {
                AppSetting::set($key, $request->input($key, '0.5'), 'print');
            } else {
                AppSetting::set($key, '', 'print');
            }
        }

        // Section toggles
        $sections = ['vital_signs','complaints','notes','investigations','diagnosis','treatments','remarks','followup'];
        foreach ($sections as $s) {
            AppSetting::set("print_section_{$s}", $request->boolean("print_section_{$s}") ? '1' : '0', 'print');
        }

        // Treatment plan print footer (Terms & Validity + benefit note).
        // One line per bullet; an emptied box hides that block on the printout.
        AppSetting::set('tp_valid_days', (string) max(1, (int) $request->input('tp_valid_days', 15)), 'print');
        foreach (['tp_terms', 'tp_benefit_title', 'tp_benefit_text'] as $key) {
            AppSetting::set($key, trim((string) $request->input($key, '')), 'print');
        }

        return back()->with('success', 'Print settings saved.');
    }

    // ── Staff: invite / create ──────────────────────────────────────────────
    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email',
            'phone'       => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:80',
            'role_id'     => 'nullable|exists:roles,id',
            'password'    => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['role']     = 'staff'; // legacy field

        User::create($data);

        return back()->with('success', 'Staff member added.');
    }

    // ── Staff: update full profile ──────────────────────────────────────────
    public function updateStaff(Request $request, User $user)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email,' . $user->id,
            'phone'       => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:80',
            'color'       => 'nullable|string|max:7',
            'role_id'     => 'nullable|exists:roles,id',
        ]);

        $oldRole = $user->roleModel?->name ?? 'None';
        $user->update($data);

        // Capture wasChanged() BEFORE refresh() resets dirty tracking
        $roleChanged    = $user->wasChanged('role_id');
        $changes = [];
        if ($user->wasChanged('name'))        $changes[] = 'name';
        if ($user->wasChanged('email'))       $changes[] = 'email';
        if ($user->wasChanged('phone'))       $changes[] = 'phone';
        if ($user->wasChanged('designation')) $changes[] = 'designation';

        $user->refresh()->load('roleModel');

        if ($roleChanged) {
            StaffActivityLog::record(
                $user->id,
                'role_changed',
                $oldRole,
                $user->roleModel?->name ?? 'None',
            );
        }

        if ($changes) {
            StaffActivityLog::record(
                $user->id,
                'profile_updated',
                null,
                null,
                'Changed: ' . implode(', ', $changes),
            );
        }

        return response()->json(['ok' => true, 'user' => [
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'designation' => $user->designation,
            'role_id'     => $user->role_id,
            'role_name'   => $user->roleModel?->name,
            'role_color'  => $user->roleModel?->color,
        ]]);
    }

    // ── Staff: toggle active (password-gated) ───────────────────────────────
    public function toggleStaff(Request $request, User $user)
    {
        // Verify the acting admin's own password
        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->password, auth()->user()->password)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Incorrect password. Action not allowed.',
            ], 403);
        }

        $wasActive  = $user->is_active;
        $user->update(['is_active' => ! $wasActive]);

        StaffActivityLog::record(
            $user->id,
            $user->is_active ? 'activated' : 'deactivated',
            $wasActive  ? 'Active'   : 'Inactive',
            $user->is_active ? 'Active' : 'Inactive',
        );

        return response()->json(['ok' => true, 'is_active' => $user->is_active]);
    }

    // ── Staff: update role ──────────────────────────────────────────────────
    public function updateStaffRole(Request $request, User $user)
    {
        $request->validate(['role_id' => 'nullable|exists:roles,id']);
        $oldRole = $user->roleModel?->name ?? 'None';
        $user->update(['role_id' => $request->role_id]);
        $user->refresh()->load('roleModel');

        StaffActivityLog::record(
            $user->id,
            'role_changed',
            $oldRole,
            $user->roleModel?->name ?? 'None',
        );

        return response()->json(['ok' => true]);
    }

    // ── Staff: activity log (AJAX, last 100 entries) ────────────────────────
    public function activityLog()
    {
        $logs = StaffActivityLog::with(['user', 'performer'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'staff_name'  => $l->user?->name  ?? '—',
                'by_name'     => $l->performer?->name ?? '—',
                'action'      => $l->action,
                'action_label'=> $l->actionLabel(),
                'action_color'=> $l->actionColor(),
                'old_value'   => $l->old_value,
                'new_value'   => $l->new_value,
                'note'        => $l->note,
                'ip'          => $l->ip_address,
                'time'        => $l->created_at?->format('d M Y, h:i A'),
                'time_ago'    => $l->created_at?->diffForHumans(),
            ]);

        return response()->json($logs);
    }

    // ── Inventory / Procurement Settings ────────────────────────────────────
    public function saveInventorySettings(Request $request)
    {
        $request->validate([
            'grn_correction_window_hours' => 'required|integer|min:0|max:168',
        ]);

        AppSetting::set(
            'grn_correction_window_hours',
            (int) $request->grn_correction_window_hours,
            'inventory'
        );

        return back()->with('success', 'Inventory settings saved.');
    }

    // ── Clinical Library Settings (Phase 6 — static UI only) ───────────────
    public function clinicalLibrary()
    {
        return view('settings.clinical-library');
    }
}
