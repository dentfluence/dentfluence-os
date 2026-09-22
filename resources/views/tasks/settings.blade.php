{{--
    Tasks > Settings — the outcome vocabulary, per task category.

    Six-ish words per category, editable by the clinic. Rows are deactivated,
    never deleted, because the history in task_outcomes references these keys.
--}}
@extends('layouts.app')
@section('page-title', 'Task Settings')

@section('content')
<div style="font-family:'Inter',sans-serif;padding:22px 28px;max-width:1000px;">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;">
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:25px;font-weight:700;color:#1a0320;margin:0;">Task Settings</h1>
        <a href="{{ route('tasks.index') }}" style="font-size:13px;color:#6a0f70;text-decoration:none;">&larr; Back to tasks</a>
    </div>
    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">
        What staff can choose when they close or log a task. Keep each list short —
        a long list gets picked from carelessly.
    </p>

    @if(session('success'))
        <div style="padding:10px 14px;background:#e8f7ef;border:1.5px solid #bfe3cf;border-radius:8px;font-size:13px;color:#1a7a45;margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:10px 14px;background:#fdeaea;border:1.5px solid #f0c4c4;border-radius:8px;font-size:13px;color:#b52020;margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    @foreach($categories as $key => $label)
        @php $rows = $groups[$key] ?? collect(); @endphp
        <div style="border:1.5px solid #ede4f3;border-radius:10px;margin-bottom:18px;overflow:hidden;">

            <div style="padding:12px 16px;background:#faf7fc;border-bottom:1.5px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13.5px;font-weight:600;color:#1a0320;">{{ $label }}</span>
                <span style="font-size:11.5px;color:#9a7aaa;">{{ $rows->where('is_active', true)->count() }} active</span>
            </div>

            @if($rows->isEmpty())
                <p style="padding:14px 16px;font-size:12.5px;color:#b0a0bb;margin:0;">No outcomes yet — add one below.</p>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="color:#9a7aaa;font-size:11px;letter-spacing:.1em;text-transform:uppercase;">
                            <th style="text-align:left;padding:9px 16px;font-weight:600;">Outcome</th>
                            <th style="text-align:center;padding:9px 8px;font-weight:600;" title="Choosing this finishes the task. Untick for a retry-style outcome.">Closes task</th>
                            <th style="text-align:center;padding:9px 8px;font-weight:600;">Note required</th>
                            <th style="text-align:center;padding:9px 8px;font-weight:600;">Active</th>
                            <th style="text-align:center;padding:9px 8px;font-weight:600;">Order</th>
                            <th style="padding:9px 16px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr style="border-top:1px solid #f3eef7;">
                            <form method="POST" action="{{ route('tasks.settings.outcome.save', $row) }}">
                                @csrf
                                <td style="padding:8px 16px;">
                                    <input type="text" name="label" value="{{ $row->label }}"
                                           style="width:100%;padding:6px 10px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12.5px;font-family:inherit;">
                                    <div style="font-size:10.5px;color:#c5b0d5;margin-top:3px;">{{ $row->key }}</div>
                                </td>
                                <td style="text-align:center;padding:8px;">
                                    <input type="checkbox" name="closes_task" value="1" @checked($row->closes_task) style="accent-color:#6a0f70;">
                                </td>
                                <td style="text-align:center;padding:8px;">
                                    <input type="checkbox" name="requires_notes" value="1" @checked($row->requires_notes) style="accent-color:#6a0f70;">
                                </td>
                                <td style="text-align:center;padding:8px;">
                                    <input type="checkbox" name="is_active" value="1" @checked($row->is_active) style="accent-color:#6a0f70;">
                                </td>
                                <td style="text-align:center;padding:8px;">
                                    <input type="number" name="sort_order" value="{{ $row->sort_order }}" min="0"
                                           style="width:56px;padding:6px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12.5px;text-align:center;font-family:inherit;">
                                </td>
                                <td style="padding:8px 16px;text-align:right;">
                                    <button type="submit" style="padding:6px 13px;background:#fff;border:1.5px solid #ede4f3;border-radius:6px;font-size:12px;font-weight:600;color:#6a0f70;cursor:pointer;font-family:inherit;">Save</button>
                                </td>
                            </form>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <form method="POST" action="{{ route('tasks.settings.outcome.add', $key) }}"
                  style="display:flex;gap:8px;padding:11px 16px;border-top:1.5px solid #ede4f3;background:#fcfafd;">
                @csrf
                <input type="text" name="label" placeholder="Add an outcome, e.g. Waiting on courier" required
                       style="flex:1;padding:7px 11px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12.5px;font-family:inherit;">
                <button type="submit" style="padding:7px 15px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;">Add</button>
            </form>
        </div>
    @endforeach

    {{-- ── Maintenance / AMC types ──────────────────────────────────────
         Not per task category — one clinic-wide list of the things that get
         serviced. Used by the "Type of Maintenance" dropdown on a recurring
         maintenance task. --}}
    <div style="border:1.5px solid #ede4f3;border-radius:10px;margin:26px 0 18px;overflow:hidden;">
        <div style="padding:12px 16px;background:#faf7fc;border-bottom:1.5px solid #ede4f3;">
            <div style="font-size:13.5px;font-weight:600;color:#1a0320;">Maintenance / AMC types</div>
            <div style="font-size:11.5px;color:#9a7aaa;margin-top:2px;">
                AC service, pest control, autoclave, compressor — whatever this clinic actually services.
            </div>
        </div>

        @if($maintenanceTypes->isEmpty())
            <p style="padding:14px 16px;font-size:12.5px;color:#b0a0bb;margin:0;">No types yet — add one below.</p>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="color:#9a7aaa;font-size:11px;letter-spacing:.1em;text-transform:uppercase;">
                        <th style="text-align:left;padding:9px 16px;font-weight:600;">Type</th>
                        <th style="text-align:center;padding:9px 8px;font-weight:600;">Active</th>
                        <th style="text-align:center;padding:9px 8px;font-weight:600;">Order</th>
                        <th style="padding:9px 16px;"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($maintenanceTypes as $mt)
                    <tr style="border-top:1px solid #f3eef7;">
                        <form method="POST" action="{{ route('tasks.settings.maintenance.save', $mt) }}">
                            @csrf
                            <td style="padding:8px 16px;">
                                <input type="text" name="label" value="{{ $mt->label }}"
                                       style="width:100%;padding:6px 10px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12.5px;font-family:inherit;">
                                <div style="font-size:10.5px;color:#c5b0d5;margin-top:3px;">{{ $mt->key }}</div>
                            </td>
                            <td style="text-align:center;padding:8px;">
                                <input type="checkbox" name="is_active" value="1" @checked($mt->is_active) style="accent-color:#6a0f70;">
                            </td>
                            <td style="text-align:center;padding:8px;">
                                <input type="number" name="sort_order" value="{{ $mt->sort_order }}" min="0"
                                       style="width:56px;padding:6px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12.5px;text-align:center;font-family:inherit;">
                            </td>
                            <td style="padding:8px 16px;text-align:right;">
                                <button type="submit" style="padding:6px 13px;background:#fff;border:1.5px solid #ede4f3;border-radius:6px;font-size:12px;font-weight:600;color:#6a0f70;cursor:pointer;font-family:inherit;">Save</button>
                            </td>
                        </form>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <form method="POST" action="{{ route('tasks.settings.maintenance.add') }}"
              style="display:flex;gap:8px;padding:11px 16px;border-top:1.5px solid #ede4f3;background:#fcfafd;">
            @csrf
            <input type="text" name="label" placeholder="Add a type, e.g. Compressor service" required
                   style="flex:1;padding:7px 11px;border:1.5px solid #ede4f3;border-radius:6px;font-size:12.5px;font-family:inherit;">
            <button type="submit" style="padding:7px 15px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12.5px;font-weight:600;cursor:pointer;font-family:inherit;">Add</button>
        </form>
    </div>

    <p style="font-size:11.5px;color:#9a7aaa;margin:0;">
        Call, WhatsApp and Follow-up tasks have one rule the clinic cannot change:
        an outcome meaning the call never connected always leaves the task open,
        whatever "Closes task" says. A call that did not happen is never marked handled.
    </p>
</div>
@endsection
