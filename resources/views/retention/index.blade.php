{{--
| DPDP — Data Retention (dry run)
| File: resources/views/retention/index.blade.php
--}}
@extends('layouts.app')
@section('page-title', 'Data Retention')
@section('content')
<div class="df-page-header" style="margin-bottom:16px;">
    <h1 class="df-page-title">Data Retention</h1>
    <p class="df-page-subtitle">How long each kind of data is kept, and how many records are past their window today.</p>
</div>

<div style="background:#eef4ff; border:1px solid #b9cdf0; color:#274690; padding:12px 16px; border-radius:10px; margin-bottom:18px; font-size:14px;">
    🛈 This is a <b>dry run</b> — it only reports. Nothing is deleted automatically. Run <code>php artisan dpdp:retention-report</code> for the same figures in the terminal.
</div>

<div class="df-card"><div class="df-card-body" style="padding:0; overflow:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead><tr style="text-align:left; background:#faf5f9; color:#4A1F3D;">
            <th style="padding:12px 16px;">Data type</th><th style="padding:12px 16px;">Policy</th>
            <th style="padding:12px 16px; text-align:center;">Retain (days)</th><th style="padding:12px 16px;">Cutoff</th>
            <th style="padding:12px 16px;">Action</th><th style="padding:12px 16px; text-align:center;">Past window</th>
        </tr></thead>
        <tbody>
        @forelse($report as $r)
            <tr style="border-top:1px solid #f0e6ee;">
                <td style="padding:12px 16px; font-weight:600; color:#4A1F3D;">{{ $r['policy']->data_type }}</td>
                <td style="padding:12px 16px;">
                    {{ $r['policy']->name }}
                    @if($r['policy']->description)<div style="color:#8a7790; font-size:12px;">{{ $r['policy']->description }}</div>@endif
                </td>
                <td style="padding:12px 16px; text-align:center;">{{ number_format($r['policy']->retain_days) }}</td>
                <td style="padding:12px 16px;">{{ $r['cutoff'] }}</td>
                <td style="padding:12px 16px;">
                    <span style="background:#eef4ff; color:#274690; padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600;">{{ $r['policy']->action }}</span>
                </td>
                <td style="padding:12px 16px; text-align:center; font-weight:700; color:{{ ($r['count'] ?? 0) > 0 ? '#9a4a00' : '#1b7a3d' }};">
                    {{ is_null($r['count']) ? 'n/a' : number_format($r['count']) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:24px; text-align:center; color:#8a7790;">
                No retention policies. Seed them: <code>php artisan db:seed --class=RetentionPolicySeeder</code>
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>

<p style="color:#9aa; font-size:12px; margin-top:12px;">
    To actually purge/anonymise expired data, the purge step must be built and enabled separately with explicit sign-off — it is intentionally not automated here.
</p>
@endsection
