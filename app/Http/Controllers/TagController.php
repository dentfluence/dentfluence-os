<?php
// ─── app/Http/Controllers/TagController.php ──────────────────────────────

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    // ── Settings: list all tags ───────────────────────────────────────────

    public function index(Request $request)
    {
        $search = $request->get('search');

        $tags = Tag::when($search, fn($q) => $q->search($search))
                   ->orderBy('group')
                   ->orderBy('sort_order')
                   ->orderBy('name')
                   ->withCount('patients')
                   ->get()
                   ->groupBy('group');

        return view('settings.tags.index', compact('tags', 'search'));
    }

    // ── Settings: store new tag ───────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:60',
            'color'       => 'required|string|size:7',
            'bg_color'    => 'required|string|size:7',
            'group'       => 'required|string|max:60',
            'description' => 'nullable|string|max:255',
        ]);

        $data['slug'] = Str::slug($data['name']);

        $tag = Tag::create($data);

        return response()->json(['success' => true, 'tag' => $tag]);
    }

    // ── Settings: update tag ──────────────────────────────────────────────

    public function update(Request $request, Tag $tag)
    {
        abort_if($tag->is_system, 403, 'System tags cannot be edited.');

        $data = $request->validate([
            'name'        => 'required|string|max:60',
            'color'       => 'required|string|size:7',
            'bg_color'    => 'required|string|size:7',
            'group'       => 'required|string|max:60',
            'description' => 'nullable|string|max:255',
        ]);

        $tag->update($data);

        return response()->json(['success' => true, 'tag' => $tag]);
    }

    // ── Settings: destroy tag ─────────────────────────────────────────────

    public function destroy(Tag $tag)
    {
        abort_if($tag->is_system, 403, 'System tags cannot be deleted.');

        $tag->delete();

        return response()->json(['success' => true]);
    }

    // ── Patient: list available tags for picker ───────────────────────────

    public function forPatient(Request $request, Patient $patient)
    {
        $branchId = $patient->branch_id;

        $allTags = Tag::orderBy('group')
                      ->orderBy('sort_order')
                      ->orderBy('name')
                      ->get();

        $patientTagIds = $patient->tags()->pluck('tags.id')->toArray();

        $grouped = $allTags->map(function ($tag) use ($patientTagIds) {
            $tag->is_attached = in_array($tag->id, $patientTagIds);
            return $tag;
        })->groupBy('group');

        return response()->json([
            'grouped'        => $grouped,
            'patient_tag_ids' => $patientTagIds,
        ]);
    }

    // ── Patient: attach tag ───────────────────────────────────────────────

    public function attach(Request $request, Patient $patient)
    {
        $request->validate(['tag_id' => 'required|exists:tags,id']);

        $patient->tags()->syncWithoutDetaching([
            $request->tag_id => ['added_by' => auth()->id()],
        ]);

        $tag = Tag::find($request->tag_id);

        return response()->json(['success' => true, 'tag' => $tag]);
    }

    // ── Patient: detach tag ───────────────────────────────────────────────

    public function detach(Request $request, Patient $patient, Tag $tag)
    {
        $patient->tags()->detach($tag->id);

        return response()->json(['success' => true]);
    }
}
