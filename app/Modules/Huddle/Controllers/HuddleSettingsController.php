<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Models\HuddleSetting;
use App\Modules\Huddle\Requests\UpdateHuddleSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HuddleSettingsController extends Controller
{
    /**
     * GET /huddle/settings
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $settings = HuddleSetting::where('branch_id', $branchId)
            ->get(['key', 'value'])
            ->pluck('value', 'key');

        return response()->json(['data' => $settings]);
    }

    /**
     * PATCH /huddle/settings
     * Admin only — enforced in UpdateHuddleSettingsRequest::authorize()
     */
    public function update(UpdateHuddleSettingsRequest $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $settings = $request->validated('settings');

        foreach ($settings as $key => $value) {
            HuddleSetting::updateOrCreate(
                ['branch_id' => $branchId, 'key' => $key],
                ['value'     => $value],
            );
        }

        return response()->json(['message' => 'Settings updated.']);
    }
}
