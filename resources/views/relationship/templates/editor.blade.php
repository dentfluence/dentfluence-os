{{--
|==========================================================================
| PRE — Message Template Editor
| Route: GET /relationship/templates/{id} | POST create/update
|        [relationship.templates.edit / .create / .store / .update]
|
| Moved here from communication/templates/editor.blade.php on 2026-07-06 —
| see relationship/templates/index.blade.php header comment for why.
| Original archived at under_review/pre_consolidation_2026_07_06/.
|==========================================================================
--}}
@extends('relationship.layouts.app')
@section('page-title', $template->exists ? 'Edit Template' : 'New Template')
@section('relationship-content')
<div style="padding:8px 4px 40px;font-family:'DM Sans',sans-serif;max-width:900px;margin:0 auto;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
        <h1 style="font-size:22px;font-weight:600;color:#1f2937;margin:0;">
            {{ $template->exists ? 'Edit Template' : 'New Template' }}
        </h1>
        <a href="{{ route('relationship.templates.index') }}" style="font-size:12.5px;color:#6a0f70;text-decoration:none;">&larr; Back to Templates</a>
    </div>
    <p style="color:#9a7aaa;font-size:13px;margin:0 0 20px;">
        Use tokens like <code>&lt;PatientName&gt;</code> in the message body — they'll be filled in automatically when the message is sent.
    </p>

    @if($errors->any())
    <div style="background:#fdeaea;border:1px solid #f5c6c6;color:#b52020;padding:10px 16px;border-radius:6px;font-size:13px;margin-bottom:16px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <form method="POST"
          action="{{ $template->exists ? route('relationship.templates.update', $template->id) : route('relationship.templates.store') }}">
        @csrf
        @if($template->exists) @method('PUT') @endif

        <div style="display:grid;grid-template-columns:1fr 240px;gap:20px;align-items:start;">

            {{-- ── Main form ── --}}
            <div style="background:#fff;border:1px solid #ede4f3;border-radius:10px;padding:20px;">

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Name *</label>
                    <input name="name" type="text" required value="{{ old('name', $template->name) }}"
                           placeholder="e.g. Recall Reminder — 6 Month"
                           style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;">
                </div>

                <div style="display:flex;gap:12px;margin-bottom:14px;">
                    <div style="flex:1;">
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Channel *</label>
                        <select name="channel" style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;">
                            @foreach($channels as $key => $label)
                            <option value="{{ $key }}" @selected(old('channel', $template->channel) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Type *</label>
                        <select name="type" style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;">
                            @foreach($types as $key => $label)
                            <option value="{{ $key }}" @selected(old('type', $template->type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Message Body *</label>
                    <textarea id="tpl-body" name="body" rows="8" required
                              placeholder="Hi <PatientName>, this is a reminder from <ClinicName>..."
                              style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;resize:vertical;font-family:'DM Sans',sans-serif;">{{ old('body', $template->body) }}</textarea>
                    <div style="font-size:11px;color:#9a7aaa;margin-top:4px;">Max 2000 characters. Click a token on the right to insert it at the cursor.</div>
                </div>

                <label style="display:flex;align-items:center;gap:8px;margin-bottom:20px;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active))
                           style="width:15px;height:15px;accent-color:#6a0f70;">
                    <span style="font-size:13px;color:#1a0320;">Active (available for use)</span>
                </label>

                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="{{ route('relationship.templates.index') }}"
                       style="padding:8px 18px;border:1.5px solid #ddd;background:#fff;border-radius:6px;font-size:13px;color:#555;text-decoration:none;">Cancel</a>
                    <button type="submit"
                            style="padding:8px 20px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;">
                        {{ $template->exists ? 'Save Changes' : 'Create Template' }}
                    </button>
                </div>
            </div>

            {{-- ── Token picker ── --}}
            <div style="background:#fff;border:1px solid #ede4f3;border-radius:10px;overflow:hidden;">
                <div style="padding:12px 16px;border-bottom:1px solid #ede4f3;background:#f9f5fc;">
                    <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">Insert Token</div>
                </div>
                <div>
                    @foreach($tokens as $token => $label)
                    <button type="button" class="tpl-token-btn" data-token="{{ $token }}"
                            style="display:block;width:100%;text-align:left;padding:10px 16px;border:none;background:none;border-bottom:1px solid #f5f0f8;cursor:pointer;">
                        <div style="font-size:12.5px;font-weight:600;color:#6a0f70;font-family:monospace;">&lt;{{ $token }}&gt;</div>
                        <div style="font-size:11px;color:#9a7aaa;margin-top:2px;">{{ $label }}</div>
                    </button>
                    @endforeach
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Insert the clicked token at the current cursor position in the body
// textarea (falls back to appending at the end if focus was lost).
document.querySelectorAll('.tpl-token-btn').forEach(function (btn) {
    btn.addEventListener('mouseenter', function () { btn.style.background = '#faf5fc'; });
    btn.addEventListener('mouseleave', function () { btn.style.background = 'none'; });
    btn.addEventListener('click', function () {
        var textarea = document.getElementById('tpl-body');
        var token = '<' + btn.dataset.token + '>';
        var start = textarea.selectionStart ?? textarea.value.length;
        var end = textarea.selectionEnd ?? textarea.value.length;

        textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(end);
        textarea.focus();
        var cursor = start + token.length;
        textarea.setSelectionRange(cursor, cursor);
    });
});
</script>
@endsection
