<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * G-12 — the "Treatments" column on the accountant handoff.
 *
 * Both export paths plucked `invoice_items.treatment_name`, a column that has
 * never existed on that table. pluck() on a missing attribute yields nulls,
 * filter() drops them, implode() returns '' — so the column shipped blank for
 * months, silently, and no test noticed. That is the reason this file exists.
 *
 * Rule: the linked Treatment master names the line; a line with no master link
 * (retail products, legacy rows) falls back to its own description. Never blank.
 */
class CaExportTreatmentsColumnTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private const DAY = '2026-08-18';

    private function seedPaidInvoice(): void
    {
        $patient = Patient::create([
            'name'      => 'G12 Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);

        $treatment = Treatment::create([
            'treatment_category_id' => TreatmentCategory::create(['name' => 'Endodontics'])->id,
            'name'                  => 'Root Canal Treatment',
            'default_price'         => 6000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => self::DAY,
            'status'         => 'draft',
        ]);

        // Line 1 — linked to the Treatment master.
        InvoiceItem::create([
            'invoice_id'   => $invoice->id,
            'treatment_id' => $treatment->id,
            'description'  => 'Root Canal Treatment',
            'unit_price'   => 6000,
            'qty'          => 1,
            'net_amount'   => 6000,
            'gst_pct'      => 0,
            'gst_amount'   => 0,
            'total'        => 6000,
        ]);

        // Line 2 — no master link (a retail product). Must fall back, not blank.
        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Sensitive Toothpaste',
            'unit_price'  => 400,
            'qty'         => 1,
            'net_amount'  => 400,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => 400,
        ]);

        $invoice->recalculate();

        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $patient->id,
            'amount'       => 6400,
            'payment_mode' => 'cash',
            'payment_date' => self::DAY,
        ]);
    }

    private function exportUrl(string $format): string
    {
        return route('finance.ca-export', [
            'from'     => self::DAY,
            'to'       => self::DAY,
            'download' => 1,
            'format'   => $format,
        ]);
    }

    public function test_ca_export_csv_lists_treatment_names(): void
    {
        $this->seedPaidInvoice();

        $content = $this->actingAs($this->legacyAdminUser())
            ->get($this->exportUrl('csv'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Treatments', $content);
        $this->assertStringContainsString('Root Canal Treatment', $content);
        $this->assertStringContainsString('Sensitive Toothpaste', $content);
    }

    public function test_ca_export_xlsx_lists_treatment_names(): void
    {
        $this->seedPaidInvoice();

        $response = $this->actingAs($this->legacyAdminUser())
            ->get($this->exportUrl('excel'))
            ->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);

        $sheet = IOFactory::load($path)->getSheetByName('Income');
        $cell  = (string) $sheet->getCell('E2')->getValue();

        $this->assertNotSame('', trim($cell), 'The Treatments column exported blank again.');
        $this->assertStringContainsString('Root Canal Treatment', $cell);
        $this->assertStringContainsString('Sensitive Toothpaste', $cell);
    }

    public function test_income_export_xlsx_lists_treatment_names(): void
    {
        $this->seedPaidInvoice();

        $response = $this->actingAs($this->legacyAdminUser())
            ->get(route('finance.income.export', [
                'from'   => self::DAY,
                'to'     => self::DAY,
                'format' => 'excel',
            ]))
            ->assertOk();

        $path  = $response->baseResponse->getFile()->getPathname();
        $sheet = IOFactory::load($path)->getSheetByName('Income');

        $this->assertStringContainsString(
            'Root Canal Treatment',
            (string) $sheet->getCell('E2')->getValue()
        );
    }
}
