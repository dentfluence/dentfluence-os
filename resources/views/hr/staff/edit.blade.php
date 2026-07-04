@extends('layouts.app')
@section('page-title', 'Edit — ' . $user->name)

@push('styles')
<style>
.htab-nav { display:flex; gap:0; border-bottom:2px solid #ede4f3; overflow-x:auto; flex-shrink:0; scrollbar-width:none; -ms-overflow-style:none; }
.htab-nav::-webkit-scrollbar { display:none; }
.htab-btn {
    display:inline-flex; align-items:center; gap:6px; padding:10px 18px;
    font-size:13px; font-weight:500; color:#7a6080; background:none; border:none;
    border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer;
    white-space:nowrap; font-family:'Inter',sans-serif; transition:color .15s, border-color .15s;
}
.htab-btn:hover { color:#3a0050; }
.htab-btn.active { color:#6a0f70; border-bottom-color:#6a0f70; font-weight:600; }
.htab-panel { display:none; }
.htab-panel.active { display:block; }

.fi { width:100%; border:1.5px solid #e5d5f0; border-radius:8px; padding:8px 12px; font-size:13px; color:#1a0320; outline:none; font-family:inherit; background:#fff; }
.fi:focus { border-color:#8b44aa; }
.fl { display:block; font-size:12px; font-weight:600; color:#5a4060; margin-bottom:5px; }
.fsec { background:#fff; border:1.5px solid #ede4f3; border-radius:12px; padding:22px; margin-bottom:18px; }
.fsec-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6a0f70; margin:0 0 16px; }
.save-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 22px; background:#6a0f70; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:background .15s; }
.save-btn:hover { background:#3a0050; }
.grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.grid3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
@media(max-width:640px) { .grid2,.grid3 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div style="font-family:'Inter',sans-serif;max-width:900px;padding:24px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <div>
            <a href="{{ route('hr.staff.show', $user) }}" style="font-size:12.5px;color:#9a7aaa;text-decoration:none;display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Profile
            </a>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;color:#1a0320;margin:0;">
                {{ $user->name }}
            </h1>
        </div>
        <span style="font-size:11px;padding:4px 12px;border-radius:99px;background:#f0e6f6;color:#6a0f70;font-weight:600;">
            {{ ucfirst(str_replace('_',' ',$user->role)) }}
        </span>
    </div>

    @if(session('success'))
    <div style="margin-bottom:16px;padding:11px 16px;background:#e8f7ef;border:1px solid #b8e8cc;border-radius:8px;color:#1a7a45;font-size:13px;">
        ✓ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="margin-bottom:16px;padding:11px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#b91c1c;font-size:13px;">
        @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
    </div>
    @endif

    {{-- ── Horizontal Tab Nav ── --}}
    <div class="htab-nav" id="tabNav">
        <button class="htab-btn active" onclick="switchTab('account',this)">Account</button>
        <button class="htab-btn" onclick="switchTab('hr',this)">HR Details</button>
        <button class="htab-btn" onclick="switchTab('personal',this)">Personal</button>
        <button class="htab-btn" onclick="switchTab('professional',this)">Professional</button>
        <button class="htab-btn" onclick="switchTab('bank',this)">Bank</button>
        <button class="htab-btn" onclick="switchTab('finance',this)" id="financeTabBtn">Finance</button>
        <button class="htab-btn" onclick="switchTab('documents',this)">Documents</button>
    </div>

    <div style="padding-top:24px;">

    {{-- ════ TAB: ACCOUNT ════ --}}
    <div class="htab-panel active" id="panel-account">
        <form method="POST" action="{{ route('hr.staff.update', $user) }}">
            @csrf @method('PATCH')
            <div class="fsec">
                <p class="fsec-title">Login Account</p>
                <div class="grid2">
                    <div>
                        <label class="fl">Full Name *</label>
                        <input type="text" name="name" value="{{ old('name',$user->name) }}" required class="fi">
                    </div>
                    <div>
                        <label class="fl">Email *</label>
                        <input type="email" name="email" value="{{ old('email',$user->email) }}" required class="fi">
                    </div>
                    <div>
                        <label class="fl">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone',$user->phone) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Role *</label>
                        <select name="role" required class="fi">
                            @foreach($roles as $v => $l)
                            <option value="{{ $v }}" @selected(old('role',$user->role)==$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fl">Designation</label>
                        <input type="text" name="designation" value="{{ old('designation',$user->designation) }}" class="fi">
                    </div>
                </div>
            </div>

            @if(auth()->user()?->role === 'admin')
            <div class="fsec">
                <p class="fsec-title">Reset Password</p>
                <p style="font-size:12px;color:#9a85aa;margin:-6px 0 12px;">
                    Leave blank to keep {{ $user->name }}'s current password. Only admins can set this.
                </p>
                <div class="grid2">
                    <div>
                        <label class="fl">New Password</label>
                        <input type="password" name="new_password" autocomplete="new-password" minlength="8" class="fi" placeholder="Leave blank to keep current password">
                        @error('new_password')<p style="color:#c0392b;font-size:12px;margin-top:4px;">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="fl">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation" autocomplete="new-password" minlength="8" class="fi" placeholder="Re-enter new password">
                    </div>
                </div>
            </div>
            @endif

            <button type="submit" class="save-btn">Save Account</button>
        </form>
    </div>

    {{-- ════ TAB: HR DETAILS ════ --}}
    <div class="htab-panel" id="panel-hr">
        <form method="POST" action="{{ route('hr.staff.update', $user) }}">
            @csrf @method('PATCH')
            {{-- Pass non-HR fields as hidden so validation passes --}}
            <input type="hidden" name="name"  value="{{ $user->name }}">
            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="role"  value="{{ $user->role }}">
            <div class="fsec">
                <p class="fsec-title">HR Details</p>
                <div class="grid2">
                    <div>
                        <label class="fl">Department</label>
                        <select name="department_id" class="fi">
                            <option value="">None</option>
                            @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected(old('department_id',$user->hrProfile?->department_id)==$d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fl">Employee Code</label>
                        <input type="text" name="employee_code" value="{{ old('employee_code',$user->hrProfile?->employee_code) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Joining Date</label>
                        <input type="date" name="joining_date" value="{{ old('joining_date',$user->hrProfile?->joining_date?->format('Y-m-d')) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Employment Type</label>
                        <select name="employment_type" class="fi">
                            @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Contract','intern'=>'Intern'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('employment_type',$user->hrProfile?->employment_type)==$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label class="fl">Notes</label>
                        <textarea name="notes" rows="3" class="fi">{{ old('notes',$user->hrProfile?->notes) }}</textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="save-btn">Save HR Details</button>
        </form>
    </div>

    {{-- ════ TAB: PERSONAL ════ --}}
    <div class="htab-panel" id="panel-personal">
        <form method="POST" action="{{ route('hr.staff.update', $user) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="name"  value="{{ $user->name }}">
            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="role"  value="{{ $user->role }}">

            {{-- Personal Info --}}
            <div class="fsec">
                <p class="fsec-title">Personal Info</p>
                <div class="grid3">
                    <div>
                        <label class="fl">Date of Birth</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth',$user->hrProfile?->date_of_birth?->format('Y-m-d')) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Gender</label>
                        <select name="gender" class="fi">
                            <option value="">Select…</option>
                            @foreach(['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l)
                            <option value="{{ $v }}" @selected(old('gender',$user->hrProfile?->gender)==$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fl">Blood Group</label>
                        <select name="blood_group" class="fi">
                            <option value="">Unknown</option>
                            @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
                            <option value="{{ $bg }}" @selected(old('blood_group',$user->hrProfile?->blood_group)==$bg)>{{ $bg }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="margin-top:14px;">
                    <label class="fl">Residential Address</label>
                    <textarea name="address" rows="2" class="fi" placeholder="Full address including city, pincode">{{ old('address',$user->hrProfile?->address) }}</textarea>
                </div>
            </div>

            {{-- Communication --}}
            <div class="fsec">
                <p class="fsec-title">Communication</p>
                <div class="grid2">
                    <div>
                        <label class="fl">WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number',$user->hrProfile?->whatsapp_number) }}" placeholder="+91 9XXXXXXXXX" class="fi">
                    </div>
                    <div>
                        <label class="fl">Alternate Phone</label>
                        <input type="text" name="alternate_phone" value="{{ old('alternate_phone',$user->hrProfile?->alternate_phone) }}" placeholder="Secondary number" class="fi">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label class="fl">Alternate Email</label>
                        <input type="email" name="alternate_email" value="{{ old('alternate_email',$user->hrProfile?->alternate_email) }}" placeholder="Personal email address" class="fi">
                    </div>
                </div>
            </div>

            {{-- Emergency Contact --}}
            <div class="fsec">
                <p class="fsec-title">Emergency Contact</p>
                <div class="grid3">
                    <div>
                        <label class="fl">Name</label>
                        <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name',$user->hrProfile?->emergency_contact_name) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Phone</label>
                        <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone',$user->hrProfile?->emergency_contact_phone) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Relation</label>
                        <input type="text" name="emergency_contact_relation" value="{{ old('emergency_contact_relation',$user->hrProfile?->emergency_contact_relation) }}" class="fi">
                    </div>
                </div>
            </div>

            <button type="submit" class="save-btn">Save Personal Details</button>
        </form>
    </div>

    {{-- ════ TAB: PROFESSIONAL ════ --}}
    <div class="htab-panel" id="panel-professional">
        <form method="POST" action="{{ route('hr.staff.update', $user) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="name"  value="{{ $user->name }}">
            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="role"  value="{{ $user->role }}">
            <div class="fsec">
                <p class="fsec-title">Professional Details</p>
                <div class="grid2">
                    <div>
                        <label class="fl">Qualification</label>
                        <input type="text" name="qualification" value="{{ old('qualification',$user->hrProfile?->qualification) }}" placeholder="BDS, MDS…" class="fi">
                    </div>
                    <div>
                        <label class="fl">Specialization</label>
                        <input type="text" name="specialization" value="{{ old('specialization',$user->hrProfile?->specialization) }}" placeholder="Orthodontics, Implants…" class="fi">
                    </div>
                    <div>
                        <label class="fl">License / Reg. Number</label>
                        <input type="text" name="license_number" value="{{ old('license_number',$user->hrProfile?->license_number) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">License Expiry</label>
                        <input type="date" name="license_expiry" value="{{ old('license_expiry',$user->hrProfile?->license_expiry?->format('Y-m-d')) }}" class="fi">
                    </div>
                </div>
            </div>
            <button type="submit" class="save-btn">Save Professional Details</button>
        </form>
    </div>

    {{-- ════ TAB: BANK ════ --}}
    <div class="htab-panel" id="panel-bank">
        <form method="POST" action="{{ route('hr.staff.update', $user) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="name"  value="{{ $user->name }}">
            <input type="hidden" name="email" value="{{ $user->email }}">
            <input type="hidden" name="role"  value="{{ $user->role }}">
            <div class="fsec">
                <p class="fsec-title">Bank Account Details</p>
                <p style="font-size:12px;color:#9a7aaa;margin:-8px 0 16px;">Confidential — used for salary transfers only.</p>
                <div class="grid2">
                    <div>
                        <label class="fl">Account Holder Name</label>
                        <input type="text" name="account_holder_name" value="{{ old('account_holder_name',$user->hrProfile?->account_holder_name) }}" placeholder="As per bank records" class="fi">
                    </div>
                    <div>
                        <label class="fl">Account Number</label>
                        <input type="text" name="account_number" value="{{ old('account_number',$user->hrProfile?->account_number) }}" class="fi">
                    </div>
                    <div>
                        <label class="fl">Bank Name</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name',$user->hrProfile?->bank_name) }}" placeholder="e.g. HDFC Bank" class="fi">
                    </div>
                    <div>
                        <label class="fl">IFSC Code</label>
                        <input type="text" name="ifsc_code" value="{{ old('ifsc_code',$user->hrProfile?->ifsc_code) }}" placeholder="e.g. HDFC0001234" style="text-transform:uppercase;" class="fi">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label class="fl">Branch Name</label>
                        <input type="text" name="branch_name" value="{{ old('branch_name',$user->hrProfile?->branch_name) }}" class="fi">
                    </div>
                </div>
            </div>
            <button type="submit" class="save-btn">Save Bank Details</button>
        </form>
    </div>

    {{-- ════ TAB: FINANCE ════ --}}
    <div class="htab-panel" id="panel-finance">

        {{-- ── Salary Structure ── --}}
        <form method="POST" action="{{ route('hr.staff.finance.salary', $user) }}">
            @csrf
            <div class="fsec">
                <p class="fsec-title">Salary Structure</p>
                <div class="grid3">
                    <div>
                        <label class="fl">Basic Salary (₹) *</label>
                        <input type="number" name="basic_salary" value="{{ old('basic_salary',$user->hrSalary?->basic_salary ?? 0) }}" min="0" step="0.01" class="fi" id="basicSalInput" oninput="calcGross()">
                    </div>
                    <div>
                        <label class="fl">HRA (₹)</label>
                        <input type="number" name="hra" value="{{ old('hra',$user->hrSalary?->hra ?? 0) }}" min="0" step="0.01" class="fi" oninput="calcGross()">
                    </div>
                    <div>
                        <label class="fl">Conveyance (₹)</label>
                        <input type="number" name="conveyance" value="{{ old('conveyance',$user->hrSalary?->conveyance ?? 0) }}" min="0" step="0.01" class="fi" oninput="calcGross()">
                    </div>
                    <div>
                        <label class="fl">Medical Allowance (₹)</label>
                        <input type="number" name="medical" value="{{ old('medical',$user->hrSalary?->medical ?? 0) }}" min="0" step="0.01" class="fi" oninput="calcGross()">
                    </div>
                    <div>
                        <label class="fl">Special Allowance (₹)</label>
                        <input type="number" name="special" value="{{ old('special',$user->hrSalary?->special ?? 0) }}" min="0" step="0.01" class="fi" oninput="calcGross()">
                    </div>
                    <div style="display:flex;flex-direction:column;justify-content:flex-end;">
                        <label class="fl" style="color:#6a0f70;">Gross Salary</label>
                        <div id="grossDisplay" style="font-size:20px;font-weight:700;color:#6a0f70;padding:6px 0;">₹0</div>
                    </div>
                </div>
                <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;padding-top:16px;border-top:1px solid #f0e8f8;">
                    <div>
                        <label class="fl">OT Multiplier</label>
                        <select name="ot_multiplier" class="fi">
                            <option value="1.5" @selected((old('ot_multiplier',$user->hrSalary?->ot_multiplier ?? 1.5))==1.5)>1.5× (Standard)</option>
                            <option value="2.0" @selected((old('ot_multiplier',$user->hrSalary?->ot_multiplier))==2.0)>2.0× (Double)</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;gap:20px;padding-top:18px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="pf_applicable" value="1" {{ $user->hrSalary?->pf_applicable ? 'checked' : '' }} style="accent-color:#6a0f70;width:15px;height:15px;">
                            PF (12% of basic)
                        </label>
                    </div>
                    <div style="display:flex;align-items:center;gap:20px;padding-top:18px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="esi_applicable" value="1" {{ $user->hrSalary?->esi_applicable ? 'checked' : '' }} style="accent-color:#6a0f70;width:15px;height:15px;">
                            ESI (0.75% of gross)
                        </label>
                    </div>
                </div>
            </div>
            <button type="submit" class="save-btn">Save Salary Structure</button>
        </form>

        {{-- ── Incentive / Compensation Model (doctors only) ── --}}
        @if($user->isDoctor())
        <form method="POST" action="{{ route('hr.staff.finance.incentive', $user) }}" style="margin-top:24px;">
            @csrf
            @php $inc = $user->hrIncentiveRule; @endphp
            <div class="fsec">
                <p class="fsec-title">Compensation Model</p>
                <div style="margin-bottom:16px;">
                    <label class="fl">Compensation Type</label>
                    <select name="compensation_type" class="fi" id="compTypeSelect" onchange="toggleCompFields(this.value)" style="max-width:360px;">
                        @foreach(\App\Models\HrIncentiveRule::$typeLabels as $v => $l)
                        <option value="{{ $v }}" @selected(old('compensation_type',$inc?->compensation_type ?? 'fixed')==$v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Fixed Revenue fields --}}
                <div id="field-fixed_revenue" class="comp-field" style="display:none;">
                    <div class="grid2">
                        <div>
                            <label class="fl">Monthly Revenue Target (₹)</label>
                            <input type="number" name="revenue_target" value="{{ old('revenue_target',$inc?->revenue_target) }}" min="0" step="0.01" class="fi" placeholder="e.g. 80000">
                            <p style="font-size:11px;color:#9a7aaa;margin:4px 0 0;">Incentive kicks in above this amount</p>
                        </div>
                        <div>
                            <label class="fl">Incentive Rate (%)</label>
                            <input type="number" name="incentive_rate" value="{{ old('incentive_rate',$inc?->incentive_rate) }}" min="0" max="100" step="0.01" class="fi" placeholder="e.g. 30">
                            <p style="font-size:11px;color:#9a7aaa;margin:4px 0 0;">% of revenue above target</p>
                        </div>
                    </div>
                </div>
                {{-- Pure Revenue fields --}}
                <div id="field-pure_revenue" class="comp-field" style="display:none;">
                    <div class="grid2">
                        <div>
                            <label class="fl">Incentive Rate (%)</label>
                            <input type="number" name="incentive_rate" value="{{ old('incentive_rate',$inc?->incentive_rate) }}" min="0" max="100" step="0.01" class="fi" placeholder="e.g. 35">
                            <p style="font-size:11px;color:#9a7aaa;margin:4px 0 0;">% of all revenue generated</p>
                        </div>
                        <div>
                            <label class="fl">Minimum Guarantee (₹)</label>
                            <input type="number" name="minimum_guarantee" value="{{ old('minimum_guarantee',$inc?->minimum_guarantee) }}" min="0" step="0.01" class="fi" placeholder="Floor even in slow months">
                        </div>
                    </div>
                </div>
                {{-- Per Patient fields --}}
                <div id="field-per_patient" class="comp-field" style="display:none;">
                    <div class="grid2">
                        <div>
                            <label class="fl">Per Patient Rate (₹)</label>
                            <input type="number" name="per_patient_rate" value="{{ old('per_patient_rate',$inc?->per_patient_rate) }}" min="0" step="0.01" class="fi" placeholder="₹ per patient seen">
                        </div>
                        <div>
                            <label class="fl">Minimum Guarantee (₹)</label>
                            <input type="number" name="minimum_guarantee" value="{{ old('minimum_guarantee',$inc?->minimum_guarantee) }}" min="0" step="0.01" class="fi">
                        </div>
                    </div>
                </div>
                {{-- Fixed Bonus (Front Desk) --}}
                <div id="field-fixed_bonus" class="comp-field" style="display:none;">
                    <div class="grid2">
                        <div>
                            <label class="fl">Monthly Appointment Target</label>
                            <input type="number" name="target_appointments" value="{{ old('target_appointments',$inc?->target_appointments) }}" min="0" class="fi" placeholder="e.g. 120 appointments">
                        </div>
                        <div>
                            <label class="fl">Bonus Amount if Target Met (₹)</label>
                            <input type="number" name="bonus_amount" value="{{ old('bonus_amount',$inc?->bonus_amount) }}" min="0" step="0.01" class="fi">
                        </div>
                    </div>
                </div>
                {{-- Notes --}}
                <div style="margin-top:14px;">
                    <label class="fl">Notes / Custom Terms</label>
                    <textarea name="notes" rows="2" class="fi" placeholder="Any special arrangement…">{{ old('notes',$inc?->notes) }}</textarea>
                </div>
            </div>
            <button type="submit" class="save-btn">Save Compensation Model</button>
        </form>
        @endif

        {{-- ── Advances ── --}}
        <div style="margin-top:24px;" class="fsec">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <p class="fsec-title" style="margin:0;">Advances</p>
                <button type="button" onclick="document.getElementById('addAdvanceForm').classList.toggle('hidden')"
                        style="font-size:12px;color:#6a0f70;background:#f0e6f6;border:none;border-radius:6px;padding:5px 12px;cursor:pointer;">+ Add Advance</button>
            </div>

            {{-- Add advance form --}}
            <div id="addAdvanceForm" class="hidden" style="background:#fdf8ff;border:1px solid #e5d5f0;border-radius:10px;padding:16px;margin-bottom:16px;">
                <form method="POST" action="{{ route('hr.staff.finance.advances.store', $user) }}">
                    @csrf
                    <div class="grid2" style="margin-bottom:12px;">
                        <div>
                            <label class="fl">Amount (₹) *</label>
                            <input type="number" name="principal" required min="100" step="0.01" class="fi" id="advPrincipal" oninput="calcEmiPreview()">
                        </div>
                        <div>
                            <label class="fl">Date Given *</label>
                            <input type="date" name="given_date" required value="{{ date('Y-m-d') }}" class="fi">
                        </div>
                        <div>
                            <label class="fl">Tenure (months) *</label>
                            <input type="number" name="tenure_months" required min="1" max="60" class="fi" id="advMonths" oninput="calcEmiPreview()">
                        </div>
                        <div>
                            <label class="fl">Interest Rate (annual %)</label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <label style="font-size:12.5px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="checkbox" name="with_interest" value="1" id="advInterestChk" onchange="calcEmiPreview()" style="accent-color:#6a0f70;">
                                    Charge interest
                                </label>
                            </div>
                            <input type="number" name="interest_rate" id="advRate" value="0" min="0" max="100" step="0.01" class="fi" style="margin-top:6px;" oninput="calcEmiPreview()">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="fl">Reason</label>
                            <input type="text" name="reason" class="fi" placeholder="e.g. Medical emergency, personal need">
                        </div>
                    </div>
                    <div id="emiPreview" style="background:#f0e6f6;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#6a0f70;display:none;">
                        EMI: <strong id="emiVal">₹0</strong> / month &nbsp;·&nbsp; Total Payable: <strong id="totalVal">₹0</strong>
                    </div>
                    <button type="submit" class="save-btn" style="padding:7px 18px;">Record Advance</button>
                </form>
            </div>

            {{-- Active advances list --}}
            @forelse($user->hrAdvances->where('status','active') as $adv)
            <div style="border:1px solid #ede4f3;border-radius:8px;padding:14px;margin-bottom:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <div>
                        <p style="font-size:13.5px;font-weight:600;color:#1a0320;margin:0;">₹{{ number_format($adv->principal) }}
                            @if($adv->with_interest)<span style="font-size:11px;color:#d97706;margin-left:6px;">{{ $adv->interest_rate }}% p.a.</span>@endif
                        </p>
                        <p style="font-size:12px;color:#9a7aaa;margin:2px 0 0;">
                            Given {{ $adv->given_date->format('d M Y') }} · EMI ₹{{ number_format($adv->emi_amount) }}/mo
                            · {{ $adv->emis_remaining }} EMIs left · Balance ₹{{ number_format($adv->balance) }}
                        </p>
                        @if($adv->reason)<p style="font-size:11.5px;color:#b0a0bb;margin:2px 0 0;">{{ $adv->reason }}</p>@endif
                    </div>
                    <div style="display:flex;gap:6px;">
                        <form method="POST" action="{{ route('hr.staff.finance.advances.close', [$user, $adv]) }}">
                            @csrf <input type="hidden" name="action" value="closed">
                            <button type="submit" style="font-size:11.5px;padding:4px 10px;border:1px solid #b8e8cc;background:#e8f7ef;color:#1a7a45;border-radius:6px;cursor:pointer;">Mark Closed</button>
                        </form>
                        <form method="POST" action="{{ route('hr.staff.finance.advances.close', [$user, $adv]) }}">
                            @csrf <input type="hidden" name="action" value="waived">
                            <button type="submit" style="font-size:11.5px;padding:4px 10px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:6px;cursor:pointer;">Waive</button>
                        </form>
                    </div>
                </div>
                {{-- Progress bar --}}
                @php $pct = $adv->total_payable > 0 ? min(100, ($adv->amount_paid / $adv->total_payable) * 100) : 0; @endphp
                <div style="margin-top:10px;height:4px;background:#f0e8f8;border-radius:99px;overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:#6a0f70;border-radius:99px;"></div>
                </div>
            </div>
            @empty
            <p style="font-size:13px;color:#b0a0bb;text-align:center;padding:16px 0;">No active advances.</p>
            @endforelse

            @if($user->hrAdvances->whereIn('status',['closed','waived'])->count())
            <details style="margin-top:8px;">
                <summary style="font-size:12px;color:#9a7aaa;cursor:pointer;">Show closed / waived advances</summary>
                <div style="margin-top:10px;">
                @foreach($user->hrAdvances->whereIn('status',['closed','waived']) as $adv)
                <div style="padding:10px 14px;border:1px solid #f0e8f8;border-radius:8px;margin-bottom:6px;opacity:.6;">
                    <span style="font-size:13px;font-weight:500;color:#1a0320;">₹{{ number_format($adv->principal) }}</span>
                    <span style="font-size:11px;color:#9a7aaa;margin-left:8px;">{{ ucfirst($adv->status) }} · {{ $adv->given_date->format('d M Y') }}</span>
                </div>
                @endforeach
                </div>
            </details>
            @endif
        </div>

        {{-- ── Bonuses ── --}}
        <div style="margin-top:18px;" class="fsec">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <p class="fsec-title" style="margin:0;">Bonuses</p>
                <button type="button" onclick="document.getElementById('addBonusForm').classList.toggle('hidden')"
                        style="font-size:12px;color:#6a0f70;background:#f0e6f6;border:none;border-radius:6px;padding:5px 12px;cursor:pointer;">+ Add Bonus</button>
            </div>

            <div id="addBonusForm" class="hidden" style="background:#fdf8ff;border:1px solid #e5d5f0;border-radius:10px;padding:16px;margin-bottom:16px;">
                <form method="POST" action="{{ route('hr.staff.finance.bonuses.store', $user) }}">
                    @csrf
                    <div class="grid2" style="margin-bottom:12px;">
                        <div>
                            <label class="fl">Bonus Name *</label>
                            <input type="text" name="bonus_name" required class="fi" placeholder="e.g. Diwali Bonus">
                        </div>
                        <div>
                            <label class="fl">Type *</label>
                            <select name="bonus_type" required class="fi">
                                @foreach(\App\Models\HrBonus::$typeLabels as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="fl">Amount (₹) *</label>
                            <input type="number" name="amount" required min="1" step="0.01" class="fi">
                        </div>
                        <div>
                            <label class="fl">Bonus Date *</label>
                            <input type="date" name="bonus_date" required value="{{ date('Y-m-d') }}" class="fi">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="fl">Notes</label>
                            <input type="text" name="notes" class="fi" placeholder="Optional note">
                        </div>
                    </div>
                    <button type="submit" class="save-btn" style="padding:7px 18px;">Add Bonus</button>
                </form>
            </div>

            @forelse($user->hrBonuses as $bonus)
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid #ede4f3;border-radius:8px;margin-bottom:8px;">
                <div style="width:8px;height:8px;border-radius:50%;background:{{ $bonus->type_color }};flex-shrink:0;"></div>
                <div style="flex:1;">
                    <p style="font-size:13.5px;font-weight:600;color:#1a0320;margin:0;">{{ $bonus->bonus_name }} — ₹{{ number_format($bonus->amount) }}</p>
                    <p style="font-size:11.5px;color:#9a7aaa;margin:2px 0 0;">{{ $bonus->type_label }} · {{ $bonus->bonus_date->format('d M Y') }}</p>
                </div>
                <form method="POST" action="{{ route('hr.staff.finance.bonuses.destroy', [$user, $bonus]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this bonus?')" style="font-size:11px;padding:3px 9px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;border-radius:5px;cursor:pointer;">Delete</button>
                </form>
            </div>
            @empty
            <p style="font-size:13px;color:#b0a0bb;text-align:center;padding:16px 0;">No bonuses recorded yet.</p>
            @endforelse
        </div>

    </div>{{-- /finance panel --}}

    {{-- ════ TAB: DOCUMENTS ════ --}}
    <div class="htab-panel" id="panel-documents">
        <form method="POST" action="{{ route('hr.staff.documents.store', $user) }}" enctype="multipart/form-data">
            @csrf
            <div class="fsec">
                <p class="fsec-title">Upload Document</p>
                <div class="grid2" style="margin-bottom:14px;">
                    <div>
                        <label class="fl">Document Type *</label>
                        <select name="document_type" required class="fi">
                            <option value="">— Select —</option>
                            <option value="contract">Employment Contract</option>
                            <option value="id_proof">ID Proof (Aadhar / PAN / Passport)</option>
                            <option value="certificate">Certificate / Degree</option>
                            <option value="bank">Bank Document</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="fl">Label *</label>
                        <input type="text" name="label" required class="fi" placeholder="e.g. Aadhar Card">
                    </div>
                    <div>
                        <label class="fl">File * <span style="font-size:11px;color:#9a7aaa;">(PDF, JPG, PNG, DOC — max 10MB)</span></label>
                        <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="fi" style="padding:6px;">
                    </div>
                    <div>
                        <label class="fl">Notes</label>
                        <input type="text" name="notes" class="fi" placeholder="Expiry date, issuing authority…">
                    </div>
                </div>
                <button type="submit" class="save-btn">Upload Document</button>
            </div>
        </form>

        {{-- Existing docs --}}
        @if($user->hrDocuments->isEmpty())
        <p style="font-size:13px;color:#b0a0bb;text-align:center;padding:24px;">No documents uploaded yet.</p>
        @else
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($user->hrDocuments as $doc)
            @php $ic = match($doc->document_type){ 'contract'=>'#7c3aed','id_proof'=>'#0891b2','certificate'=>'#059669','bank'=>'#d97706',default=>'#6b7280' }; @endphp
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border:1px solid #ede4f3;border-radius:8px;background:#fff;">
                <div style="width:34px;height:34px;border-radius:8px;background:{{ $ic }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="15" height="15" fill="none" stroke="{{ $ic }}" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:#1a0320;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $doc->label }}</p>
                    <p style="font-size:11.5px;color:#9a7aaa;margin:2px 0 0;">{{ $doc->type_label }} · {{ $doc->file_size_human }} · {{ $doc->created_at->format('d M Y') }}</p>
                </div>
                <a href="{{ Storage::url($doc->file_path) }}" target="_blank"
                   style="font-size:12px;color:#6a0f70;padding:4px 10px;border:1px solid #e5d5f0;border-radius:6px;text-decoration:none;">View</a>
                <form method="POST" action="{{ route('hr.staff.documents.destroy', [$user, $doc]) }}" onsubmit="return confirm('Delete this document?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="font-size:12px;color:#b91c1c;padding:4px 10px;border:1px solid #fecaca;background:#fef2f2;border-radius:6px;cursor:pointer;">Delete</button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    </div>{{-- /tab panels --}}
</div>

@push('scripts')
<script>
// ── Tab switching ──
function switchTab(id, btn) {
    document.querySelectorAll('.htab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.htab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + id).classList.add('active');
    btn.classList.add('active');
}

// ── Open finance tab if hash present ──
if (window.location.hash === '#finance') {
    switchTab('finance', document.getElementById('financeTabBtn'));
}

// ── Gross salary live preview ──
function calcGross() {
    const inputs = ['basic_salary','hra','conveyance','medical','special'];
    let total = 0;
    inputs.forEach(name => {
        const el = document.querySelector('[name="' + name + '"]');
        if (el) total += parseFloat(el.value || 0);
    });
    document.getElementById('grossDisplay').textContent = '₹' + total.toLocaleString('en-IN', {maximumFractionDigits: 0});
}
calcGross(); // run on load

// ── Compensation type field toggle ──
function toggleCompFields(type) {
    document.querySelectorAll('.comp-field').forEach(el => el.style.display = 'none');
    const el = document.getElementById('field-' + type);
    if (el) el.style.display = 'block';
}
toggleCompFields(document.getElementById('compTypeSelect')?.value || 'fixed');

// ── Advance EMI preview ──
function calcEmiPreview() {
    const P = parseFloat(document.getElementById('advPrincipal')?.value || 0);
    const n = parseInt(document.getElementById('advMonths')?.value || 0);
    const withInt = document.getElementById('advInterestChk')?.checked;
    const r = parseFloat(document.getElementById('advRate')?.value || 0);

    if (!P || !n) { document.getElementById('emiPreview').style.display='none'; return; }

    let emi, total;
    if (!withInt || r <= 0) {
        emi   = P / n;
        total = P;
    } else {
        const mr = r / 12 / 100;
        emi   = P * mr * Math.pow(1+mr, n) / (Math.pow(1+mr, n) - 1);
        total = emi * n;
    }

    document.getElementById('emiVal').textContent   = '₹' + emi.toLocaleString('en-IN', {maximumFractionDigits: 0});
    document.getElementById('totalVal').textContent = '₹' + total.toLocaleString('en-IN', {maximumFractionDigits: 0});
    document.getElementById('emiPreview').style.display = 'block';
}
</script>
@endpush
@endsection
