<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Marketing\MarketingAsset;
use App\Models\Marketing\AssetFolder;
use App\Models\Marketing\AssetTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LibraryController extends Controller
{
    use ResolvesClinicId;

    // -------------------------------------------------------------------------
    // Index — library main view
    // -------------------------------------------------------------------------
    public function index(Request $request): View
    {
        $clinicId = $this->currentClinicId();
        $folderId = $request->get('folder');
        $tagId    = $request->get('tag');
        $type     = $request->get('type');
        $search   = $request->get('q');

        // ── Folder tree ──────────────────────────────────────────────────────
        $rootFolders = AssetFolder::where('clinic_id', $clinicId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Build folder list in shape the view expects
        $totalAssets = MarketingAsset::where('clinic_id', $clinicId)->count();
        $folders = collect([
            ['id' => 'all', 'name' => 'All Assets', 'count' => $totalAssets, 'icon' => 'grid', 'children' => []],
            ['id' => 'uncategorized', 'name' => 'Uncategorized', 'count' => MarketingAsset::where('clinic_id', $clinicId)->whereNull('folder_id')->count(), 'icon' => 'folder', 'children' => []],
        ]);
        foreach ($rootFolders as $folder) {
            $folders->push([
                'id'       => $folder->id,
                'name'     => $folder->name,
                'count'    => MarketingAsset::where('folder_id', $folder->id)->count(),
                'icon'     => 'folder',
                'children' => $folder->children->map(fn($c) => [
                    'id'    => $c->id,
                    'name'  => $c->name,
                    'count' => MarketingAsset::where('folder_id', $c->id)->count(),
                ])->toArray(),
            ]);
        }

        // ── Assets query ─────────────────────────────────────────────────────
        $query = MarketingAsset::where('clinic_id', $clinicId)
            ->with('tags', 'folder');

        if ($folderId === 'uncategorized') {
            $query->whereNull('folder_id');
        } elseif ($folderId && $folderId !== 'all') {
            $query->where('folder_id', $folderId);
        }

        if ($type) {
            $query->where('asset_type', $type);
        }

        if ($search) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('file_name', 'like', "%{$search}%"));
        }

        if ($tagId) {
            $query->whereHas('tags', fn($q) => $q->where('mkt_asset_tags.id', $tagId));
        }

        $assets = $query->orderByDesc('created_at')->get()->map(fn($a) => [
            'id'          => $a->id,
            'filename'    => $a->file_name,
            'type'        => $a->asset_type,
            'size'        => $a->fileSizeForHumans(),
            'date'        => $a->created_at->format('M d, Y'),
            'tags'        => $a->tags->pluck('name')->toArray(),
            'campaign'    => '—',
            'dimensions'  => $a->width && $a->height ? "{$a->width} × {$a->height} px" : '—',
            'uploaded_by' => '—',
            'folder'      => $a->folder?->name ?? 'Uncategorized',
            'description' => $a->description,
            'duration'    => $a->duration_seconds ? gmdate('i:s', $a->duration_seconds) : null,
            'thumb_color' => 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)',
            'file_path'   => $a->file_path,
        ])->toArray();

        $selectedAsset = $assets[0] ?? null;

        // ── Tags for filter ──────────────────────────────────────────────────
        $tags = AssetTag::where('clinic_id', $clinicId)->orderBy('name')->get();

        // DAM assets — stub for Phase 5 (empty for now)
        $damAssets = [];

        return view('marketing.library.index', compact(
            'folders', 'assets', 'damAssets', 'selectedAsset', 'tags'
        ));
    }

    // -------------------------------------------------------------------------
    // Create Folder
    // -------------------------------------------------------------------------
    public function createFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100',
            'parent_id' => 'nullable|integer|exists:mkt_asset_folders,id',
        ]);

        $folder = AssetFolder::create([
            'clinic_id'  => $this->currentClinicId(),
            'name'       => $validated['name'],
            'parent_id'  => $validated['parent_id'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'folder' => ['id' => $folder->id, 'name' => $folder->name]]);
    }

    // -------------------------------------------------------------------------
    // Rename Folder
    // -------------------------------------------------------------------------
    public function renameFolder(Request $request, AssetFolder $folder): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        $folder->update(['name' => $request->name, 'updated_by' => auth()->id()]);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Delete Folder (block if has assets)
    // -------------------------------------------------------------------------
    public function deleteFolder(AssetFolder $folder): JsonResponse
    {
        if ($folder->hasAssets()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete a folder that contains assets. Move or delete assets first.'], 422);
        }

        $folder->delete();

        return response()->json(['success' => true]);
    }
}
