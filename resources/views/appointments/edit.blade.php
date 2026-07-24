@extends('layouts.app')
@section('page-title', 'Edit Appointment')
@section('content')
<div style="padding:28px;font-family:'Inter',sans-serif;max-width:700px;margin:0 auto;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="{{ route('appointments.index') }}" style="color:#6a0f70;font-size:13px;">← Back</a>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0;">Edit Appointment</h1>
    </div>

    <form action="{{ route('appointments.update', $appointment) }}" method="POST"
          style="background:#fff;border:1px solid #ede4f3;border-radius:10px;padding:28px;">
        @csrf
        @method('PATCH')
        {{-- Optimistic lock: the server refuses the save if another user edited
             this appointment since this page was rendered. --}}
        <input type="hidden" name="updated_at" value="{{ $appointment->updated_at?->toIso8601String() }}">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label style="font-size:12px;font-weight:600;color:#4b0e59;display:block;margin-bottom:4px;">Patient</label>
                {{-- Slice 10: typeahead reusing the same /patients/search the booking
                     modal uses — no longer loads the entire branch patient list. --}}
                <input type="text" id="appt-patient-search" list="appt-patient-options" autocomplete="off"
                       value="{{ $appointment->patient?->name }}"
                       placeholder="Search patient by name or phone…"
                       style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                <input type="hidden" name="patient_id" id="appt-patient-id" value="{{ $appointment->patient_id }}">
                <datalist id="appt-patient-options"></datalist>
                <script>
                (function () {
                    var s = document.getElementById('appt-patient-search'),
                        h = document.getElementById('appt-patient-id'),
                        dl = document.getElementById('appt-patient-options'),
                        map = {}, t;
                    function sync() { if (map[s.value] !== undefined) h.value = map[s.value]; }
                    s.addEventListener('input', function () {
                        sync();
                        clearTimeout(t);
                        var q = s.value.trim();
                        if (q.length < 2) return;
                        t = setTimeout(function () {
                            fetch('/patients/search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                                .then(function (r) { return r.json(); })
                                .then(function (list) {
                                    dl.innerHTML = ''; map = {};
                                    (list || []).forEach(function (p) {
                                        var label = p.phone ? (p.name + ' — ' + p.phone) : p.name;
                                        map[label] = p.id;
                                        var o = document.createElement('option'); o.value = label; dl.appendChild(o);
                                    });
                                    sync();
                                })
                                .catch(function () {});
                        }, 250);
                    });
                    s.addEventListener('change', sync);
                })();
                </script>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4b0e59;display:block;margin-bottom:4px;">Doctor</label>
                <select name="doctor_id" required style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                    @foreach($doctors as $d)
                    <option value="{{ $d->id }}" {{ $appointment->doctor_id == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4b0e59;display:block;margin-bottom:4px;">Date</label>
                <input type="date" name="appointment_date" value="{{ $appointment->appointment_date }}" required
                       style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#4b0e59;display:block;margin-bottom:4px;">Time</label>
                <select name="appointment_time" required style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                    @foreach($timeSlots as $slot)
                    <option value="{{ $slot }}" {{ $appointment->appointment_time == $slot ? 'selected' : '' }}>{{ $slot }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Operatory (optional) --}}
        @if(isset($operatories) && $operatories->count() > 0)
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;font-weight:600;color:#4b0e59;display:block;margin-bottom:4px;">Operatory</label>
            <select name="operatory_id" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                <option value="">— None —</option>
                @foreach($operatories as $op)
                    <option value="{{ $op->id }}" {{ $appointment->operatory_id == $op->id ? 'selected' : '' }}>
                        {{ $op->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <div style="margin-bottom:16px;">
            <label style="font-size:12px;font-weight:600;color:#4b0e59;display:block;margin-bottom: