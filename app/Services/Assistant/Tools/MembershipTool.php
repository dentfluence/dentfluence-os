<?php

namespace App\Services\Assistant\Tools;

use App\Models\Patient;
use App\Models\User;

/**
 * MembershipTool — AOCP membership stats. Read-only.
 * Active members, memberships expiring this month, expired, total enrolled —
 * NOT a generic patient count.
 */
class MembershipTool implements AssistantTool
{
    public function name(): string
    {
        return 'membership_report';
    }

    public function description(): string
    {
        return 'AOCP membership information ONLY (not total patients). view options: summary, active, '
             . 'expiring, expired. Use for "how many AOCP members", "memberships expiring this month", '
             . '"who is expiring", "AOCP status".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'view' => [
                    'type' => 'string',
                    'enum' => ['summary', 'active', 'expiring', 'expired'],
                    'description' => 'summary = counts; active/expired = list those; expiring = members expiring soon.',
                ],
                'period' => [
                    'type' => 'string',
                    'enum' => ['this_month', 'next_30_days'],
                    'description' => 'For "expiring": this month or the next 30 days. Default this_month.',
                ],
            ],
            'required' => [],
        ];
    }

    public function category(): string
    {
        return 'read';
    }

    public function run(array $args, User $user): array
    {
        $view   = in_array($args['view'] ?? '', ['summary', 'active', 'expiring', 'expired'], true) ? $args['view'] : 'summary';
        $period = ($args['period'] ?? 'this_month') === 'next_30_days' ? 'next_30_days' : 'this_month';
        $branch = $user->branch_id ?? null;

        $scoped = fn () => Patient::query()->when($branch, fn ($q) => $q->where('branch_id', $branch));

        // Active = status 'active' AND not past expiry.
        $activeQ = fn () => $scoped()->where('membership_status', 'active')
            ->where(function ($q) {
                $q->whereNull('membership_expires_at')->orWhereDate('membership_expires_at', '>=', today());
            });

        [$from, $to] = $period === 'next_30_days'
            ? [today(), today()->copy()->addDays(30)]
            : [today(), today()->copy()->endOfMonth()];

        $expiringQ = fn () => $scoped()->where('membership_status', 'active')
            ->whereNotNull('membership_expires_at')
            ->whereBetween('membership_expires_at', [$from->toDateString(), $to->toDateString()]);

        switch ($view) {
            case 'active':
                $members = $activeQ()->orderBy('name')->limit(50)->get(['name', 'patient_id', 'membership_expires_at']);
                $lines = $members->map(fn ($p) => "- {$p->name} ({$p->patient_id})"
                    . ($p->membership_expires_at ? ", expires " . $p->membership_expires_at->format('d M Y') : ''))->implode("\n");
                return [
                    'summary' => "AOCP active members: {$members->count()}",
                    'content' => "Active AOCP members ({$members->count()}):\n" . ($lines ?: '- none'),
                ];

            case 'expiring':
                $members = $expiringQ()->orderBy('membership_expires_at')->limit(50)->get(['name', 'patient_id', 'membership_expires_at']);
                $when = $period === 'next_30_days' ? 'in the next 30 days' : 'this month';
                if ($members->isEmpty()) {
                    return ['summary' => "AOCP expiring {$when}: 0", 'content' => "No AOCP memberships expiring {$when}."];
                }
                $lines = $members->map(fn ($p) => "- {$p->name} ({$p->patient_id}) — expires " . $p->membership_expires_at->format('d M Y'))->implode("\n");
                return [
                    'summary' => "AOCP expiring {$when}: {$members->count()}",
                    'content' => "AOCP memberships expiring {$when} ({$members->count()}):\n{$lines}",
                ];

            case 'expired':
                $count = $scoped()->where(function ($q) {
                    $q->where('membership_status', 'expired')
                      ->orWhere(function ($q2) {
                          $q2->where('membership_status', 'active')->whereDate('membership_expires_at', '<', today());
                      });
                })->count();
                return ['summary' => "AOCP expired: {$count}", 'content' => "Expired AOCP memberships: {$count}."];

            default: // summary
                $active   = $activeQ()->count();
                $expiring = $expiringQ()->count();
                $expired  = $scoped()->where(function ($q) {
                    $q->where('membership_status', 'expired')
                      ->orWhere(function ($q2) {
                          $q2->where('membership_status', 'active')->whereDate('membership_expires_at', '<', today());
                      });
                })->count();
                $enrolled = $scoped()->whereNotNull('membership_status')
                    ->where('membership_status', '!=', 'not_enrolled')->count();

                return [
                    'summary' => "AOCP summary: {$active} active",
                    'content' => "AOCP membership summary:\n"
                        . "- Active members: {$active}\n"
                        . "- Expiring this month: {$expiring}\n"
                        . "- Expired: {$expired}\n"
                        . "- Total ever enrolled: {$enrolled}",
                ];
        }
    }
}
