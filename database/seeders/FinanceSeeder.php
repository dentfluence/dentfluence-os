<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds finance settings, expense categories, vendors, labs, and dummy staff.
 * Run: php artisan db:seed --class=FinanceSeeder
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        // ── Finance Settings (default for clinic_id=1) ──
        DB::table('finance_settings')->insertOrIgnore([
            'clinic_id'              => 1,
            'gst_enabled'            => false,
            'business_type'          => 'proprietorship',
            'daily_collection_target'=> 15000,
            'monthly_revenue_target' => 450000,
            'fy_start_month'         => '4',
            'currency'               => 'INR',
            'currency_symbol'        => '₹',
            'invoice_prefix'         => 'INV',
            'invoice_start_number'   => 1,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // ══════════════════════════════════════════════════════════
        // EXPENSE CATEGORIES
        // ══════════════════════════════════════════════════════════
        $categories = [
            ['name'=>'Rent',               'slug'=>'rent',               'is_system'=>1,'parent_id'=>null,'sort_order'=>1],
            ['name'=>'Maintenance',        'slug'=>'maintenance',        'is_system'=>1,'parent_id'=>null,'sort_order'=>2],
            ['name'=>'Electricity',        'slug'=>'electricity',        'is_system'=>1,'parent_id'=>null,'sort_order'=>3],
            ['name'=>'Internet / WiFi',    'slug'=>'internet',           'is_system'=>1,'parent_id'=>null,'sort_order'=>4],
            ['name'=>'Mobile Bills',       'slug'=>'mobile_bills',       'is_system'=>1,'parent_id'=>null,'sort_order'=>5],
            ['name'=>'Water',              'slug'=>'water',              'is_system'=>1,'parent_id'=>null,'sort_order'=>6],
            ['name'=>'Staff Salary',       'slug'=>'staff_salary',       'is_system'=>1,'parent_id'=>null,'sort_order'=>7],
            ['name'=>'Consultant Fees',    'slug'=>'consultant_fees',    'is_system'=>1,'parent_id'=>null,'sort_order'=>8],
            ['name'=>'Petty Cash',         'slug'=>'petty_cash',         'is_system'=>1,'parent_id'=>null,'sort_order'=>9],
            ['name'=>'Refreshments',       'slug'=>'refreshments',       'is_system'=>1,'parent_id'=>null,'sort_order'=>10],
            ['name'=>'Marketing',          'slug'=>'marketing',          'is_system'=>1,'parent_id'=>null,'sort_order'=>11],
            ['name'=>'Dental Materials',   'slug'=>'dental_materials',   'is_system'=>1,'parent_id'=>null,'sort_order'=>12],
            ['name'=>'Lab Expenses',       'slug'=>'lab_expenses',       'is_system'=>1,'parent_id'=>null,'sort_order'=>13],
            ['name'=>'Equipment',          'slug'=>'equipment',          'is_system'=>1,'parent_id'=>null,'sort_order'=>14],
            ['name'=>'Professional Fees',  'slug'=>'professional_fees',  'is_system'=>1,'parent_id'=>null,'sort_order'=>15],
            ['name'=>'Insurance',          'slug'=>'insurance',          'is_system'=>1,'parent_id'=>null,'sort_order'=>16],
            ['name'=>'CDE / Education',    'slug'=>'cde_education',      'is_system'=>1,'parent_id'=>null,'sort_order'=>17],
            ['name'=>'Office Supplies',    'slug'=>'office_supplies',    'is_system'=>0,'parent_id'=>null,'sort_order'=>18],
            ['name'=>'Travel',             'slug'=>'travel',             'is_system'=>0,'parent_id'=>null,'sort_order'=>19],
            ['name'=>'Miscellaneous',      'slug'=>'miscellaneous',      'is_system'=>0,'parent_id'=>null,'sort_order'=>20],
        ];

        foreach ($categories as &$cat) {
            $cat['clinic_id']  = 1;
            $cat['is_active']  = 1;
            $cat['created_at'] = now();
            $cat['updated_at'] = now();
        }
        DB::table('finance_expense_categories')->insertOrIgnore($categories);

        // Sub-categories: Maintenance
        $maintId = DB::table('finance_expense_categories')->where('slug','maintenance')->value('id');
        if ($maintId) {
            DB::table('finance_expense_categories')->insertOrIgnore([
                ['clinic_id'=>1,'parent_id'=>$maintId,'name'=>'AC Service',        'slug'=>'ac_service',        'is_system'=>1,'is_active'=>1,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$maintId,'name'=>'Plumbing',          'slug'=>'plumbing',          'is_system'=>1,'is_active'=>1,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$maintId,'name'=>'Electrical Repair', 'slug'=>'electrical_repair', 'is_system'=>1,'is_active'=>1,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$maintId,'name'=>'Equipment Service', 'slug'=>'equipment_service', 'is_system'=>1,'is_active'=>1,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$maintId,'name'=>'Housekeeping',      'slug'=>'housekeeping',      'is_system'=>0,'is_active'=>1,'sort_order'=>5,'created_at'=>now(),'updated_at'=>now()],
            ]);
        }

        // Sub-categories: Marketing
        $marketingId = DB::table('finance_expense_categories')->where('slug','marketing')->value('id');
        if ($marketingId) {
            DB::table('finance_expense_categories')->insertOrIgnore([
                ['clinic_id'=>1,'parent_id'=>$marketingId,'name'=>'Meta Ads',    'slug'=>'meta_ads',    'is_system'=>0,'is_active'=>1,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$marketingId,'name'=>'Google Ads',  'slug'=>'google_ads',  'is_system'=>0,'is_active'=>1,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$marketingId,'name'=>'Printing',    'slug'=>'printing',    'is_system'=>0,'is_active'=>1,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$marketingId,'name'=>'Hoarding',    'slug'=>'hoarding',    'is_system'=>0,'is_active'=>1,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
            ]);
        }

        // Sub-categories: Professional Fees
        $profId = DB::table('finance_expense_categories')->where('slug','professional_fees')->value('id');
        if ($profId) {
            DB::table('finance_expense_categories')->insertOrIgnore([
                ['clinic_id'=>1,'parent_id'=>$profId,'name'=>'CA Fees',   'slug'=>'ca_fees',   'is_system'=>0,'is_active'=>1,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$profId,'name'=>'Legal Fees','slug'=>'legal_fees','is_system'=>0,'is_active'=>1,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
            ]);
        }

        // Sub-categories: Insurance
        $insuranceId = DB::table('finance_expense_categories')->where('slug','insurance')->value('id');
        if ($insuranceId) {
            DB::table('finance_expense_categories')->insertOrIgnore([
                ['clinic_id'=>1,'parent_id'=>$insuranceId,'name'=>'Clinic Insurance',    'slug'=>'clinic_insurance',    'is_system'=>1,'is_active'=>1,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$insuranceId,'name'=>'Equipment Insurance', 'slug'=>'equipment_insurance', 'is_system'=>1,'is_active'=>1,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$insuranceId,'name'=>'Health Insurance',    'slug'=>'health_insurance',    'is_system'=>1,'is_active'=>1,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$insuranceId,'name'=>'Professional Indemnity','slug'=>'professional_indemnity','is_system'=>1,'is_active'=>1,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
            ]);
        }

        // Sub-categories: CDE / Education
        $cdeId = DB::table('finance_expense_categories')->where('slug','cde_education')->value('id');
        if ($cdeId) {
            DB::table('finance_expense_categories')->insertOrIgnore([
                ['clinic_id'=>1,'parent_id'=>$cdeId,'name'=>'Conference / Seminar','slug'=>'conference',  'is_system'=>1,'is_active'=>1,'sort_order'=>1,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$cdeId,'name'=>'Online Course',       'slug'=>'online_course','is_system'=>1,'is_active'=>1,'sort_order'=>2,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$cdeId,'name'=>'Books / Journals',    'slug'=>'books',        'is_system'=>1,'is_active'=>1,'sort_order'=>3,'created_at'=>now(),'updated_at'=>now()],
                ['clinic_id'=>1,'parent_id'=>$cdeId,'name'=>'Workshop / Hands-on', 'slug'=>'workshop',     'is_system'=>1,'is_active'=>1,'sort_order'=>4,'created_at'=>now(),'updated_at'=>now()],
            ]);
        }

        // ══════════════════════════════════════════════════════════
        // VENDORS (5 suppliers + 2 labs)
        // ══════════════════════════════════════════════════════════
        $vendors = [
            // ── 5 General Vendors ──
            [
                'vendor_name'  => 'Sharma Property',
                'company_name' => 'Sharma Property Management',
                'vendor_type'  => 'other',           // rent
                'phone'        => '+91 98765 00001',
                'email'        => 'sharma.property@example.com',
                'city'         => 'Pune',
                'credit_days'  => 0,
                'notes'        => 'Clinic landlord — monthly rent ₹35,000',
            ],
            [
                'vendor_name'  => 'CoolAir Services',
                'company_name' => 'CoolAir HVAC Solutions',
                'vendor_type'  => 'other',           // maintenance / AC
                'phone'        => '+91 98765 00002',
                'email'        => 'coolair@example.com',
                'city'         => 'Pune',
                'credit_days'  => 7,
                'notes'        => 'AC service & maintenance vendor',
            ],
            [
                'vendor_name'  => 'Prime Dental Supply',
                'company_name' => 'Prime Dental Supplies Pvt Ltd',
                'vendor_type'  => 'dental_supplier',
                'phone'        => '+91 77777 23456',
                'email'        => 'orders@primedental.example.com',
                'city'         => 'Mumbai',
                'gstin'        => '27AABCP1234A1Z5',
                'credit_days'  => 15,
                'notes'        => 'Primary dental consumables supplier',
            ],
            [
                'vendor_name'  => 'Osstem India',
                'company_name' => 'Osstem India Pvt Ltd',
                'vendor_type'  => 'implant_company',
                'phone'        => '+91 80001 56789',
                'email'        => 'sales@osstem.example.com',
                'city'         => 'Mumbai',
                'gstin'        => '27AABCO5678B1Z3',
                'credit_days'  => 45,
                'notes'        => 'Implant system supplier — Osstem brand',
            ],
            [
                'vendor_name'  => 'CA Ramesh Joshi',
                'company_name' => 'Ramesh Joshi & Associates',
                'vendor_type'  => 'ca',
                'phone'        => '+91 98001 34567',
                'email'        => 'ramesh.joshi@example.com',
                'city'         => 'Pune',
                'credit_days'  => 0,
                'notes'        => 'Chartered accountant — quarterly filing',
            ],
            // ── 2 Labs ──
            [
                'vendor_name'  => 'City Dental Lab',
                'company_name' => 'City Dental Laboratory Pvt Ltd',
                'vendor_type'  => 'lab',
                'phone'        => '+91 98765 12345',
                'email'        => 'work@citydentlab.example.com',
                'city'         => 'Pune',
                'credit_days'  => 21,
                'notes'        => 'Crown, bridge & removable prosthetics lab',
            ],
            [
                'vendor_name'  => 'Digital Dentistry Works',
                'company_name' => 'Digital Dentistry Works LLP',
                'vendor_type'  => 'lab',
                'phone'        => '+91 91234 78901',
                'email'        => 'digital@ddworks.example.com',
                'city'         => 'Pune',
                'credit_days'  => 14,
                'notes'        => 'CAD/CAM zirconia & digital prosthetics lab',
            ],
        ];

        foreach ($vendors as $v) {
            $exists = DB::table('finance_vendors')
                ->where('clinic_id', 1)
                ->where('vendor_name', $v['vendor_name'])
                ->exists();

            if (! $exists) {
                DB::table('finance_vendors')->insert(array_merge([
                    'clinic_id'          => 1,
                    'company_name'       => null,
                    'phone'              => null,
                    'email'              => null,
                    'address'            => null,
                    'city'               => null,
                    'state'              => 'Maharashtra',
                    'pincode'            => null,
                    'gstin'              => null,
                    'pan'                => null,
                    'credit_days'        => 0,
                    'credit_limit'       => 0,
                    'total_purchases'    => 0,
                    'total_paid'         => 0,
                    'outstanding_amount' => 0,
                    'last_purchase_date' => null,
                    'bank_name'          => null,
                    'account_number'     => null,
                    'ifsc_code'          => null,
                    'account_name'       => null,
                    'documents'          => null,
                    'is_active'          => 1,
                    'notes'              => null,
                    'created_by'         => 1,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ], $v));
            }
        }

        // ══════════════════════════════════════════════════════════
        // STAFF / DOCTORS for Salary & Consultant dropdowns
        // These map to the app users table (role-based).
        // We just store them in finance_vendors as vendor_type=consultant
        // OR rely on users table. We add a finance_staff config table entry
        // via app_settings for easy JS lookup without a new migration.
        // ══════════════════════════════════════════════════════════
        // Staff (salary)
        $staff = [
            ['name'=>'Samiksha',  'role'=>'staff'],
            ['name'=>'Runali',    'role'=>'staff'],
            ['name'=>'Ankita',    'role'=>'staff'],
            ['name'=>'Ashwini',   'role'=>'staff'],
            ['name'=>'Dr. Nirmita','role'=>'doctor'],
            ['name'=>'Dr. Sumit', 'role'=>'doctor'],
            ['name'=>'Dr. Sayli', 'role'=>'doctor'],
        ];
        // Consultants
        $consultants = [
            ['name'=>'Dr. Devendra', 'role'=>'consultant'],
            ['name'=>'Dr. Niraj',    'role'=>'consultant'],
        ];

        // Store combined list as a JSON app_setting for use in the UI
        $allPersonnel = array_merge($staff, $consultants);
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'finance_personnel'],
            [
                'value'      => json_encode($allPersonnel),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✅ Finance module seeded — categories, vendors, labs & personnel saved.');
    }
}
