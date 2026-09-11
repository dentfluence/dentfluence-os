{{--
|==========================================================================
| Today's Actions — one worklist row = ONE PATIENT (W-10 finish, 2026-09-11).
| Included by each of the three bands and by the Completed section, so the
| row anatomy is defined exactly once. Expects: $row (a TodayCallList row)
| and inherits the parent Alpine scope (actioned/lastResponse/openDrawer/
| openHistory/sendBirthdayWhatsapp).
|
| The row's PRIMARY reason leads (it decides the row's place in the queue);
| every other open reason for the same patient is a chip in "Also on this
| call". Opening the drawer hands over ALL of them — one call, one outcome.
|==========================================================================
--}}
    @php
        $rowId    = $row['id'];
        $done     = $row['done'];
        $lastCall = $row['lastCall'];
        $primary  = $row['primary'];
        $pr       = $row['priority'] ?? 'low';
        $reasons  = $row['isDone'] ? $row['doneItems'] : $row['items'];

        // Drawer payload: the patient plus every reason on this call. Each
        // reason is the engine item with only its two display strings
        // cleaned — 'reason' / 'suggested_action' are shown, never submitted;
        // logCall/close/dismiss read category + ids only.
        $drawerRow = [
            'id'              => $rowId,
            'patient_name'    => $row['patient_name'],
            'patient_id'      => $row['patient_id'],
            'lead_id'         => $row['lead_id'],
            'relationship_id' => $row['relationship_id'],
            'link'            => $row['link'],
            'phone'           => $row['phone'],
            'done'            => $done,
            'item'            => $primary['drawer'],
            'items'           => array_map(fn ($i) => $i['drawer'], $reasons),
        ];
        $primaryAction = $primary['item']['primary_action'] ?? null;
    @endphp
    <tr id="row-{{ $rowId }}"
        class="{{ $done ? 'is-done' : '' }}"
        :class="actioned['{{ $rowId }}'] ? 'is-done' : ''"
        {{-- Search/chip filtering applies to the ACTIVE queue only. Completed
             rows sit in their own collapsed section and are not indexed by
             the Alpine filter, so they must not be gated on show(). --}}
        @unless($row['isDone'] ?? false) x-show="show('{{ $rowId }}')" @endunless>

        {{-- 1 · PRIORITY --}}
        <td>
            <span class="taw-pr taw-pr--{{ $pr }}" title="{{ ucfirst($pr) }} priority">
                <span class="taw-dot"></span>{{ ucfirst($pr) }}
            </span>
        </td>

        {{-- 2 · PATIENT --}}
        <td>
            <div class="taw-name" title="{{ $row['patient_name'] }}">
                <a href="{{ $row['link'] }}">{{ $row['patient_name'] }}</a>
            </div>
            @if(count($reasons) > 1)
                <span class="taw-reasons" title="One call covers all of these">{{ count($reasons) }} reasons · 1 call</span>
            @endif
        </td>

        {{-- 3 · CALL FOR — the primary reason --}}
        <td>
            @if($done)
                <div class="taw-do" title="{{ $done['label'] }}{{ !empty($done['notes']) ? ' — ' . $done['notes'] : '' }}">{{ $done['label'] }}</div>
                <span class="taw-why taw-why--ok">{{ $primary['whyText'] }}{{ !empty($done['at']) ? ' · ' . $done['at'] : '' }}</span>
            @else
                <div class="taw-do" title="{{ $primary['doText'] }}">{{ $primary['doText'] }}</div>
                @if($lastCall)
                    <span class="taw-why taw-why--try" x-show="!lastResponse['{{ $rowId }}']"
                          title="{{ $primary['whyText'] }} — last attempt: {{ $lastCall['label'] }}{{ !empty($lastCall['at']) ? ' at ' . $lastCall['at'] : '' }}{{ !empty($lastCall['notes']) ? ' — ' . $lastCall['notes'] : '' }}">
                        {{ $primary['whyText'] }} · last attempt: {{ $lastCall['label'] }}{{ !empty($lastCall['at']) ? ' ' . $lastCall['at'] : '' }}
                    </span>
                @else
                    <span class="taw-why" x-show="!lastResponse['{{ $rowId }}']" title="{{ $primary['whyText'] }}">{{ $primary['whyText'] }}</span>
                @endif
                <span class="taw-why taw-why--ok" x-show="lastResponse['{{ $rowId }}']" x-cloak>
                    <span x-text="lastResponse['{{ $rowId }}']"></span>
                </span>
            @endif
        </td>

        {{-- 4 · CATEGORY — of the primary reason --}}
        <td><span class="taw-tag" title="{{ $primary['catLabel'] }}">{{ $primary['catLabel'] }}</span></td>

        {{-- 5 · ALSO ON THIS CALL — every FURTHER reason, one chip each --}}
        <td>
            @if(count($reasons) > 1)
                <div class="taw-chips">
                    @if($done)
                        @foreach(array_slice($reasons, 1) as $r)
                            <span class="taw-chip" title="{{ $r['doText'] }}">{{ $r['catLabel'] }}</span>
                        @endforeach
                    @else
                        @foreach($row['chips'] as $chip)
                            <span class="taw-chip {{ $chip['over'] ? 'taw-chip--over' : '' }} {{ $chip['money'] ? 'taw-chip--money' : '' }}"
                                  title="{{ $chip['title'] }}">{{ $chip['label'] }}@if($chip['detail'] !== '') · {{ $chip['detail'] }}@endif</span>
                        @endforeach
                    @endif
                </div>
            @else
                <span class="taw-owner--none">—</span>
            @endif
        </td>

        {{-- 6 · DUE — the primary reason's date --}}
        <td><span class="taw-due {{ $primary['dueCls'] }}" title="{{ $primary['dueTip'] }}">{{ $primary['dueTxt'] }}</span></td>

        {{-- 7 · OWNER --}}
        <td>
            @if($row['owner'])
                <span class="taw-owner" title="Handled by {{ $row['owner'] }}">{{ $row['owner'] }}</span>
            @else
                <span class="taw-owner taw-owner--none" title="Not yet picked up by anyone">Unassigned</span>
            @endif
        </td>

        {{-- 8 · STATUS — Open → Attempted → Done --}}
        <td>
            <span class="taw-st {{ $row['stCls'] }}"
                  :class="actioned['{{ $rowId }}'] ? 'taw-st--done' : (attempted['{{ $rowId }}'] ? 'taw-st--tried' : '')">
                <span x-show="!actioned['{{ $rowId }}'] && !attempted['{{ $rowId }}']">{{ $row['stTxt'] }}</span>
                <span x-show="!actioned['{{ $rowId }}'] && attempted['{{ $rowId }}']" x-cloak>Attempted</span>
                <span x-show="actioned['{{ $rowId }}']" x-cloak>Done</span>
            </span>
        </td>

        {{-- 9 · ACTIONS --}}
        <td>
            <div class="taw-acts">
                {{-- The tick on a finished row opens the drawer read-only on
                     the patient's interaction history. Same drawer, no form. --}}
                @if($done)
                    <button type="button" class="taw-ib taw-ib--ok"
                            title="Done — {{ $done['label'] }}{{ !empty($done['at']) ? ' at ' . $done['at'] : '' }} · click for full history"
                            @click="openHistory({{ json_encode($drawerRow) }}, '{{ $rowId }}')"><i class="ti ti-check"></i></button>
                @elseif($mode === 'past')
                    @php $outcome = $primary['item']['meta']['outcome'] ?? null; @endphp
                    <button type="button" class="taw-ib taw-ib--ok"
                            title="{{ $outcome ? ucwords(str_replace('_', ' ', $outcome)) : 'Completed' }} · click for full history"
                            @click="openHistory({{ json_encode($drawerRow) }}, '{{ $rowId }}')"><i class="ti ti-check"></i></button>
                @elseif($primaryAction === 'whatsapp')
                    <template x-if="!actioned['{{ $rowId }}']">
                        <button type="button" class="taw-ib taw-ib--go" title="Send WhatsApp birthday greeting"
                                :disabled="sendingWhatsapp['{{ $rowId }}']"
                                @click="sendBirthdayWhatsapp({{ json_encode($drawerRow['item']) }}, '{{ $rowId }}')">
                            <i class="ti" :class="sendingWhatsapp['{{ $rowId }}'] ? 'ti-loader-2' : 'ti-brand-whatsapp'"
                               :style="sendingWhatsapp['{{ $rowId }}'] ? 'animation:spin 1s linear infinite;' : ''"></i>
                        </button>
                    </template>
                    <template x-if="actioned['{{ $rowId }}']">
                        <button type="button" class="taw-ib taw-ib--ok" title="Sent · click for full history"
                                @click="openHistory({{ json_encode($drawerRow) }}, '{{ $rowId }}')"><i class="ti ti-check"></i></button>
                    </template>
                @else
                    <template x-if="!actioned['{{ $rowId }}']">
                        <button type="button" class="taw-ib taw-ib--go" title="Log call{{ count($reasons) > 1 ? ' — ' . count($reasons) . ' reasons, one call' : '' }}"
                                @click="openDrawer({{ json_encode($drawerRow) }}, '{{ $rowId }}')">
                            <i class="ti ti-phone"></i>
                        </button>
                    </template>
                    <template x-if="actioned['{{ $rowId }}']">
                        <button type="button" class="taw-ib taw-ib--ok" title="Done · click for full history"
                                @click="openHistory({{ json_encode($drawerRow) }}, '{{ $rowId }}')"><i class="ti ti-check"></i></button>
                    </template>
                @endif

                <a href="{{ $row['link'] }}" class="taw-ib" title="Open record"><i class="ti ti-external-link"></i></a>
            </div>
        </td>
    </tr>
