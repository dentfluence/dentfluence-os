<?php

namespace App\Http\Controllers;

use App\Models\DataRequest;
use App\Models\Patient;
use App\Services\DataRightsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * DataRequestController (DPDP 5.2)
 * --------------------------------
 * Admin queue for patient-rights requests + the actions to fulfil them.
 * Real work lives in DataRightsService.
 */
class DataRequestController extends Controller
{
    public function __construct(private DataRightsService $rights) {}

    public function index(Request $request)
    {
        $query = DataRequest::with('patient', 'assignedTo')->latest('requested_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $requests = $query->paginate(25)->withQueryString();
        $counts   = [
            'open'      => DataRequest::open()->count(),
            'overdue'   => DataRequest::open()->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'completed' => DataRequest::where('status', 'completed')->count(),
        ];

        return view('data-requests.index', compact('requests', 'counts'));
    }

    public function create(Request $request)
    {
        // Optionally pre-select a patient via ?patient={id}
        $patient = $request->filled('patient') ? Patient::find($request->input('patient')) : null;
        return view('data-requests.create', compact('patient'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => ['required', 'exists:patients,id'],
            'type'           => ['required', Rule::in(DataRequest::TYPES)],
            'details'        => ['nullable', 'string', 'max:2000'],
            'requested_via'  => ['nullable', Rule::in(['web', 'portal', 'email', 'phone', 'paper'])],
            'requester_name' => ['nullable', 'string', 'max:120'],
            // nominee extras (only used when type = nominee)
            'nominee_name'         => ['nullable', 'string', 'max:120'],
            'nominee_relationship' => ['nullable', 'string', 'max:60'],
            'nominee_contact'      => ['nullable', 'string', 'max:60'],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);

        $payload = null;
        if ($data['type'] === 'nominee') {
            $payload = [
                'nominee_name'         => $data['nominee_name'] ?? null,
                'nominee_relationship' => $data['nominee_relationship'] ?? null,
                'nominee_contact'      => $data['nominee_contact'] ?? null,
            ];
        }

        $req = $this->rights->create($patient, $data['type'], [
            'details'        => $data['details'] ?? null,
            'requested_via'  => $data['requested_via'] ?? 'web',
            'requester_name' => $data['requester_name'] ?? null,
            'payload'        => $payload,
        ]);

        return redirect()->route('data-rights.show', $req)->with('success', "Request {$req->reference} logged.");
    }

    public function show(DataRequest $dataRequest)
    {
        $dataRequest->load('patient', 'assignedTo', 'resolvedBy');
        return view('data-requests.show', ['req' => $dataRequest]);
    }

    public function update(Request $request, DataRequest $dataRequest)
    {
        $data = $request->validate([
            'status'      => ['nullable', Rule::in(DataRequest::STATUSES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'resolution'  => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('assigned_to', $data)) {
            $this->rights->assign($dataRequest, $data['assigned_to']);
        }
        $dataRequest->update(array_filter([
            'status'     => $data['status'] ?? null,
            'resolution' => $data['resolution'] ?? null,
        ], fn ($v) => ! is_null($v)));

        return back()->with('success', 'Request updated.');
    }

    public function complete(Request $request, DataRequest $dataRequest)
    {
        $request->validate(['resolution' => ['nullable', 'string', 'max:2000']]);
        $this->rights->complete($dataRequest, $request->input('resolution'));
        return back()->with('success', "Request {$dataRequest->reference} completed.");
    }

    public function reject(Request $request, DataRequest $dataRequest)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->rights->reject($dataRequest, $data['reason']);
        return back()->with('success', "Request {$dataRequest->reference} rejected.");
    }

    /** ACCESS right — download the patient's data as a JSON file. */
    public function download(DataRequest $dataRequest)
    {
        abort_unless($dataRequest->type === 'access', 404);

        $export = $this->rights->compileAccessExport($dataRequest->patient);
        $json   = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $name   = $dataRequest->reference . '-data-export.json';

        return response($json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }

    /**
     * ERASURE right — anonymise the patient. DESTRUCTIVE to personal data, so
     * the admin must type ERASE to confirm. Clinical/financial records are kept.
     */
    public function erase(Request $request, DataRequest $dataRequest)
    {
        abort_unless($dataRequest->type === 'erasure', 404);

        $request->validate([
            'confirm' => ['required', 'in:ERASE'],
        ], [], ['confirm' => 'confirmation']);

        $this->rights->anonymisePatient($dataRequest->patient);
        $this->rights->complete($dataRequest, 'Personal data anonymised; clinical/financial records retained per statutory requirements.');

        return back()->with('success', 'Patient personal data anonymised and request completed.');
    }
}
