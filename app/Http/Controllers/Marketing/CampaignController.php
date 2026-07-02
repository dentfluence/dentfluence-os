<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignGoal;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\MarketingActivityLog;
use App\Services\Marketing\CampaignService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CampaignController extends Controller
{
    private const CLINIC_ID = 1;

    // -------------------------------------------------------------------------
    // Index — campaign list
    // -------------------------------------------------------------------------
    public function index(): View
    {
        $clinicId = self::CLINIC_ID;

        $campaigns = Campaign::where('clinic_id', $clinicId)
            ->with(['goals', 'teamMembers'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($c) {
                $leads   = $c->goals->firstWhere('goal_type', 'leads');
                $appts   = $c->goals->firstWhere('goal_type', 'appointments');
                $revenue = $c->goals->firstWhere('goal_type', 'revenue');

                return [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'treatment'      => '—',
                    'status'         => ucfirst($c->status),
                    'start_date'     => $c->start_date?->format('d M Y') ?? '—',
                    'end_date'       => $c->end_date?->format('d M Y')   ?? '—',
                    'budget'         => $c->budget_total,
                    'leads'          => $leads   ? (int) $leads->actual_value   : 0,
                    'appointments'   => $appts   ? (int) $appts->actual_value   : 0,
                    'revenue'        => $revenue ? (int) $revenue->actual_value : 0,
                    'completion_pct' => CampaignService::completionPercentage($c),
                    'team_avatars'   => $c->teamMembers->map(fn($u) => strtoupper(substr($u->name, 0, 2)))->toArray(),
                    'days_remaining' => CampaignService::daysRemaining($c) ?? '—',
                ];
            });

        return view('marketing.campaigns.index', compact('campaigns'));
    }

    // -------------------------------------------------------------------------
    // Show — campaign detail (7-tab view)
    // -------------------------------------------------------------------------
    public function show(Campaign $campaign): View
    {
        $campaign->load(['goals', 'teamMembers', 'posts', 'owner']);

        // Posts grouped by status for content-plan kanban
        $postsByStatus = $campaign->posts
            ->groupBy('status')
            ->map(fn($group) => $group->map(fn($p) => [
                'id'           => $p->id,
                'title'        => $p->title ?: substr($p->caption, 0, 60),
                'content_type' => $p->content_type,
                'platforms'    => $p->platforms ?? [],
                'status'       => $p->status,
                'assignee_id'  => $p->assignee_id,
            ])->toArray());

        // Performance metrics (placeholders until Phase 5 platform APIs)
        $performance = [
            ['metric' => 'Reach',        'value' => '—', 'change' => '—', 'up' => true],
            ['metric' => 'Impressions',  'value' => '—', 'change' => '—', 'up' => true],
            ['metric' => 'Engagement',   'value' => '—', 'change' => '—', 'up' => true],
            ['metric' => 'Leads',        'value' => $campaign->goals->firstWhere('goal_type', 'leads')?->actual_value ?? 0, 'change' => '—', 'up' => true],
            ['metric' => 'Appointments', 'value' => $campaign->goals->firstWhere('goal_type', 'appointments')?->actual_value ?? 0, 'change' => '—', 'up' => true],
            ['metric' => 'Revenue',      'value' => '₹' . number_format($campaign->goals->firstWhere('goal_type', 'revenue')?->actual_value ?? 0), 'change' => '—', 'up' => true],
        ];

        // Build goals array for view
        $goals = $campaign->goals->map(fn($g) => [
            'label'       => $g->displayLabel(),
            'target'      => $g->target_value,
            'actual'      => $g->actual_value,
            'unit'        => $g->unit ?? '',
            'unit_prefix' => $g->goal_type === 'revenue',
        ])->toArray();

        // Team members for view
        $team = $campaign->teamMembers->map(fn($u) => [
            'id'       => $u->id,
            'name'     => $u->name,
            'initials' => strtoupper(substr($u->name, 0, 2)),
            'role'     => ucfirst($u->pivot->role),
        ])->toArray();

        // Build $campaign array matching the shape the view expects
        $campaignData = [
            'id'          => $campaign->id,
            'name'        => $campaign->name,
            'description' => $campaign->description,
            'status'      => ucfirst($campaign->status),
            'owner' => [
                'name'     => $campaign->owner?->name ?? 'Unassigned',
                'initials' => strtoupper(substr($campaign->owner?->name ?? 'U', 0, 2)),
            ],
            'start_date'     => $campaign->start_date?->format('d M Y') ?? '—',
            'end_date'       => $campaign->end_date?->format('d M Y') ?? '—',
            'days_remaining' => CampaignService::daysRemaining($campaign) ?? '—',
            'budget'         => $campaign->budget_total,
            'audience'       => '—',
            'channels'       => collect($campaign->channels ?? [])->map(fn($ch) => ['key' => $ch, 'label' => ucfirst(str_replace('_', ' ', $ch))])->toArray(),
            'channels_overflow' => 0,
            'progress' => [
                'overall_pct'       => CampaignService::completionPercentage($campaign),
                'content_planned'   => ['done' => $campaign->posts->count(), 'total' => $campaign->posts->count()],
                'content_published' => ['done' => $campaign->posts->where('status', 'published')->count(), 'total' => $campaign->posts->count()],
                'budget_utilized'   => ['done' => $campaign->budget_utilized, 'total' => $campaign->budget_total],
                'goals_achieved'    => ['done' => $campaign->goals->filter(fn($g) => $g->actual_value >= $g->target_value)->count(), 'total' => $campaign->goals->count()],
            ],
            'goals'       => $goals,
            'performance' => $performance,
            'top_content' => [],
            'team'        => $team,
            'posts_by_status' => $postsByStatus,
        ];

        // All users for "add team member" dropdown
        $allUsers = User::select('id', 'name')->orderBy('name')->get();

        return view('marketing.campaigns.show', [
            'campaign' => $campaignData,
            'allUsers' => $allUsers,
        ]);
    }

    // -------------------------------------------------------------------------
    // Store — create campaign
    // -------------------------------------------------------------------------
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'status'         => 'nullable|in:draft,active,paused,completed',
            'channels'       => 'nullable|array',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'budget_total'   => 'nullable|numeric|min:0',
            'campaign_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'owner_id'       => 'nullable|integer|exists:users,id',
        ]);

        $campaign = Campaign::create(array_merge($validated, [
            'clinic_id'  => self::CLINIC_ID,
            'status'     => $validated['status'] ?? 'draft',
            'owner_id'   => $validated['owner_id'] ?? auth()->id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        MarketingActivityLog::log(
            self::CLINIC_ID,
            'campaign_created',
            $campaign,
            "Campaign \"{$campaign->name}\" created"
        );

        return redirect()->route('marketing.campaigns.show', $campaign)
            ->with('success', 'Campaign created.');
    }

    // -------------------------------------------------------------------------
    // Update — edit campaign header fields
    // -------------------------------------------------------------------------
    public function update(Request $request, Campaign $campaign): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string',
            'status'         => 'nullable|in:draft,active,paused,completed',
            'channels'       => 'nullable|array',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date',
            'budget_total'   => 'nullable|numeric|min:0',
            'campaign_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'owner_id'       => 'nullable|integer|exists:users,id',
        ]);

        $campaign->update(array_merge($validated, ['updated_by' => auth()->id()]));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Campaign updated.');
    }

    // -------------------------------------------------------------------------
    // Destroy — soft delete
    // -------------------------------------------------------------------------
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')
            ->with('success', 'Campaign deleted.');
    }

    // -------------------------------------------------------------------------
    // Update Goals — PUT /campaigns/{id}/goals
    // -------------------------------------------------------------------------
    public function updateGoals(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'goals'               => 'required|array',
            'goals.*.goal_type'   => 'required|in:leads,appointments,treatments,revenue,posts,custom',
            'goals.*.target_value'=> 'required|numeric|min:0',
            'goals.*.actual_value'=> 'nullable|numeric|min:0',
            'goals.*.unit'        => 'nullable|string',
            'goals.*.custom_label'=> 'nullable|string',
        ]);

        // Replace all goals for this campaign
        $campaign->goals()->delete();
        foreach ($validated['goals'] as $g) {
            CampaignGoal::create(array_merge($g, [
                'campaign_id' => $campaign->id,
                'actual_value'=> $g['actual_value'] ?? 0,
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]));
        }

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Add Team Member
    // -------------------------------------------------------------------------
    public function addTeamMember(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role'    => 'nullable|in:manager,creator,approver,viewer',
        ]);

        $campaign->teamMembers()->syncWithoutDetaching([
            $validated['user_id'] => ['role' => $validated['role'] ?? 'creator'],
        ]);

        $user = User::find($validated['user_id']);

        return response()->json([
            'success' => true,
            'member'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'initials' => strtoupper(substr($user->name, 0, 2)),
                'role'     => ucfirst($validated['role'] ?? 'creator'),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Remove Team Member
    // -------------------------------------------------------------------------
    public function removeTeamMember(Request $request, Campaign $campaign): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $campaign->teamMembers()->detach($request->user_id);

        return response()->json(['success' => true]);
    }
}
