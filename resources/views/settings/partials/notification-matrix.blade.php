{{--
    settings/partials/notification-matrix.blade.php — N-4 (2026-09-09)

    WHO hears about WHAT, and how loudly. One row per catalogue event, one
    column per role plus a fixed OWNER column (the record's own doctor /
    assignee). Each cell: Off · Bell · Popup, and a 📱 push tick that only
    means anything at Popup level (the engine ignores push on a bell rule).

    Replaces the seven notif_* toggles that were saved to app_settings and
    read by nothing (measured 9 Sep). Reads NotificationRule::effectiveFor()
    so the cell shows what will ACTUALLY happen — rule row if present,
    catalogue default otherwise. Saving writes an explicit row for every cell,
    so from then on the matrix, not the code, is the authority.

    Expected: $notificationMatrix  [event_key => [role => ['level','push']]]
--}}
@php
    use App\Services\Notifications\NotificationCatalog as Cat;
    $roles  = Cat::roleColumns();
    $labels = [
        \App\Models\Role::ADMIN => 'Admin', \App\Models\Role::MANAGER => 'Manager', \App\Models\Role::DOCTOR => 'Doctor',
        \App\Models\Role::ASSISTANT => 'Assistant', \App\Models\Role::FRONT_DESK => 'Front desk', \App\Models\Role::ACCOUNTS => 'Accounts',
    ];
    $levels = [Cat::LEVEL_OFF => 'Off', Cat::LEVEL_BELL => 'Bell', Cat::LEVEL_POPUP => 'Popup'];
    // 🪤 Event keys contain dots ('consultation.saved'). Laravel resolves a
    // validation attribute by SPLITTING on dots, so a form key with a dot in
    // it is unreachable — every cell would validate as null and fail
    // `required`. Encode the dot in the field name; SettingsController decodes
    // it back before touching the catalogue.
    $fieldKey = fn (string $k) => str_replace('.', '__', $k);
@endphp

<style>
    .nm-wrap { overflow-x: auto; border: 1.5px solid #ede4f3; border-radius: 12px; background: #fff; }
    .nm { border-collapse: collapse; width: 100%; min-width: 980px; font-size: 12px; }
    .nm th { position: sticky; top: 0; background: #faf5fc; color: #6a0f70; font-size: 10.5px; font-weight: 700;
             letter-spacing: .5px; text-transform: uppercase; padding: 9px 8px; border-bottom: 1.5px solid #ede4f3; text-align: center; white-space: nowrap; z-index: 1; }
    .nm th:first-child { text-align: left; }
    .nm td { padding: 5px 6px; border-bottom: 1px solid #f5f0f8; text-align: center; vertical-align: middle; }
    .nm td:first-child { text-align: left; color: #1a0320; font-weight: 500; white-space: nowrap; }
    .nm tr.nm-module td { background: #f9f6fb; color: #6a0f70; font-size: 10.5px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; padding: 7px 8px; }
    .nm td.nm-owner { background: #fcf8fd; }
    .nm-cell { display: inline-flex; align-items: center; gap: 4px; }
    .nm-cell select { font: 500 12px 'DM Sans', system-ui, sans-serif; padding: 3px 4px; border: 1px solid #e3d6ea; border-radius: 6px; background: #fff; color: #3d2b47; }
    .nm-cell select.nm-off   { color: #b0a4bc; }
    .nm-cell select.nm-popup { color: #6a0f70; font-weight: 700; border-color: #c9b3d1; background: #f7eefa; }
    .nm-cell label { display: inline-flex; align-items: center; gap: 2px; cursor: pointer; color: #9a7aaa; font-size: 11px; }
    .nm-cell label.nm-na { opacity: .35; }
    .nm-cell input[type=checkbox] { margin: 0; accent-color: #6a0f70; }
    .nm-note { font-size: 12px; color: #9a7aaa; margin: 8px 0 14px; line-height: 1.5; }
    .nm-key { display: flex; gap: 14px; flex-wrap: wrap; font-size: 11.5px; color: #6b5a75; margin-bottom: 10px; }
    .nm-key b { color: #6a0f70; }
</style>

<p class="nm-note">
    <b>Popup</b> stops the front desk: use it only where someone must act in the next five minutes while the patient is still standing there.
    <b>Bell</b> is the quiet default. <b>📱</b> also sends a phone push — Popup level only.
    <b>Owner</b> is the person the record belongs to (the case's doctor, the task's assignee) — it cannot be changed to a role.
    The person who caused an event is never notified about their own action.
</p>
<div class="nm-key"><span><b>6 popups shipped by default</b> — consultation saved · visit saved · tomorrow's appointment but lab missing · invoice cancelled · negative review · system failure.</span></div>

<div class="nm-wrap">
<table class="nm">
    <thead>
        <tr>
            <th>Event</th>
            @foreach($roles as $r)<th>{{ $labels[$r] ?? $r }}</th>@endforeach
            <th>Owner</th>
        </tr>
    </thead>
    <tbody>
    @foreach(Cat::byModule() as $module => $group)
        @if(empty($group['events'])) @continue @endif
        <tr class="nm-module"><td colspan="{{ count($roles) + 2 }}">{{ $group['label'] }}</td></tr>
        @foreach($group['events'] as $key => $def)
        <tr>
            <td title="{{ $key }}">{{ $def['label'] }}</td>

            @foreach($roles as $r)
                @php $cell = $notificationMatrix[$key][$r] ?? ['level' => Cat::LEVEL_OFF, 'push' => false]; @endphp
                <td>
                    <span class="nm-cell">
                        <select name="rules[{{ $fieldKey($key) }}][{{ $r }}][level]" class="nm-{{ $cell['level'] }}"
                                onchange="this.className='nm-'+this.value; this.nextElementSibling.classList.toggle('nm-na', this.value!=='popup')">
                            @foreach($levels as $v => $l)<option value="{{ $v }}" {{ $cell['level'] === $v ? 'selected' : '' }}>{{ $l }}</option>@endforeach
                        </select>
                        <label class="{{ $cell['level'] !== Cat::LEVEL_POPUP ? 'nm-na' : '' }}" title="Phone push (popup level only)">
                            <input type="checkbox" name="rules[{{ $fieldKey($key) }}][{{ $r }}][push]" value="1" {{ $cell['push'] ? 'checked' : '' }}>📱
                        </label>
                    </span>
                </td>
            @endforeach

            <td class="nm-owner">
                @if($def['owner'])
                    @php $cell = $notificationMatrix[$key][Cat::OWNER] ?? ['level' => Cat::LEVEL_OFF, 'push' => false]; @endphp
                    <span class="nm-cell">
                        <select name="rules[{{ $fieldKey($key) }}][{{ Cat::OWNER }}][level]" class="nm-{{ $cell['level'] }}"
                                onchange="this.className='nm-'+this.value; this.nextElementSibling.classList.toggle('nm-na', this.value!=='popup')">
                            @foreach($levels as $v => $l)<option value="{{ $v }}" {{ $cell['level'] === $v ? 'selected' : '' }}>{{ $l }}</option>@endforeach
                        </select>
                        <label class="{{ $cell['level'] !== Cat::LEVEL_POPUP ? 'nm-na' : '' }}" title="Phone push (popup level only)">
                            <input type="checkbox" name="rules[{{ $fieldKey($key) }}][{{ Cat::OWNER }}][push]" value="1" {{ $cell['push'] ? 'checked' : '' }}>📱
                        </label>
                    </span>
                @else
                    <span style="color:#d1c4d8;">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
</div>
