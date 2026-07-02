{{-- List view table — included inside index.blade.php --}}
<div class="list-table-wrap">
    <table class="leads-table">
        <thead>
            <tr>
                <th onclick="sortTable('name')">Name <i class="ti ti-arrows-sort" aria-hidden="true"></i></th>
                <th>Phone</th>
                <th>Stage</th>
                <th>Source</th>
                <th>Treatment</th>
                <th onclick="sortTable('assigned_to')">Assigned To</th>
                <th onclick="sortTable('followup_date')">Follow-up Date</th>
                <th>Urgency</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($leads as $lead)
            <tr class="lead-row {{ $lead['is_overdue'] ? 'row-overdue' : '' }}"
                data-stage="{{ $lead['stage'] }}"
                onclick="window.location='{{ !empty($lead['relationship_id']) ? route('relationship.profile', $lead['relationship_id']) : '/communication/prm/lead/'.$lead['id'] }}'"
                title="{{ !empty($lead['relationship_id']) ? 'View Relationship Profile' : 'View Lead' }}">
                <td>
                    <div class="td-name-wrap">
                        <div class="td-avatar">{{ strtoupper(substr($lead['name'], 0, 1)) }}{{ strtoupper(substr(explode(' ', $lead['name'])[1] ?? '', 0, 1)) }}</div>
                        <div>
                            <div class="td-name">{{ $lead['name'] }}</div>
                            @if(!empty($lead['email']))
                                <div class="td-email">{{ $lead['email'] }}</div>
                            @endif
                        </div>
                    </div>
                </td>
                <td><span class="td-phone">{{ $lead['phone'] }}</span></td>
                <td><x-prm.stage-badge :stage="$lead['stage']" /></td>
                <td><x-prm.source-tag :source="$lead['source']" /></td>
                <td class="td-treatment">{{ $lead['treatment'] }}</td>
                <td class="td-assigned">{{ $lead['assigned_to'] }}</td>
                <td class="td-date">
                    @if($lead['is_overdue'])
                        <span class="td-overdue"><i class="ti ti-alert-circle" aria-hidden="true"></i> Overdue {{ $lead['overdue_days'] }}d</span>
                    @elseif(!empty($lead['followup_date']))
                        {{ \Carbon\Carbon::parse($lead['followup_date'])->format('d M Y') }}
                        <span class="td-time">{{ $lead['followup_time'] }}</span>
                    @else
                        <span class="td-none">—</span>
                    @endif
                </td>
                <td>
                    <span class="urgency-dot urgency-{{ $lead['urgency'] }}"></span>
                    {{ ucfirst($lead['urgency']) }}
                </td>
                <td class="td-actions" onclick="event.stopPropagation()">
                    <a href="tel:{{ preg_replace('/\s+/', '', $lead['phone']) }}" class="tbl-btn" title="Call">
                        <i class="ti ti-phone" aria-hidden="true"></i>
                    </a>
                    <a href="https://wa.me/91{{ preg_replace('/\s+/', '', $lead['phone']) }}" target="_blank" class="tbl-btn" title="WhatsApp">
                        <i class="ti ti-brand-whatsapp" aria-hidden="true"></i>
                    </a>
                    <a href="/communication/prm/lead/{{ $lead['id'] }}/edit" class="tbl-btn" title="Edit">
                        <i class="ti ti-edit" aria-hidden="true"></i>
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
