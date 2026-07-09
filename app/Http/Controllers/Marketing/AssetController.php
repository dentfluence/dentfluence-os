<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\Concerns\ResolvesClinicId;
use App\Models\Marketing\MarketingAsset;
use App\Models\Marketing\AssetTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    use ResolvesClinicId;

    private const DISK       = 'public';

    /** Per-clinic asset storage path — was hardcoded to clinic 1 before Slice V2. */
    private function basePath(): string
    {
        return 'marketing/assets/' . $this->currentClinicId();
    }

    // -------------------------------------------------------------------------
    // Upload — handle file upload from library page
    // -------------------------------------------------------------------------
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'        => 'required|file|max:51200', // 50 MB max
            'folder_id'   => 'nullable|integer|exists:mkt_asset_folders,id',
            'campaign_id' => 'nullable|integer|exists:mkt_campaigns,id',
            'name'        => 'nullable|string|max:255',
        ]);

        $file     = $request->file('file');
        $path     = $file->store($this->basePath(), self::DISK);
        $mimeType = $file->getMimeType();

        // Determine asset_type from mime
        $assetType = match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            default                               => 'document',
        };

        // Get image dimensions if image
        $width  = null;
        $height = null;
        if ($assetType === 'image') {
            [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        }

        $asset = MarketingAsset::create([
            'clinic_id'   => $this->currentClinicId(),
            'folder_id'   => $validated['folder_id'] ?? null,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'name'        => $validated['name'] ?: $file->getClientOriginalName(),
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'mime_type'   => $mimeType,
            'file_size'   => $file->getSize(),
            'asset_type'  => $assetType,
            'width'       => $width,
            'height'      => $height,
            'created_by'  => auth()->id(),
            'updated_by'  => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'asset'   => [
                'id'       => $asset->id,
                'name'     => $asset->name,
                'file_path'=> Storage::disk(self::DISK)->url($path),
                'type'     => $assetType,
                'size'     => $asset->fileSizeForHumans(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Update — rename / change folder / toggle favourite
    // -------------------------------------------------------------------------
    public function update(Request $request, MarketingAsset $asset): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'folder_id'   => 'nullable|integer|exists:mkt_asset_folders,id',
            'alt_text'    => 'nullable|string|max:255',
            'is_favorite' => 'nullable|boolean',
        ]);

        $asset->update(array_merge($validated, ['updated_by' => auth()->id()]));

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Destroy — delete asset + remove file from storage
    // -------------------------------------------------------------------------
    public function destroy(MarketingAsset $asset): JsonResponse|RedirectResponse
    {
        Storage::disk(self::DISK)->delete($asset->file_path);
        $asset->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Asset deleted.');
    }

    // -------------------------------------------------------------------------
    // Add Tag
    // -------------------------------------------------------------------------
    public function addTag(Request $request, MarketingAsset $asset): JsonResponse
    {
        $validated = $request->validate(['tag' => 'required|string|max:100']);

        $tag = AssetTag::firstOrCreate(
            ['clinic_id' => $this->currentClinicId(), 'name' => $validated['tag']],
            ['created_by' => auth()->id(), 'updated_by' => auth()->id()]
        );

        $asset->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['success' => true, 'tag' => ['id' => $tag->id, 'name' => $tag->name]]);
    }

    // -------------------------------------------------------------------------
    // Remove Tag
    // -------------------------------------------------------------------------
    public function removeTag(Request $request, MarketingAsset $asset): JsonResponse
    {
        $request->validate(['tag_id' => 'required|integer']);

        $asset->tags()->detach($request->tag_id);

        return response()->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Storage Usage — total bytes for this clinic
    // -------------------------------------------------------------------------
    public function storageUsage(): JsonResponse
    {
        $bytes = MarketingAsset::where('clinic_id', $this->currentClinicId())->sum('file_size');
        $mb    = round($bytes / 1024 / 1024, 1);
        $gb    = round($bytes / 1024 / 1024 / 1024, 2);

        return response()->json([
            'bytes' => $bytes,
            'mb'    => $mb,
            'gb'    => $gb,
            'human' => $gb >= 1 ? "{$gb} GB" : "{$mb} MB",
        ]);
    }
}
