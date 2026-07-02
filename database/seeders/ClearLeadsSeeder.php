<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClearLeadsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('lead_activities')->delete();
        DB::table('leads')->delete();
        DB::table('communication_queue')->delete();
        DB::table('comm_activity_logs')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('✅ All leads, lead activities, and communication queue cleared.');
    }
}
