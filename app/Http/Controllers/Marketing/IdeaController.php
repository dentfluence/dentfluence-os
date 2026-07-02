<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Idea;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class IdeaController extends Controller
{
    private const CLINIC_ID = 1;

    // -------------------------------------------------------------------------
    // Store — create a new idea (Quick Idea tab)
    // -------------------------------------------------------------------------
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'content_type' => 'nullable|in:reel,post,carousel,story,blog,offer,general',
            'platforms'    => 'nullable|array',
            'tags'         => 'nullable|array',
            'campaign_id'  => 'nullable|integer|exists:mkt_campaigns,id',
            'notes'        => 'nullable|string',
        ]);

        $idea = Idea::create(array_merge($validated, [
            'clinic_id'      => self::CLINIC_ID,
            'content_type'   => $validated['content_type'] ?? 'post',
            'status'         => 'idea',
            'is_ai_generated'=> false,
            'created_by'     => auth()->id(),
            'updated_by'     => auth()->id(),
        ]));

        MarketingActivityLog::log(
            self::CLINIC_ID,
            'idea_created',
            $idea,
            "New idea created: \"{$idea->title}\""
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'idea' => $idea]);
        }

        return redirect()->route('marketing.brainstorm')
            ->with('success', 'Idea saved to Idea Bank.');
    }

    // -------------------------------------------------------------------------
    // Update — edit idea fields
    // -------------------------------------------------------------------------
    public function update(Request $request, Idea $idea): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'description'  => 'nullable|string',
            'content_type' => 'nullable|in:reel,post,carousel,story,blog,offer,general',
            'platforms'    => 'nullable|array',
            'tags'         => 'nullable|array',
            'notes'        => 'nullable|string',
            'key_points'   => 'nullable|array',
            'status'       => 'nullable|in:idea,in_progress,converted,archived',
        ]);

        $idea->update(array_merge($validated, ['updated_by' => auth()->id()]));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Idea updated.');
    }

    // -------------------------------------------------------------------------
    // Destroy — soft delete
    // -------------------------------------------------------------------------
    public function destroy(Idea $idea): RedirectResponse|JsonResponse
    {
        $idea->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Idea deleted.');
    }

    // -------------------------------------------------------------------------
    // Convert to Post — creates an mkt_post and redirects to Publish
    // -------------------------------------------------------------------------
    public function convertToPost(Idea $idea): RedirectResponse
    {
        // Create the master post from the idea
        $post = MarketingPost::create([
            'clinic_id'    => self::CLINIC_ID,
            'campaign_id'  => $idea->campaign_id,
            'title'        => $idea->title,
            'caption'      => $idea->description ?? '',
            'content_type' => $idea->content_type === 'general' ? 'post' : $idea->content_type,
            'platforms'    => $idea->platforms,
            'hashtags'     => [],
            'status'       => 'draft',
            'created_by'   => auth()->id(),
            'updated_by'   => auth()->id(),
        ]);

        // Mark the idea as converted
        $idea->update([
            'status'       => 'converted',
            'converted_to' => 'post',
            'converted_id' => $post->id,
            'updated_by'   => auth()->id(),
        ]);

        MarketingActivityLog::log(
            self::CLINIC_ID,
            'idea_converted',
            $idea,
            "Idea \"{$idea->title}\" converted to post"
        );

        return redirect()->route('marketing.publish')
            ->with('success', "Idea converted to post. Continue editing below.");
    }

    // -------------------------------------------------------------------------
    // Convert to Campaign — creates an mkt_campaign and redirects to Campaign show
    // -------------------------------------------------------------------------
    public function convertToCampaign(Idea $idea): RedirectResponse
    {
        $campaign = Campaign::create([
            'clinic_id'  => self::CLINIC_ID,
            'name'       => $idea->title,
            'description'=> $idea->description,
            'status'     => 'draft',
            'channels'   => $idea->platforms,
            'owner_id'   => auth()->id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // Mark idea as converted
        $idea->update([
            'status'       => 'converted',
            'converted_to' => 'campaign',
            'converted_id' => $campaign->id,
            'updated_by'   => auth()->id(),
        ]);

        MarketingActivityLog::log(
            self::CLINIC_ID,
            'idea_converted',
            $idea,
            "Idea \"{$idea->title}\" converted to campaign"
        );

        return redirect()->route('marketing.campaigns.show', $campaign)
            ->with('success', "Campaign created from idea. Set your goals and team.");
    }
}
