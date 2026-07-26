<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrDepartment;
use App\Models\HrShift;
use App\Models\HrStaffDocument;
use App\Models\HrStaffProfile;
use App\Models\HrStaffShift;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class HrStaffController extends Controller
{
    /* ── List all staff ── */

    public function index(Request $request)
    {
        $view = $request->input('view', 'doctors'); // 'doctors' | 'staff'

        $query = User::with(['hrProfile.department', 'currentShift.shift'])
                     ->where('is_active', true);

        // Scope query to current view's role group
        $doctorRoles = \App\Models\User::DOCTOR_ROLES;
        if ($view === 'doctors') {
            $query->where(function ($q) use ($doctorRoles) {
                $q->whereIn('role', $doctorRoles)
                  ->orWhere('name', 'like', 'Dr. %')
                  ->orWhere('name', 'like', 'Dr.%');
            });
        } else {
            $query->whereNotIn('role', $doctorRoles)
                  ->where('name', 'not like', 'Dr. %')
                  ->where('name', 'not like', 'Dr.%');
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->whereHas('hrProfile', fn($q) =>
                $q->where('department_id', $request->department_id)
            );
        }

        // Filter by role (staff view only — specific non-doctor roles)
        if ($request->filled('role') && $view === 'staff') {
            $query->where('role', $request->role);
        }

        // Search by name / email / employee code
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhereHas('hrProfile', fn($p) => $p->where('employee_code', 'like', "%$s%"));
            });
        }

        $allUsers    = $query->orderBy('name')->get();
        $departments = HrDepartment::active()->orderBy('name')->get();

        // Split collections — doctor = any doctor role OR name starts with Dr.
        $isDoctor = fn($u) => in_array($u->role, \App\Models\User::DOCTOR_ROLES) || str_starts_with(trim($u->name), 'Dr.');
        $doctors  = $allUsers->filter($isDoctor);
        $staff    = $allUsers->reject($isDoctor);

        $staffPaginated = null;

        return view('hr.staff.index', compact('doctors', 'staff', 'staffPaginated', 'departments', 'allUsers', 'view'));
    }

    /**
     * Assigning an ACCESS role is a privilege-granting act, so it carries the
     * same owner-level gate as Settings → Roles & Permissions (admin.only).
     *
     * Found during the Phase 1 blocker fix (2026-07-26): the HR staff routes
     * are gated by `module:hr` (VIEW), which meant anyone who could open HR
     * could create a staff login and hand it ANY role — including Admin. Role
     * *configuration* was owner-only; role *assignment* was not.
     *
     * Everything else on these screens (HR details, bank, contact, documents)
     * remains available to any HR user — only the role assignment is gated.
     */
    private function assertMayAssignAccessRole(): void
    {
        abort_unless(
            auth()->user()?->isAdminRole(),
            403,
            'Only the clinic owner/admin can assign an access role.'
        );
    }

    /* ── Show create form ── */

    public function create()
    {
        $departments = HrDepartment::active()->orderBy('name')->get();
        $shifts      = HrShift::active()->orderBy('name')->get();
        // ACCESS roles — the canonical, owner-configured roles table (Settings →
        // Roles & Permissions). Any role the Clinic Owner creates is assignable
        // here the moment it exists; nothing is hardcoded. Phase 1 blocker fix
        // (2026-07-26): this used to be a hardcoded legacy job-title array, so
        // custom roles could be created but never assigned.
        $accessRoles = Role::orderBy('name')->get(['id', 'name', 'slug', 'category']);

        // STAFF TYPE — clinical/HR classification only (doctor detection for
        // scheduling, notification routing, the HR Doctors/Staff split view).
        // NOT authorization: permissions come exclusively from the access role.
        $staffTypes  = [
            'resident_dentist'    => 'Resident Dentist',
            'associate_dentist'   => 'Associate Dentist',
            'visiting_consultant' => 'Visiting Consultant',
            'doctor'              => 'Doctor',
            'assistant'           => 'Assistant',
            'front_desk'          => 'Front Desk',
            'accounts'            => 'Accounts',
            'manager'             => 'Manager',
            'admin'               => 'Admin',
        ];

        return view('hr.staff.create', compact('departments', 'shifts', 'accessRoles', 'staffTypes'));
    }

    /* ── Store new staff + profile ── */

    public function store(Request $request)
    {
        // Creating a login IS granting access — owner-only.
        $this->assertMayAssignAccessRole();

        $request->validate([
            // User fields
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'phone'       => 'nullable|string|max:20',
            // ACCESS role — canonical roles table; any owner-created role is valid.
            'role_id'     => 'required|exists:roles,id',
            // STAFF TYPE — clinical/HR classification, never authorization.
            'role'        => 'required|in:admin,manager,doctor,resident_dentist,associate_dentist,visiting_consultant,front_desk,assistant,accounts',
            'designation' => 'nullable|string|max:255',
            'password'    => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],

            // Profile fields
            'department_id'               => 'nullable|exists:hr_departments,id',
            'employee_code'               => 'nullable|string|unique:hr_staff_profiles,employee_code',
            'joining_date'                => 'nullable|date',
            'employment_type'             => 'nullable|in:full_time,part_time,contract,intern',
            'date_of_birth'               => 'nullable|date',
            'gender'                      => 'nullable|in:male,female,other',
            'blood_group'                 => 'nullable|string|max:5',
            'address'                     => 'nullable|string',
            'emergency_contact_name'      => 'nullable|string|max:255',
            'emergency_contact_phone'     => 'nullable|string|max:20',
            'emergency_contact_relation'  => 'nullable|string|max:100',
            'license_number'              => 'nullable|string|max:100',
            'license_expiry'              => 'nullable|date',
            'qualification'               => 'nullable|string|max:100',
            'specialization'              => 'nullable|string|max:100',
            'salary_type'                 => 'nullable|in:fixed,hourly',
            'basic_salary'                => 'nullable|numeric|min:0',
            'notes'                       => 'nullable|string',

            // Shift
            'shift_id'      => 'nullable|exists:hr_shifts,id',
            'shift_from'    => 'nullable|date',
        ]);

        // 1. Create user account.
        // role_id = the ACCESS role the owner picked (canonical; drives every
        // permission check on web and API). `role` = staff-type classification
        // only (doctor detection / notification routing / HR list split).
        // Phase 1 blocker fix (2026-07-26): role_id is now taken from the form
        // instead of being derived from the legacy string, so a custom role
        // like "Evening Desk" survives exactly as the owner assigned it.
        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'role'        => $request->role,
            'role_id'     => $request->role_id,
            'designation' => $request->designation,
            'password'    => Hash::make($request->password),
            'is_active'   => true,
            'branch_id'   => 1,
        ]);

        // 2. Create HR profile (qr_token auto-generated in model boot)
        $user->hrProfile()->create([
            'department_id'               => $request->department_id,
            'employee_code'               => $request->employee_code,
            'joining_date'                => $request->joining_date,
            'employment_type'             => $request->employment_type ?? 'full_time',
            'date_of_birth'               => $request->date_of_birth,
            'gender'                      => $request->gender,
            'blood_group'                 => $request->blood_group,
            'address'                     => $request->address,
            'emergency_contact_name'      => $request->emergency_contact_name,
            'emergency_contact_phone'     => $request->emergency_contact_phone,
            'emergency_contact_relation'  => $request->emergency_contact_relation,
            'license_number'              => $request->license_number,
            'license_expiry'              => $request->license_expiry,
            'qualification'               => $request->qualification,
            'specialization'              => $request->specialization,
            'salary_type'                 => $request->salary_type ?? 'fixed',
            'basic_salary'                => $request->basic_salary,
            'notes'                       => $request->notes,
        ]);

        // 3. Assign shift if provided
        if ($request->filled('shift_id')) {
            HrStaffShift::create([
                'user_id'        => $user->id,
                'shift_id'       => $request->shift_id,
                'effective_from' => $request->shift_from ?? now()->toDateString(),
            ]);
        }

        return redirect()
            ->route('hr.staff.show', $user)
            ->with('success', "{$user->name} added to HR successfully.");
    }

    /* ── Show single staff profile ── */

    public function show(User $user)
    {
        // Auto-create a blank HR profile if one doesn't exist yet
        // (covers users added via Settings before HR module existed)
        if (! $user->hrProfile) {
            $user->hrProfile()->create([
                'employment_type' => 'full_time',
                'salary_type'     => 'fixed',
            ]);
        }

        $user->load([
            'hrProfile.department',
            'currentShift.shift',
            'hrShifts.shift',
            'hrDocuments.uploadedBy',
        ]);

        // Last 30 days attendance
        $attendance = $user->hrAttendance()
            ->whereBetween('date', [now()->subDays(29), now()])
            ->orderByDesc('date')
            ->get();

        $presentDays = $attendance->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentDays  = $attendance->where('status', 'absent')->count();

        return view('hr.staff.show', compact('user', 'attendance', 'presentDays', 'absentDays'));
    }

    /* ── Show edit form ── */

    public function edit(User $user)
    {
        $user->load([
            'hrProfile.department',
            'currentShift.shift',
            'hrSalary',
            'hrIncentiveRule',
            'hrAdvances',
            'hrBonuses',
            'hrDocuments',
        ]);

        $departments = HrDepartment::active()->orderBy('name')->get();
        $shifts      = HrShift::active()->orderBy('name')->get();
        // ACCESS roles — the canonical, owner-configured roles table (Settings →
        // Roles & Permissions). Any role the Clinic Owner creates is assignable
        // here the moment it exists; nothing is hardcoded. Phase 1 blocker fix
        // (2026-07-26): this used to be a hardcoded legacy job-title array, so
        // custom roles could be created but never assigned.
        $accessRoles = Role::orderBy('name')->get(['id', 'name', 'slug', 'category']);

        // STAFF TYPE — clinical/HR classification only (doctor detection for
        // scheduling, notification routing, the HR Doctors/Staff split view).
        // NOT authorization: permissions come exclusively from the access role.
        $staffTypes  = [
            'resident_dentist'    => 'Resident Dentist',
            'associate_dentist'   => 'Associate Dentist',
            'visiting_consultant' => 'Visiting Consultant',
            'doctor'              => 'Doctor',
            'assistant'           => 'Assistant',
            'front_desk'          => 'Front Desk',
            'accounts'            => 'Accounts',
            'manager'             => 'Manager',
            'admin'               => 'Admin',
        ];

        return view('hr.staff.edit', compact('user', 'departments', 'shifts', 'accessRoles', 'staffTypes'));
    }

    /* ── Update staff + profile ── */

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'       => 'nullable|string|max:20',
            // ACCESS role — optional here because four secondary forms on the
            // edit screen (HR details, bank, contact, documents) post back only
            // their own fields; they must not be able to blank the assignment.
            'role_id'     => 'nullable|exists:roles,id',
            // STAFF TYPE — clinical/HR classification, never authorization.
            'role'        => 'required|in:admin,manager,doctor,resident_dentist,associate_dentist,visiting_consultant,front_desk,assistant,accounts',
            'designation' => 'nullable|string|max:255',

            'department_id'               => 'nullable|exists:hr_departments,id',
            'employee_code'               => ['nullable', 'string', Rule::unique('hr_staff_profiles', 'employee_code')->ignore($user->hrProfile?->id)],
            'joining_date'                => 'nullable|date',
            'employment_type'             => 'nullable|in:full_time,part_time,contract,intern',
            'date_of_birth'               => 'nullable|date',
            'gender'                      => 'nullable|in:male,female,other',
            'blood_group'                 => 'nullable|string|max:5',
            'address'                     => 'nullable|string',
            'emergency_contact_name'      => 'nullable|string|max:255',
            'emergency_contact_phone'     => 'nullable|string|max:20',
            'emergency_contact_relation'  => 'nullable|string|max:100',
            'license_number'              => 'nullable|string|max:100',
            'license_expiry'              => 'nullable|date',
            'qualification'               => 'nullable|string|max:100',
            'specialization'              => 'nullable|string|max:100',
            'salary_type'                 => 'nullable|in:fixed,hourly',
            'basic_salary'                => 'nullable|numeric|min:0',
            'notes'                       => 'nullable|string',
            // Bank
            'bank_name'            => 'nullable|string|max:100',
            'account_holder_name'  => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:50',
            'ifsc_code'            => 'nullable|string|max:20',
            'branch_name'          => 'nullable|string|max:150',
            // Contact
            'whatsapp_number'      => 'nullable|string|max:20',
            'alternate_phone'      => 'nullable|string|max:20',
            'alternate_email'      => 'nullable|email|max:255',
            // Admin password reset (optional — leave blank to keep current password)
            'new_password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Update user
        $user->update([
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'role'        => $request->role,
            'designation' => $request->designation,
        ]);

        // ACCESS role: written ONLY from the owner's explicit choice.
        //
        // Phase 1 blocker fix (2026-07-26). This block previously DERIVED
        // role_id from the legacy job-title string whenever that string changed
        // (or whenever role_id was null), which silently clobbered a custom role
        // the owner had assigned — e.g. "Evening Desk" reverted to Front Desk
        // the next time anyone edited that staff member. Authorization now
        // follows the owner's assignment and nothing else.
        if ($request->filled('role_id') && (int) $request->role_id !== (int) $user->role_id) {
            $this->assertMayAssignAccessRole();
            $user->update(['role_id' => $request->role_id]);
        }

        // Admin-only password reset: only touch the password if a new one was submitted.
        if ($request->filled('new_password') && auth()->user()?->role === 'admin') {
            $user->update(['password' => Hash::make($request->new_password)]);
        }

        // Update or create HR profile
        $user->hrProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'department_id'               => $request->department_id,
                'employee_code'               => $request->employee_code,
                'joining_date'                => $request->joining_date,
                'employment_type'             => $request->employment_type ?? 'full_time',
                'date_of_birth'               => $request->date_of_birth,
                'gender'                      => $request->gender,
                'blood_group'                 => $request->blood_group,
                'address'                     => $request->address,
                'emergency_contact_name'      => $request->emergency_contact_name,
                'emergency_contact_phone'     => $request->emergency_contact_phone,
                'emergency_contact_relation'  => $request->emergency_contact_relation,
                'license_number'              => $request->license_number,
                'license_expiry'              => $request->license_expiry,
                'qualification'               => $request->qualification,
                'specialization'              => $request->specialization,
                'salary_type'                 => $request->salary_type ?? 'fixed',
                'basic_salary'                => $request->basic_salary,
                'notes'                       => $request->notes,
                // Bank details
                'bank_name'            => $request->bank_name,
                'account_holder_name'  => $request->account_holder_name,
                'account_number'       => $request->account_number,
                'ifsc_code'            => strtoupper($request->ifsc_code ?? ''),
                'branch_name'          => $request->branch_name,
                // Contact
                'whatsapp_number'      => $request->whatsapp_number,
                'alternate_phone'      => $request->alternate_phone,
                'alternate_email'      => $request->alternate_email,
            ]
        );

        return redirect()
            ->route('hr.staff.show', $user)
            ->with('success', 'Staff profile updated.');
    }

    /* ── Upload a document for a staff member ── */

    public function storeDocument(Request $request, User $user)
    {
        $request->validate([
            'document_type' => 'required|in:contract,id_proof,certificate,bank,other',
            'label'         => 'required|string|max:255',
            'file'          => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'notes'         => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store("hr/documents/{$user->id}", 'public');

        HrStaffDocument::create([
            'user_id'       => $user->id,
            'document_type' => $request->document_type,
            'label'         => $request->label,
            'file_path'     => $path,
            'file_name'     => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'notes'         => $request->notes,
            'uploaded_by'   => auth()->id(),
        ]);

        return back()->with('success', "Document \"{$request->label}\" uploaded successfully.");
    }

    /* ── Delete a staff document ── */

    public function destroyDocument(User $user, HrStaffDocument $document)
    {
        // Safety: document must belong to this user
        abort_if($document->user_id !== $user->id, 403);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    /* ── Deactivate (soft disable) ── */

    public function destroy(User $user)
    {
        // Never hard delete — just deactivate
        $user->update(['is_active' => false]);

        return redirect()
            ->route('hr.staff.index')
            ->with('success', "{$user->name} has been deactivated.");
    }
}
