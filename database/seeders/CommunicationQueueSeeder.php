<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommunicationQueue;

/**
 * CommunicationQueueSeeder
 *
 * Seeds the communication_queue table from the legacy dummy-queue.json stub.
 * Run once after migration: php artisan db:seed --class=CommunicationQueueSeeder
 */
class CommunicationQueueSeeder extends Seeder
{
    public function run(): void
    {
        $stub = base_path('resources/stubs/communication/dummy-queue.json');

        if (!file_exists($stub)) {
            $this->command->warn('dummy-queue.json not found — skipping seed.');
            return;
        }

        $items = json_decode(file_get_contents($stub), true) ?? [];

        foreach ($items as $item) {
            CommunicationQueue::create([
                'person_name'     => $item['person_name'],
                'phone'           => $item['phone'],
                'whatsapp_number' => $item['whatsapp_number'] ?? null,
                'source'          => $item['source'],
                'type'            => $item['type'],
                'classification'  => $item['classification'],
                'status'          => $item['status'],
                'is_overdue'      => $item['is_overdue'] ?? false,
                'overdue_since'   => $item['overdue_since'] ?? null,
                'priority'        => $item['priority'],
                'note'            => $item['note'] ?? null,
                'tags'            => $item['tags'] ?? [],
                'assigned_to'     => $item['assigned_to'] ?? null,
                'assigned_avatar' => $item['assigned_avatar'] ?? null,
                'due_at'          => $item['due_at'] ?? null,
            ]);
        }

        $this->command->info('Seeded ' . count($items) . ' communication queue items from dummy JSON.');
    }
}
