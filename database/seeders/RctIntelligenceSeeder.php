<?php
namespace Database\Seeders;
// Superseded by DentalTreatmentsMasterSeeder — run that instead.
class RctIntelligenceSeeder extends \Illuminate\Database\Seeder {
    public function run(): void {
        $this->call(DentalTreatmentsMasterSeeder::class);
    }
}
