<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsEduCategory;
use App\Models\CmsEduItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EduLibraryController extends Controller
{
    /**
     * Generic Education Library tab data (AJAX-only, tab is on same page).
     */
    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->input('category_id');

        $categories = CmsEduCategory::withCount('items')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $q = CmsEduItem::with('category')
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($categoryId) {
            $q->where('category_id', $categoryId);
        }

        $items = $q->get()->map(fn($i) => [
            'id'          => $i->id,
            'title'       => $i->title,
            'description' => $i->description,
            'media_type'  => $i->media_type,
            'thumbnail'   => $i->thumbnail_path ? \Storage::url($i->thumbnail_path) : null,
            'duration'    => $i->duration_seconds,
            'photo_count' => $i->photo_count,
            'xray_count'  => $i->xray_count,
            'video_count' => $i->video_count,
            'category'    => $i->category?->name,
        ]);

        return response()->json([
            'categories' => $categories,
            'items'      => $items,
        ]);
    }
}
