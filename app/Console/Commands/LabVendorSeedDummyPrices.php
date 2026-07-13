<?php

namespace App\Console\Commands;

use App\Models\LabVendor;
use Illuminate\Console\Command;

/**
 * One-off helper: drops a handful of realistic dummy treatment/price rows
 * into a lab vendor's Services catalog, so the "Add Price List" bulk form and
 * the Lab tab's cost auto-fill can be tried out without typing sample data
 * by hand every time. Safe to re-run — skipped rows are logged, not duplicated
 * (matched on lab_vendor_id + service_name).
 *
 * Usage:
 *   php artisan lab:seed-dummy-prices                     (first active vendor)
 *   php artisan lab:seed-dummy-prices "DG Digital Lab"     (vendor by name)
 */
class LabVendorSeedDummyPrices extends Command
{
    protected $signature = 'lab:seed-dummy-prices {vendor? : Vendor name (defaults to the first active vendor)}';

    protected $description = 'Insert sample treatment/price rows into a lab vendor\'s Services catalog for testing.';

    /** [service_name, category, rate, turnaround_days] — typical India dental-lab pricing, rounded for realism. */
    private const ROWS = [
        ['Zirconia',                              'Crown & Bridge',       2500, 4],
        ['PFM (Porcelain Fused to Metal)',         'Crown & Bridge',       1800, 4],
        ['E-max (All Ceramic)',                    'Crown & Bridge',       3200, 5],
        ['Post & Core (Cast)',                     'Crown & Bridge',        900, 3],
        ['Complete Denture – Upper',                'Removable Prosthesis', 3500, 7],
        ['Complete Denture – Both',                 'Removable Prosthesis', 6500, 7],
        ['Cast Partial Denture (Metal Framework)',  'Removable Prosthesis', 4500, 6],
        ['Night Guard – Soft',                      'Occlusal Guard / Splint', 1200, 3],
        ['Implant Crown – Screw Retained',          'Implant Prosthesis',   4500, 6],
        ['Study Model',                              'Orthodontics',          300, 2],
    ];

    public function handle(): int
    {
        $vendorName = $this->argument('vendor');

        $vendor = $vendorName
            ? LabVendor::where('name', $vendorName)->first()
            : LabVendor::where('is_active', true)->orderBy('name')->first();

        if (! $vendor) {
            $this->error($vendorName
                ? "No lab vendor found named \"{$vendorName}\"."
                : 'No active lab vendor found — add one first.');
            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach (self::ROWS as [$name, $category, $rate, $days]) {
            $exists = $vendor->services()->where('service_name', $name)->exists();

            if ($exists) {
                $this->line("• {$name}: already exists — skipped.");
                $skipped++;
                continue;
            }

            $vendor->services()->create([
                'service_name'    => $name,
                'category'        => $category,
                'default_rate'    => $rate,
                'turnaround_days' => $days,
                'is_active'       => true,
            ]);

            $this->line("• {$name} ({$category}): Rs. {$rate}, {$days}d — added.");
            $created++;
        }

        $this->newLine();
        $this->info("Done for \"{$vendor->name}\". Added {$created}, skipped {$skipped} already-existing.");

        return self::SUCCESS;
    }
}
