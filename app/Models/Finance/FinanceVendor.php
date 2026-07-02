<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceVendor extends Model
{
    use SoftDeletes;

    protected $table = 'finance_vendors';

    protected $fillable = [
        'clinic_id', 'vendor_name', 'company_name', 'vendor_type',
        'phone', 'email', 'address', 'city', 'state', 'pincode',
        'gstin', 'pan', 'credit_days', 'credit_limit',
        'total_purchases', 'total_paid', 'outstanding_amount', 'last_purchase_date',
        'bank_name', 'account_number', 'ifsc_code', 'account_name',
        'documents', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'last_purchase_date'  => 'date',
        'is_active'           => 'boolean',
        'documents'           => 'array',
        'total_purchases'     => 'decimal:2',
        'outstanding_amount'  => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function expenses()  { return $this->hasMany(FinanceExpense::class, 'vendor_id'); }
    public function payments()  { return $this->hasMany(FinanceVendorPayment::class, 'vendor_id'); }

    /** Phase 1: vendor invoices raised against this Finance vendor */
    public function vendorInvoices()
    {
        return $this->hasMany(\App\Models\Procurement\VendorInvoice::class, 'finance_vendor_id');
    }

    /** Phase 1: Inventory vendor that is mirrored here */
    public function inventoryVendor()
    {
        return $this->hasOne(\App\Models\Inventory\InventoryVendor::class, 'finance_vendor_id');
    }

    /** Phase 1: Lab vendor that is mirrored here */
    public function labVendor()
    {
        return $this->hasOne(\App\Models\LabVendor::class, 'finance_vendor_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->vendor_name;
    }

    /**
     * Phase 1: human-readable type labels (includes all new vendor types).
     */
    public static function typeLabels(): array
    {
        return [
            'lab'               => 'Lab',
            'implant_company'   => 'Implant Company',
            'dental_supplier'   => 'Dental Supplier',
            'marketing_agency'  => 'Marketing Agency',
            'software_vendor'   => 'Software Vendor',
            'consultant'        => 'Consultant',
            'ca'                => 'CA / Accountant',
            'utility_provider'  => 'Utility Provider',
            'equipment_supplier'=> 'Equipment Supplier',
            'rent'              => 'Rent',
            'electricity'       => 'Electricity',
            'water'             => 'Water',
            'internet'          => 'Internet',
            'salary'            => 'Salary',
            'lawyer'            => 'Lawyer',
            'amc'               => 'AMC / Maintenance',
            'office_supplies'   => 'Office Supplies',
            'miscellaneous'     => 'Miscellaneous',
            'other'             => 'Other',
        ];
    }
}
