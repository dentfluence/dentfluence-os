<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(resource_path('stubs/communication/dummy-leads.json'));
        $rows = json_decode($json, true);

        foreach ($rows as $row) {
            $lead = Lead::create([
                'name'                => $row['name'],
                'phone'               => $row['phone'],
                'alt_phone'           => $row['alt_phone'] ?? null,
                'email'               => $row['email'] ?: null,
                'stage'               => $row['stage'],
                'source'              => $row['source'] ?? null,
                'urgency'             => $row['urgency'] ?? 'low',
                'treatment'           => $row['treatment'] ?? null,
                'secondary_treatment' => $row['secondary_treatment'] ?? null,
                'assigned_to'         => $row['assigned_to'] ?? null,
                'followup_date'       => $row['followup_date'] ?: null,
                'followup_time'       => $row['followup_time'] ?? null,
                'preferred_contact'   => $row['preferred_contact'] ?? 'call',
                'notes'               => $row['notes'] ?? null,
                'tags'                => $row['tags'] ?? [],
                'dob'                 => $row['dob'] ?: null,
                'gender'              => $row['gender'] ?? null,
                'occupation'          => $row['occupation'] ?: null,
                'location'            => $row['location'] ?? null,
                'language'            => $row['language'] ?? null,
                'referred_by'         => $row['referred_by'] ?: null,
                'created_at'          => $row['created_at'] ?? now(),
            ]);

            // Seed activity log
            foreach ($row['activity'] ?? [] as $act) {
                $lead->activities()->create([
                    'type'          => $act['type'],
                    'label'         => $act['label'],
                    'outcome'       => $act['outcome'] ?? null,
                    'note'          => $act['note'] ?? null,
                    'activity_date' => $act['date'] ?? today(),
                    'activity_time' => $act['time'] ?? null,
                    'by'            => $act['by'] ?? 'Staff',
                ]);
            }
        }

        $this->command->info('Seeded ' . count($rows) . ' leads from dummy JSON.');
    }
}
