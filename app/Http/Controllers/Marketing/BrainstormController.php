<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Marketing\Idea;
use App\Models\Marketing\FestivalDate;
use Illuminate\View\View;

class BrainstormController extends Controller
{
    use ResolvesClinicId;

    public function index(): View
    {
        $clinicId = $this->currentClinicId();

        // ── Idea Bank — paginated, all active ideas ──────────────────────────
        $ideas = Idea::where('clinic_id', $clinicId)
            ->whereIn('status', ['idea', 'in_progress'])
            ->with('assets')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($idea) => [
                'id'                => $idea->id,
                'type'              => $idea->content_type,
                'title'             => $idea->title,
                'description'       => $idea->description,
                'tags'              => $idea->tags ?? [],
                'image_placeholder' => null,
                'platform'          => $idea->platforms ? ucfirst($idea->platforms[0] ?? 'Any') : 'Any',
                'status'            => $idea->status,
                'is_ai_generated'   => $idea->is_ai_generated,
                'cover_image'       => $idea->cover_image,
                'key_points'        => $idea->key_points ?? [],
                'notes'             => $idea->notes,
            ])
            ->toArray();

        // ── Festival Planner — current month festivals ───────────────────────
        $festivals = FestivalDate::forMonth(now()->month, now()->year)
            ->active()
            ->orderBy('day')
            ->get();

        return view('marketing.brainstorm.index', compact('ideas', 'festivals'));
    }
}
