<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Models\Prescription\Prescription;
use App\Models\TreatmentVisit;
use App\Services\Print\PdfRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * V.27 slice 2 — the phone's door to the printed document.
 *
 * The web print routes are SESSION authenticated; the app carries a token, so
 * it could never open them. That is the whole reason the app ended up drawing
 * its own documents in Dart, and why an invoice printed from the phone looked
 * nothing like the same invoice printed from a browser (measured side by side
 * on production, 18 Sep 2026).
 *
 * Every type here renders the SAME Blade the browser prints, through the same
 * PdfRenderer. There is no second layout to keep in step, by construction.
 *
 * Permission is per document type and mirrors the web gate for that module —
 * a token is not a bypass. Nothing here writes: printing from the phone must
 * never change a record, which is also why the web print-count bump on
 * prescriptions is deliberately not copied.
 */
class DocumentPdfController extends Controller
{
    private const TYPES = ['invoice', 'consultation', 'visit', 'prescription'];

    public function show(Request $request, string $type, int $id, PdfRenderer $renderer)
    {
        abort_unless(in_array($type, self::TYPES, true), 404, 'Unknown document type.');

        [$module, $view, $data, $filename] = $this->document($type, $id);

        abort_unless($request->user()?->canAccess($module, 'view'), 403, 'You cannot print this document.');

        $pdf = $renderer->fromView($view, $data);

        return response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
        ]);
    }

    /** @return array{0:string,1:string,2:array,3:string} module, view, view data, filename */
    private function document(string $type, int $id): array
    {
        $print  = AppSetting::group('print');
        $clinic = AppSetting::group('clinic');

        return match ($type) {
            'invoice' => (function () use ($id, $clinic) {
                $invoice = Invoice::with(['patient', 'items', 'payments'])->findOrFail($id);

                return ['finance', 'billing.print', compact('invoice', 'clinic'), $invoice->invoice_number ?: 'invoice'];
            })(),

            'consultation' => (function () use ($id, $print, $clinic) {
                $consultation = Consultation::with(['patient', 'doctor', 'treatmentPlans.items', 'specialtyModules'])
                    ->findOrFail($id);
                $prescription = Prescription::where('consultation_id', $consultation->id)
                    ->with('items')->latest()->first();

                return ['patients', 'consultations.print', compact('consultation', 'print', 'clinic', 'prescription'), 'consultation-' . $consultation->id];
            })(),

            'visit' => (function () use ($id, $print, $clinic) {
                $visit = TreatmentVisit::with(['patient', 'doctor', 'visitItems', 'labCases.vendor'])->findOrFail($id);

                return ['patients', 'visits.print', compact('visit', 'print', 'clinic'), 'visit-' . $visit->id];
            })(),

            'prescription' => (function () use ($id) {
                $prescription = Prescription::with(['prescribedBy', 'items.drug', 'patient'])->findOrFail($id);
                $patient      = $prescription->patient;

                return ['prescriptions', 'prescriptions.print', compact('patient', 'prescription'), 'prescription-' . $prescription->id];
            })(),
        };
    }
}
