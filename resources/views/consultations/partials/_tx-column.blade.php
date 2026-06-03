{{-- Treatment column partial used in treatment-advised.blade.php --}}
{{-- Variables expected: $item (treatment row data) --}}
<td style="padding:8px 12px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6;">
    {{ $item['treatment'] ?? $item->treatment ?? '—' }}
</td>
