{{--
|==========================================================================
| Today's Actions — one worklist row.
| Included by each of the three bands and by the Completed section, so the
| row anatomy is defined exactly once. Expects: $row (from index.blade.php's
| data prep) and inherits the parent Alpine scope (actioned/lastResponse/
| openDrawer/sendBirthdayWhatsapp).
|==========================================================================
--}}
    @php
        $item     = $row['item'];
        $itemId   = $row['id'];
        $done     = $row['done'];
        $lastCall = $row['lastCall'];
        $pr       = $item['priority'] ?? 'low';
        // Drawer payload: the same item with only the two
        // display strings cleaned. 'reason' and
        // 'suggested_action' are shown, never submitted —
        // logAction/dismiss read category + ids only.
        $drawerItem = array_merge($item, [
            'reason'           => $row['whyText'] ?: ($item['reason'] ?? ''),
            'suggested_action' => $row['doText'],
        ]);
    @endphp
    <tr id="item-{{ $itemId }}"
        class="{{ $done ? 'is-done' : '' }}"
        :class="actioned['{{ $itemId }}'] ? 'is-done' : ''"
        {{-- Search/chip filtering applies to the ACTIVE queue only. Completed
             rows sit in their own collapsed section and are not indexed by
             the Alpine filter, so they must not be gated on show(). --}}
        @unless($row['isDone'] ?? false) x-show="show('{{ $itemId }}')" @endunless>

        {{-- 1 · PRIORITY --}}
        <td>
            <span class="taw-pr taw-pr--{{ $pr }}" title="{{ ucfirst($pr) }} priority">
                <span class="taw-dot"></span>{{ ucfirst($pr) }}
            </span>
        </td>

        {{-- 2 · PATIENT --}}
        <td>
            <div class="taw-name" title="{{ $item['patient_name'] }}">
                <a href="{{ $item['link'] }}">{{ $item['patient_name'] }}</a>
            </div>
        </td>

        {{-- 3 · ACTION & REASON --}}
        <td>
            @if($done)
                <div class="taw-do" title="{{ $done['label'] }}{{ !empty($done['notes']) ? ' — ' . $done['notes'] : '' }}">{{ $done['label'] }}</div>
                <span class="taw-why taw-why--ok">{{ $row['whyText'] }}{{ !empty($done['at']) ? ' · ' . $done['at'] : '' }}</span>
            @else
                <div class="taw-do" title="{{ $row['doText'] }}">{{ $row['doText'] }}</div>
                @if($lastCall)
                    <span class="taw-why taw-why--try" x-show="!lastResponse['{{ $itemId }}']"
                          title="{{ $row['whyText'] }} — last attempt: {{ $lastCall['label'] }}{{ !empty($lastCall['at']) ? ' at ' . $lastCall['at'] : '' }}{{ !empty($lastCall['notes']) ? ' — ' . $lastCall['notes'] : '' }}">
                        {{ $row['whyText'] }} · last attempt: {{ $lastCall['label'] }}{{ !empty($lastCall['at']) ? ' ' . $lastCall['at'] : '' }}
                    </span>
                @else
                    <span class="taw-why" x-show="!lastResponse['{{ $itemId }}']" title="{{ $row['whyText'] }}">{{ $row['whyText'] }}</span>
                @endif
                <span class="taw-why taw-why--ok" x-show="lastResponse['{{ $itemId }}']" x-cloak>
                    <span x-text="lastResponse['{{ $itemId }}']"></span>
                </span>
            @endif
        </td>

        {{-- 4 · DUE --}}
        <td><span class="taw-due {{ $row['dueCls'] }}" title="{{ $row['dueTip'] }}">{{ $row['dueTxt'] }}</span></td>

        {{-- 5 · OWNER --}}
        <td>
            @if($row['owner'])
                <span class="taw-owner" title="Handled by {{ $row['owner'] }}">{{ $row['owner'] }}</span>
            @else
                <span class="taw-owner taw-owner--none" title="Not yet picked up by anyone">Unassigned</span>
            @endif
        </td>

        {{-- 6 · CATEGORY --}}
        <td><span class="taw-tag" title="{{ $row['catLabel'] }}">{{ $row['catLabel'] }}</span></td>

        {{-- 7 · CHANNEL --}}
        <td><span class="taw-ch"><i class="ti {{ $row['chIcon'] }}"></i>{{ $row['chLabel'] }}</span></td>

        {{-- 8 · STATUS --}}
        <td>
            <span class="taw-st {{ $row['stCls'] }}">
                <span x-show="!actioned['{{ $itemId }}']">{{ $row['stTxt'] }}</span>
                <span x-show="actioned['{{ $itemId }}']" x-cloak>Done</span>
            </span>
        </td>

        {{-- 9 · ACTIONS --}}
        <td>
            <div class="taw-acts">
                {{-- The tick on a finished row is not decoration: it opens the
                     drawer read-only on that action's interaction history —
                     who called, when, what came of it, who took the callback.
                     Same drawer, no form, nothing re-loggable. --}}
                @if($done)
                    <button type="button" class="taw-ib taw-ib--ok"
                            title="Done — {{ $done['label'] }}{{ !empty($done['at']) ? ' at ' . $done['at'] : '' }} · click for full history"
                            @click="openHistory({{ json_encode($drawerItem) }}, '{{ $itemId }}')"><i class="ti ti-check"></i></button>
                @elseif($mode === 'past')
                    @php $outcome = $item['meta']['outcome'] ?? null; @endphp
                    <button type="button" class="taw-ib taw-ib--ok"
                            title="{{ $outcome ? ucwords(str_replace('_', ' ', $outcome)) : 'Completed' }} · click for full history"
                            @click="openHistory({{ json_encode($drawerItem) }}, '{{ $itemId }}')"><i class="ti ti-check"></i></button>
                @elseif(($item['primary_action'] ?? null) === 'whatsapp')
                    <template x-if="!actioned['{{ $itemId }}']">
                        <button type="button" class="taw-ib taw-ib--go" title="Send WhatsApp birthday greeting"
            :disabled="sendingWhatsapp['{{ $itemId }}']"
            @click="sendBirthdayWhatsapp({{ json_encode($drawerItem) }}, '{{ $itemId }}')">
        <i class="ti" :class="sendingWhatsapp['{{ $itemId }}'] ? 'ti-loader-2' : 'ti-brand-whatsapp'"
           :style="sendingWhatsapp['{{ $itemId }}'] ? 'animation:spin 1s linear infinite;' : ''"></i>
                        </button>
                    </template>
                    <template x-if="actioned['{{ $itemId }}']">
                        <button type="button" class="taw-ib taw-ib--ok" title="Sent · click for full history"
                                @click="openHistory({{ json_encode($drawerItem) }}, '{{ $itemId }}')"><i class="ti ti-check"></i></button>
                    </template>
                @else
                    <template x-if="!actioned['{{ $itemId }}']">
                        <button type="button" class="taw-ib taw-ib--go" title="Log call"
            @click="openDrawer({{ json_encode($drawerItem) }}, '{{ $itemId }}')">
        <i class="ti ti-phone"></i>
                        </button>
                    </template>
                    <template x-if="actioned['{{ $itemId }}']">
                        <button type="button" class="taw-ib taw-ib--ok" title="Done · click for full history"
                                @click="openHistory({{ json_encode($drawerItem) }}, '{{ $itemId }}')"><i class="ti ti-check"></i></button>
                    </template>
                @endif

                <a href="{{ $item['link'] }}" class="taw-ib" title="Open record"><i class="ti ti-external-link"></i></a>
            </div>
        </td>
    </tr>
