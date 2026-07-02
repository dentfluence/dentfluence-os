{{--
| DPDP Consent — Patient capture widget
| File: resources/views/consent/patient.blade.php
|
| The everyday screen front-desk staff use to record what a patient agrees to.
| Kept deliberately SIMPLE (per the UI-complexity rule): big toggles, plain
| language, one Save button.
|
| $state = collection of [ 'purpose' => ConsentPurpose, 'consent' => ?PatientConsent, 'granted' => bool ]
--}}
@extends('layouts.app')

@section('page-title', 'Consent — ' . ($patient->name ?? 'Patient'))

@section('content')
@php
    $grouped = $state->groupBy(fn ($r) => $r['purpose']->category);
    $labels  = ['clinical' => 'Treatment & records', 'data_sharing' => 'Sharing', 'communication' => 'Messages', 'research' => 'Research', 'general' => 'Other'];
@endphp

<div class="df-page-header" style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between;">
    <div>
        <h1 class="df-page-title">Consent</h1>
        <p class="df-page-subtitle">{{ $patient->name }} @if($patient->patient_id)· {{ $patient->patient_id }}@endif</p>
    </div>
    <a href="{{ route('consent.patient.trail', $patient) }}"
       style="align-self:center; color:#4A1F3D; text-decoration:none; font-weight:600; border:1px solid #d8c7d6; padding:8px 14px; border-radius:8px;">
        View history →
    </a>
</div>

<form action="{{ route('consent.patient.update', $patient) }}" method="POST"
      x-data="{ mandatoryOff: false,
                check() { this.mandatoryOff = [...this.$el.querySelectorAll('[data-mandatory]')].some(c => !c.checked); } }"
      x-init="check()">
    @csrf
    @method('PATCH')

    <div class="df-card" style="max-width:760px;">
        <div class="df-card-body" style="padding:20px 24px;">

            @foreach($grouped as $category => $rows)
                <h3 style="margin:18px 0 8px; font-size:13px; text-transform:uppercase; letter-spacing:.04em; color:#9a7ba0;">
                    {{ $labels[$category] ?? ucfirst($category) }}
                </h3>

                @foreach($rows as $row)
                    @php $p = $row['purpose']; @endphp
                    <label style="display:flex; align-items:flex-start; gap:12px; padding:12px; border:1px solid #f0e6ee; border-radius:10px; margin-bottom:8px; cursor:pointer;">
                        <input type="checkbox" name="granted[]" value="{{ $p->id }}"
                               @if($p->is_mandatory) data-mandatory @endif
                               @checked($row['granted'])
                               @change="check()"
                               style="width:20px; height:20px; margin-top:2px; flex-shrink:0; accent-color:#C2185B;">
                        <span style="flex:1;">
                            <span style="font-weight:600; color:#1e0a2c;">{{ $p->name }}</span>
                            @if($p->is_mandatory)
                                <span style="background:#fbe3ec; color:#9c2b48; padding:1px 7px; border-radius:9px; font-size:11px; font-weight:600; margin-left:6px;">Required for care</span>
                            @endif
                            @if($p->description)
                                <span style="display:block; color:#6b5b73; font-size:13px; margin-top:3px;">{{ $p->description }}</span>
                            @endif
                            @if($row['consent'])
                                <span style="display:block; color:#9aa; font-size:11px; margin-top:4px;">
                                    Last set {{ optional($row['consent']->updated_at)->diffForHumans() }}
                                    @if($row['consent']->purpose_version < $p->version)
                                        · <span style="color:#b8860b;">wording updated — please re-confirm</span>
                                    @endif
                                </span>
                            @endif
                        </span>
                    </label>
                @endforeach
            @endforeach

            {{-- Warning when a required purpose is unticked --}}
            <div x-show="mandatoryOff" x-cloak
                 style="background:#fff7e6; border:1px solid #f0d28a; color:#8a6d00; padding:12px 14px; border-radius:10px; margin-top:14px; font-size:13px;">
                ⚠️ A required consent is unticked. The patient may withdraw it, but you may not be able to provide care without it. Make sure this is intentional.
            </div>

            @if($patient->isMinor())
                @php $lastGuardian = optional($state->firstWhere(fn($r) => $r['consent']))['consent']; @endphp
                <div style="background:#eef4ff; border:1px solid #b9cdf0; color:#274690; padding:12px 14px; border-radius:10px; margin-top:16px;">
                    <div style="font-weight:600; margin-bottom:10px;">👤 This patient is a minor — consent must be given by a parent or guardian.</div>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:200px;">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Guardian name</label>
                            <input type="text" name="guardian_name" required maxlength="120"
                                   value="{{ optional($lastGuardian)->guardian_name }}"
                                   style="width:100%; padding:9px 12px; border:1px solid #b9cdf0; border-radius:8px;">
                        </div>
                        <div style="flex:1; min-width:160px;">
                            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;">Relationship</label>
                            <input type="text" name="guardian_relationship" required maxlength="60" placeholder="Parent / Legal guardian"
                                   value="{{ optional($lastGuardian)->guardian_relationship }}"
                                   style="width:100%; padding:9px 12px; border:1px solid #b9cdf0; border-radius:8px;">
                        </div>
                    </div>
                </div>
            @endif

            <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin:18px 0 4px;">Note (optional)</label>
            <textarea name="notes" rows="2" maxlength="1000" placeholder="e.g. signed paper form on file, verbal consent over phone…"
                      style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;"></textarea>

            <div style="display:flex; justify-content:flex-end; margin-top:18px;">
                <button type="submit"
                    style="background:#C2185B; color:#fff; border:none; padding:11px 26px; border-radius:8px; font-weight:600; font-size:15px; cursor:pointer;">
                    Save consent
                </button>
            </div>
        </div>
    </div>
</form>

<style>[x-cloak]{display:none!important;}</style>
@endsection
