@extends('layouts.app')
@section('page-title', 'My Profile')

@section('content')
<div style="max-width:780px;margin:0 auto;padding:28px 20px 60px;">

    {{-- ── Page header ── --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:20px;font-weight:600;color:#1a0a24;margin:0 0 4px;">My Profile</h1>
        <p style="font-size:13px;color:#9e8fa0;margin:0;">Manage your personal information and account security.</p>
    </div>

    {{-- ── Flash messages ── --}}
    @if(session('success'))
    <div style="display:flex;align-items:center;gap:10px;background:#e8f7ef;border:1px solid #b6dfc8;border-radius:4px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#1a7a45;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#fdeaea;border:1px solid #f5c2c2;border-radius:4px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#b52020;">
        @foreach($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
    @endif

    {{-- ══════════════════════════════════════
         SECTION 1 — Avatar + Identity
    ══════════════════════════════════════ --}}
    <div style="background:#ffffff;border:1px solid rgba(185,92,183,0.12);border-radius:6px;margin-bottom:16px;overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #f5eef9;background:#faf4fb;">
            <h2 style="font-size:13px;font-weight:600;color:#1a0a24;margin:0;">Profile Photo</h2>
        </div>
        <div style="padding:20px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">

            {{-- Avatar display --}}
            <div style="position:relative;flex-shrink:0;">
                @if($user->avatar)
                    <img
                        src="{{ Storage::url($user->avatar) }}"
                        alt="{{ $user->name }}"
                        style="width:80px;height:80px;border-radius:6px;object-fit:cover;border:2px solid rgba(185,92,183,0.20);"
                    >
                @else
                    <div style="width:80px;height:80px;border-radius:6px;background:linear-gradient(135deg,#6a0f70,#b95cb7);display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;font-size:26px;font-weight:700;color:#fff;letter-spacing:0.03em;">
                        {{ $user->initials }}
                    </div>
                @endif
            </div>

            {{-- Upload form --}}
            <div style="flex:1;min-width:220px;">
                <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" id="avatarForm">
                    @csrf
                    <label
                        for="avatar-input"
                        style="display:inline-flex;align-items:center;gap:7px;padding:7px 14px;background:#6a0f70;color:#fff;border-radius:4px;cursor:pointer;font-size:12.5px;font-weight:500;transition:background 140ms;"
                        onmouseover="this.style.background='#5a006e';"
                        onmouseout="this.style.background='#6a0f70';"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Upload Photo
                    </label>
                    <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;" onchange="document.getElementById('avatarForm').submit();">
                </form>
                <p style="font-size:11.5px;color:#b0a4bc;margin:8px 0 0;">JPG, PNG or WebP. Max 2 MB.</p>

                @if($user->avatar)
                <form method="POST" action="{{ route('profile.avatar.remove') }}" style="display:inline;" onsubmit="return confirm('Remove profile photo?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="margin-top:8px;font-size:12px;color:#b52020;background:none;border:none;cursor:pointer;padding:0;"
                        onmouseover="this.style.textDecoration='underline';"
                        onmouseout="this.style.textDecoration='none';">Remove photo</button>
                </form>
                @endif
            </div>

        </div>
    </div>

    {{-- ══════════════════════════════════════
         SECTION 2 — Personal Information
    ══════════════════════════════════════ --}}
    <div style="background:#ffffff;border:1px solid rgba(185,92,183,0.12);border-radius:6px;margin-bottom:16px;overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #f5eef9;background:#faf4fb;">
            <h2 style="font-size:13px;font-weight:600;color:#1a0a24;margin:0;">Personal Information</h2>
        </div>
        <div style="padding:24px 20px;">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

                    {{-- Full Name --}}
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Full Name <span style="color:#b52020;">*</span></label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            required
                            style="width:100%;padding:8px 11px;border:1px solid rgba(185,92,183,0.22);border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                    </div>

                    {{-- Email --}}
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Email Address <span style="color:#b52020;">*</span></label>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email', $user->email) }}"
                            required
                            style="width:100%;padding:8px 11px;border:1px solid rgba(185,92,183,0.22);border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Phone</label>
                        <input
                            type="text"
                            name="phone"
                            value="{{ old('phone', $user->phone) }}"
                            placeholder="+91 98765 43210"
                            style="width:100%;padding:8px 11px;border:1px solid rgba(185,92,183,0.22);border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                    </div>

                    {{-- Designation --}}
                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Designation</label>
                        <input
                            type="text"
                            name="designation"
                            value="{{ old('designation', $user->designation) }}"
                            placeholder="e.g. Senior Dentist"
                            style="width:100%;padding:8px 11px;border:1px solid rgba(185,92,183,0.22);border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                    </div>

                </div>

                {{-- Role display (read-only) --}}
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Role</label>
                    <div style="display:inline-flex;align-items:center;gap:7px;padding:6px 12px;background:#f5eef9;border:1px solid rgba(185,92,183,0.18);border-radius:20px;">
                        <div style="width:7px;height:7px;border-radius:50%;background:#6a0f70;flex-shrink:0;"></div>
                        <span style="font-size:12.5px;font-weight:500;color:#5a006e;">{{ $user->role_label }}</span>
                    </div>
                    <p style="font-size:11.5px;color:#b0a4bc;margin:6px 0 0;">Role is managed by your administrator.</p>
                </div>

                {{-- Account info (read-only) --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:14px;background:#faf4fb;border-radius:4px;margin-bottom:20px;font-size:12px;color:#7a6884;">
                    <div>
                        <span style="font-weight:500;color:#4a3460;">Account created:</span>
                        {{ $user->created_at ? $user->created_at->format('d M Y') : '—' }}
                    </div>
                    <div>
                        <span style="font-weight:500;color:#4a3460;">Last login:</span>
                        {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never recorded' }}
                    </div>
                </div>

                <button
                    type="submit"
                    style="padding:8px 20px;background:#6a0f70;color:#fff;border:none;border-radius:4px;font-size:13px;font-weight:500;cursor:pointer;transition:background 140ms;"
                    onmouseover="this.style.background='#5a006e';"
                    onmouseout="this.style.background='#6a0f70';"
                >
                    Save Changes
                </button>

            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         SECTION 3 — Change Password
    ══════════════════════════════════════ --}}
    <div style="background:#ffffff;border:1px solid rgba(185,92,183,0.12);border-radius:6px;overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #f5eef9;background:#faf4fb;">
            <h2 style="font-size:13px;font-weight:600;color:#1a0a24;margin:0;">Change Password</h2>
        </div>
        <div style="padding:24px 20px;">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Current Password <span style="color:#b52020;">*</span></label>
                        <input
                            type="password"
                            name="current_password"
                            autocomplete="current-password"
                            style="width:100%;padding:8px 11px;border:1px solid {{ $errors->has('current_password') ? '#b52020' : 'rgba(185,92,183,0.22)' }};border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                        @error('current_password')
                            <p style="font-size:11.5px;color:#b52020;margin:5px 0 0;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">New Password <span style="color:#b52020;">*</span></label>
                        <input
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            style="width:100%;padding:8px 11px;border:1px solid rgba(185,92,183,0.22);border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:500;color:#4a3460;margin-bottom:5px;">Confirm New Password <span style="color:#b52020;">*</span></label>
                        <input
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            style="width:100%;padding:8px 11px;border:1px solid rgba(185,92,183,0.22);border-radius:4px;font-size:13px;color:#1a0a24;background:#fff;outline:none;transition:border-color 140ms,box-shadow 140ms;"
                            onfocus="this.style.borderColor='#6a0f70';this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.10)';"
                            onblur="this.style.borderColor='rgba(185,92,183,0.22)';this.style.boxShadow='none';"
                        >
                        @error('password')
                            <p style="font-size:11.5px;color:#b52020;margin:5px 0 0;">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                <p style="font-size:11.5px;color:#b0a4bc;margin:0 0 16px;">Minimum 8 characters.</p>

                <button
                    type="submit"
                    style="padding:8px 20px;background:#1a5ea8;color:#fff;border:none;border-radius:4px;font-size:13px;font-weight:500;cursor:pointer;transition:background 140ms;"
                    onmouseover="this.style.background='#144d8c';"
                    onmouseout="this.style.background='#1a5ea8';"
                >
                    Update Password
                </button>

            </form>
        </div>
    </div>

</div>
@endsection
