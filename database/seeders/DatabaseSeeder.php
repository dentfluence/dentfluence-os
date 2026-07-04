<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ClinicUsersSeeder::class,
            RolePermissionSeeder::class,       // Roles, modules & default permission matrix (required for Roles & Permissions page)
            RoleBillingPermissionSeeder::class, // Per-role billing action limits (depends on RolePermissionSeeder)
            CmsEduSeeder::class,
            InventoryDemoSeeder::class, // Dental inventory demo data
            MasterDemoSeeder::class,    // 10 patients + appointments + consultations + visits + plans + finance
            LeadSeeder::class,          // PRM leads from dummy JSON
            RxMasterSeeder::class,             // Prescription masters: drugs, templates, rules
            RxDentalBrandsSeeder::class,       // 120+ Indian brand drugs (ICPA, Mankind, Dr. Reddy's, IPCA, Abbott, etc.)
            RxPrescriptionTemplatesSeeder::class, // 20 dental treatment prescription templates
            MarketingModuleSeeder::class,          // Register marketing module as active
            FestivalDateSeeder::class,             // 20+ festival + dental awareness dates
        ]);
    }
}