<?php

namespace App\Http\Controllers\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\EducationCategory;
use App\Models\EducationMedia;
use App\Models\EducationTreatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EducationContentController extends Controller
{
    // ── Categories ────────────────────────────────────────────────────────────

    public function categoriesIndex()
    {
        $categories = EducationCategory::withCount('treatments')
            ->orderBy('sort_order')
            ->get();

        return view('content-management.education-manage', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:20',
            'sort_order'  => 'nullable|integer',
        ]);

        $data['slug']      = Str::slug($data['name']);
        $data['is_active'] = true;

        EducationCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function destroyCategory(EducationCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    // ── Treatments ────────────────────────────────────────────────────────────

    public function treatmentsIndex(Request $request)
    {
        $categories = EducationCategory::active()->orderBy('sort_order')->get();
        $categoryId = $request->get('category_id');

        $treatments = EducationTreatment::with(['category', 'media'])
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        return view('content-management.education-manage', compact('categories', 'treatments', 'categoryId'));
    }

    public function storeTreatment(Request $request)
    {
        $data = $request->validate([
            'category_id'  => 'required|exists:education_categories,id',
            'title'        => 'required|string|max:200',
            'description'  => 'nullable|string|max:1000',
            'sort_order'   => 'nullable|integer',
            'cover_image'  => 'nullable|image|max:5120',
        ]);

        $data['slug']         = Str::slug($data['title']) . '-' . Str::random(4);
        $data['is_published'] = true;

        if ($request->hasFile('cover_image')) {
            $data['cover_image_path'] = $request->file('cover_image')
                ->store('education/covers', 'public');
        }

        unset($data['cover_image']);

        EducationTreatment::create($data);

        return back()->with('success', 'Treatment created.');
    }

    public function destroyTreatment(EducationTreatment $treatment)
    {
        // Delete associated media files
        foreach ($treatment->media as $media) {
            Storage::disk('public')->delete($media->file_path);
            if ($media->thumbnail_path) {
                Storage::disk('public')->delete($media->thumbnail_path);
            }
            $media->delete();
        }

        if ($treatment->cover_image_path) {
            Storage::disk('public')->delete($treatment->cover_image_path);
        }

        $treatment->delete();

        return back()->with('success', 'Treatment deleted.');
    }

    // ── Media Upload ──────────────────────────────────────────────────────────

    public function uploadMedia(Request $request, EducationTreatment $treatment)
    {
        $request->validate([
            'files'      => 'required|array|min:1',
            'files.*'    => 'required|file|max:102400', // 100MB max per file
            'media_type' => 'required|in:photo,xray,video,pdf,scan',
            'title'      => 'nullable|string|max:200',
            'tags'       => 'nullable|string|max:500',
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $mime      = $file->getMimeType();
            $mediaType = $request->input('media_type');
            $dir       = 'education/media/' . $treatment->id;

            $path = $file->store($dir, 'public');

            // Generate thumbnail for videos (store path only, actual thumb via ffmpeg if available)
            $thumbPath = null;
            if (str_starts_with($mime, 'image/')) {
                $thumbPath = $path; // use same image as thumb
            }

            // Parse duration for videos (placeholder — real duration needs ffprobe)
            $duration = null;

            $media = EducationMedia::create([
                'treatment_id'     => $treatment->id,
                'media_type'       => $mediaType,
                'file_path'        => $path,
                'thumbnail_path'   => $thumbPath,
                'title'            => $request->input('title') ?: $file->getClientOriginalName(),
                'tags'             => $request->input('tags'),
                'uploaded_by'      => Auth::id(),
                'file_size'        => $file->getSize(),
                'mime_type'        => $mime,
                'duration_seconds' => $duration,
                'is_published'     => true,
                'sort_order'       => EducationMedia::where('treatment_id', $treatment->id)->count(),
            ]);

            $uploaded[] = $media;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count'   => count($uploaded),
                'message' => count($uploaded) . ' file(s) uploaded successfully.',
            ]);
        }

        return back()->with('success', count($uploaded) . ' file(s) uploaded.');
    }

    public function destroyMedia(EducationMedia $media)
    {
        Storage::disk('public')->delete($media->file_path);
        if ($media->thumbnail_path && $media->thumbnail_path !== $media->file_path) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }
        $media->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Media deleted.');
    }

    // ── Manage page (single view for all operations) ──────────────────────────

    public function manage(Request $request)
    {
        $categories = EducationCategory::withCount('treatments')
            ->orderBy('sort_order')
            ->get();

        $selectedCategory = null;
        $treatments       = collect();
        $selectedTreatment = null;
        $media            = collect();

        $categoryId  = $request->get('category_id');
        $treatmentId = $request->get('treatment_id');

        if ($categoryId) {
            $selectedCategory = EducationCategory::find($categoryId);
            $treatments = EducationTreatment::where('category_id', $categoryId)
                ->withCount('media')
                ->orderBy('sort_order')
                ->get();
        }

        if ($treatmentId) {
            $selectedTreatment = EducationTreatment::with('category')->find($treatmentId);
            $media = EducationMedia::where('treatment_id', $treatmentId)
                ->orderBy('sort_order')
                ->get();
            if ($selectedTreatment) {
                $selectedCategory = $selectedTreatment->category;
                $categoryId = $selectedCategory->id;
                $treatments = EducationTreatment::where('category_id', $categoryId)
                    ->withCount('media')
                    ->orderBy('sort_order')
                    ->get();
            }
        }

        return view('content-management.education-manage', compact(
            'categories',
            'selectedCategory',
            'treatments',
            'selectedTreatment',
            'media',
            'categoryId',
            'treatmentId'
        ));
    }
    public function updateCategory(Request $request, EducationCategory $category)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color'       => 'nullable|string|max:20',
        ]);
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        $category->update($data);
        return back()->with('success', 'Category updated.');
    }

    public function updateTreatment(Request $request, EducationTreatment $treatment)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
        ]);
        $treatment->update($data);
        return back()->with('success', 'Treatment updated.');
    }
}
