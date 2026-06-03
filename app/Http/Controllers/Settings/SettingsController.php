<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Complaint;
use App\Models\DentalCondition;
use App\Models\Diagnosis;
use App\Models\Investigation;
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

        $staff   = User::with('roleModel')->orderBy('name')->get();
        $roles   = Role::withCount('users')->orderBy('id')->get();
        $modules = Module::orderBy('sort_order')->get()->groupBy('section');

        // Masters
        $treatments       = Treatment::orderBy('name')->get();
        $complaints       = Complaint::orderBy('name')->get();
        $diagnoses        = Diagnosis::orderBy('name')->get();
        $investigations   = Investigation::orderBy('name')->get();

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

        return view('settings.index', compact(
            'activeTab', 'clinic', 'notifications', 'billing', 'print',
            'staff', 'roles', 'modules',
            'treatments', 'complaints', 'diagnoses', 'investigations',
            'medicines', 'medicalConditions', 'dentalConditions', 'patientSources',
            'messageTemplates',
            'invCategories', 'invLocations', 'invSubTypes', 'invSettings',
            'huddleSettings'
        ));
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
            'clinic_logo'     => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('clinic_logo')) {
            $path = $request->file('clinic_logo')->store('settings', 'public');
            $data['clinic_logo'] = $path;
        }

        AppSetting::setMany($data, 'clinic');

        return back()->with('success', 'Clinic profile saved.');
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
        ]);

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
            'password'    => 'required|string|min:6',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['role']     = 'staff'; // legacy field

        User::create($data);

        return back()->with('success', 'Staff member added.');
    }

    // ── Staff: toggle active ────────────────────────────────────────────────
    public function toggleStaff(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);
        return response()->json(['is_active' => $user->is_active]);
    }

    // ── Staff: update role ──────────────────────────────────────────────────
    public function updateStaffRole(Request $request, User $user)
    {
        $request->validate(['role_id' => 'nullable|exists:roles,id']);
        $user->update(['role_id' => $request->role_id]);
        return response()->json(['ok' => true]);
    }
}
