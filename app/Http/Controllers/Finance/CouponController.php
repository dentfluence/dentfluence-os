<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CouponCode;
use App\Models\CouponUsage;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = CouponCode::withTrashed()
            ->withCount('usages')
            ->latest()
            ->paginate(20);

        // --- Dashboard stats ---
        $totalCoupons    = CouponCode::withTrashed()->count();
        $activeCoupons   = CouponCode::active()->count();

        // Expired or inactive: not soft-deleted but either is_active=false OR past valid_until
        $expiredInactive = CouponCode::where(function ($q) {
                                $q->where('is_active', false)
                                  ->orWhere('valid_until', '<', today());
                            })->count();

        $totalRedemptions  = CouponUsage::count();
        $totalDiscountGiven = CouponUsage::sum('discount_applied');

        // Most used coupon by uses_count column (maintained on each redemption)
        $mostUsedCoupon = CouponCode::withTrashed()
                            ->where('uses_count', '>', 0)
                            ->orderBy('uses_count', 'desc')
                            ->first();

        return view('finance.coupons.index', compact(
            'coupons',
            'totalCoupons', 'activeCoupons', 'expiredInactive',
            'totalRedemptions', 'totalDiscountGiven', 'mostUsedCoupon'
        ));
    }

    public function create()
    {
        return view('finance.coupons.form', ['coupon' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();

        CouponCode::create($data);

        return redirect()->route('finance.coupons.index')
            ->with('success', 'Coupon code created.');
    }

    public function edit(CouponCode $coupon)
    {
        return view('finance.coupons.form', compact('coupon'));
    }

    public function update(Request $request, CouponCode $coupon)
    {
        $coupon->update($this->validated($request));

        return redirect()->route('finance.coupons.index')
            ->with('success', 'Coupon updated.');
    }

    public function toggle(CouponCode $coupon)
    {
        $coupon->update(['is_active' => !$coupon->is_active]);

        return back()->with('success', 'Coupon ' . ($coupon->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(CouponCode $coupon)
    {
        $coupon->delete(); // soft delete
        return back()->with('success', 'Coupon deleted.');
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'code'                  => 'required|string|max:50|uppercase',
            'description'           => 'nullable|string|max:300',
            'discount_type'         => 'required|in:flat,percentage',
            'discount_value'        => 'required|numeric|min:0',
            'max_uses_global'       => 'nullable|integer|min:0',
            'max_uses_per_patient'  => 'nullable|integer|min:1',
            'valid_from'            => 'nullable|date',
            'valid_until'           => 'nullable|date|after_or_equal:valid_from',
            'min_invoice_amount'    => 'nullable|numeric|min:0',
            'applicable_treatments' => 'nullable|array',
            'is_active'             => 'boolean',
        ]) + [
            'max_uses_global'      => 0,
            'max_uses_per_patient' => 1,
            'min_invoice_amount'   => 0,
            'is_active'            => true,
        ];
    }
}
