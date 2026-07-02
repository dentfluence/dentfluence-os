<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Tag;
use App\Models\Treatment;
use App\Models\WalletCampaign;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletCampaignController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $campaigns = WalletCampaign::orderByDesc('created_at')->paginate(20);
        return view('finance.wallet-campaigns.index', compact('campaigns'));
    }

    // ── Create form ──────────────────────────────────────────────────────────

    public function create()
    {
        $treatments = Treatment::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $tags       = Tag::orderBy('name')->get(['id', 'name', 'color']);
        $areas      = Patient::whereNotNull('area')->distinct()->orderBy('area')->pluck('area');
        $sources    = Patient::whereNotNull('source')->distinct()->orderBy('source')->pluck('source');

        return view('finance.wallet-campaigns.create', compact(
            'treatments', 'tags', 'areas', 'sources'
        ));
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'name'                    => 'required|string|max:200',
            'description'             => 'nullable|string|max:1000',
            'amount'                  => 'required|numeric|min:1',
            'expiry_date'             => 'required|date|after:today',
            'treatment_scope'         => 'nullable|in:all,specific',
            'applicable_treatments'   => 'nullable|array',
            'applicable_treatments.*' => 'integer|exists:treatments,id',
            'filter_gender'           => 'nullable|array',
            'filter_area'             => 'nullable|array',
            'filter_tag_ids'          => 'nullable|array',
            'filter_tag_ids.*'        => 'integer|exists:tags,id',
            'filter_age_min'          => 'nullable|integer|min:0|max:150',
            'filter_age_max'          => 'nullable|integer|min:0|max:150|gte:filter_age_min',
            'filter_membership'       => 'nullable|array',
            'filter_source'           => 'nullable|array',
            'notes'                   => 'nullable|string|max:1000',
        ]);

        $applicableTreatments = null;
        if ($request->treatment_scope === 'specific' && !empty($request->applicable_treatments)) {
            $applicableTreatments = array_map('intval', $request->applicable_treatments);
        }

        $campaign = WalletCampaign::create([
            'name'                   => $request->name,
            'description'            => $request->description,
            'amount'                 => $request->amount,
            'expiry_date'            => $request->expiry_date,
            'applicable_treatments'  => $applicableTreatments,
            'filter_gender'          => $request->filter_gender ?: null,
            'filter_area'            => $request->filter_area ?: null,
            'filter_tag_ids'         => $request->filter_tag_ids ?: null,
            'filter_age_min'         => $request->filter_age_min ?: null,
            'filter_age_max'         => $request->filter_age_max ?: null,
            'filter_membership'      => $request->filter_membership ?: null,
            'filter_source'          => $request->filter_source ?: null,
            'notes'                  => $request->notes,
            'status'                 => 'draft',
            'created_by'             => auth()->id(),
        ]);

        return redirect()->route('finance.wallet-campaigns.show', $campaign)
            ->with('success', 'Campaign "' . $campaign->name . '" created. Preview and apply when ready.');
    }

    // ── Show / Detail ────────────────────────────────────────────────────────

    public function show(WalletCampaign $walletCampaign)
    {
        $matchCount  = $walletCampaign->matchingPatientCount();
        $previewList = $walletCampaign->matchingPatientsQuery()
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'phone', 'gender', 'area', 'membership_status']);

        $treatments = Treatment::whereIn('id', $walletCampaign->applicable_treatments ?? [])
            ->pluck('name')
            ->all();

        return view('finance.wallet-campaigns.show', compact(
            'walletCampaign', 'matchCount', 'previewList', 'treatments'
        ));
    }

    // ── AJAX: Live preview patient count ─────────────────────────────────────

    public function preview(Request $request)
    {
        // Build a temporary campaign model from the posted filters (no DB save)
        $campaign = new WalletCampaign([
            'filter_gender'     => $request->filter_gender ?: null,
            'filter_area'       => $request->filter_area ?: null,
            'filter_tag_ids'    => $request->filter_tag_ids ?: null,
            'filter_age_min'    => $request->filter_age_min ?: null,
            'filter_age_max'    => $request->filter_age_max ?: null,
            'filter_membership' => $request->filter_membership ?: null,
            'filter_source'     => $request->filter_source ?: null,
        ]);

        $count   = $campaign->matchingPatientCount();
        $preview = $campaign->matchingPatientsQuery()
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'area'])
            ->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'phone' => $p->phone,
                'area'  => $p->area,
            ]);

        return response()->json([
            'count'   => $count,
            'preview' => $preview,
        ]);
    }

    // ── Apply: Bulk credit all matching patients ──────────────────────────────

    public function apply(WalletCampaign $walletCampaign)
    {
        if (! $walletCampaign->isDraft()) {
            return back()->with('error', 'This campaign has already been applied.');
        }

        $walletService = new WalletService();
        $patients      = $walletCampaign->matchingPatientsQuery()->get(['id']);
        $count         = 0;

        DB::transaction(function () use ($walletCampaign, $walletService, $patients, &$count) {
            foreach ($patients as $patient) {
                $walletService->credit(
                    patientId:            $patient->id,
                    amount:               (float) $walletCampaign->amount,
                    creditType:           'promotional',
                    expiryDate:           $walletCampaign->expiry_date->format('Y-m-d'),
                    notes:                $walletCampaign->name,
                    createdBy:            auth()->id(),
                    campaignName:         $walletCampaign->name,
                    applicableTreatments: $walletCampaign->applicable_treatments,
                );
                $count++;
            }

            $walletCampaign->update([
                'status'             => 'applied',
                'patients_credited'  => $count,
                'total_amount_issued'=> $count * (float) $walletCampaign->amount,
                'applied_at'         => now(),
            ]);
        });

        return redirect()->route('finance.wallet-campaigns.show', $walletCampaign)
            ->with('success', "Campaign applied! ₹" . number_format($walletCampaign->amount, 0)
                . " credited to {$count} patients.");
    }

    // ── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(WalletCampaign $walletCampaign)
    {
        if (! $walletCampaign->isDraft()) {
            return back()->with('error', 'Only draft campaigns can be cancelled.');
        }

        $walletCampaign->update(['status' => 'cancelled']);

        return redirect()->route('finance.wallet-campaigns.index')
            ->with('success', 'Campaign cancelled.');
    }

    // ── Delete (draft only) ───────────────────────────────────────────────────

    public function destroy(WalletCampaign $walletCampaign)
    {
        if (! $walletCampaign->isDraft()) {
            return back()->with('error', 'Only draft campaigns can be deleted. Applied campaigns cannot be removed as credits are already in patient wallets.');
        }

        $name = $walletCampaign->name;
        $walletCampaign->delete();

        return redirect()->route('finance.wallet-campaigns.index')
            ->with('success', "Campaign \"{$name}\" deleted.");
    }
}
