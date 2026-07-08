<?php

namespace App\Http\Controllers;

use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventorySubType;
use App\Models\Inventory\InventoryVariant;
use App\Models\Inventory\InventoryVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * InventoryProductImportController — bulk Excel onboarding for the
 * inventory product master (2026-07-07), with a second "Vendors" tab in
 * the same workbook so suppliers can be onboarded in the same pass.
 *
 * Scoped to CLINICAL products only (the common onboarding case — a
 * clinic's existing consumables + supplier list). Saleable/FMCG products
 * and photos are NOT covered here; those are rare enough at bulk-load time
 * that they're better added afterwards from the Add/Edit Product form.
 *
 * Same preview -> confirm two-step flow as PatientImportExportController,
 * with two additions: (1) row-level error reporting, since product rows
 * have more hard-required fields than a patient row does, so silent skips
 * would hide real data-entry problems; (2) a second sheet for vendors —
 * matches the same multi-sheet pattern FinanceController's CA export uses
 * (Spreadsheet::createSheet()->setTitle(...)).
 *
 * Category / Sub Type / Variant / Vendor are all find-or-create by name —
 * a fresh clinic's spreadsheet won't have any of these seeded yet, and
 * requiring them to be created one-by-one in Settings first would defeat
 * the point of a bulk import.
 */
class InventoryProductImportController extends Controller
{
    private const COLUMN_MAP = [
        'product_name'         => ['product name', 'name', 'item name'],
        'category'             => ['category', 'category name'],
        'sub_type'             => ['sub type', 'subtype', 'sub-type'],
        'variant'              => ['variant', 'size', 'shade', 'variant / size / shade'],
        'brand'                => ['brand', 'brand name'],
        'alternative_brands'   => ['alternative brands', 'alt brands', 'other brands'],
        'company_name'         => ['company', 'company name', 'manufacturer'],
        'packaging_type'       => ['packaging type', 'packaging', 'pack type'],
        'qty_in_packaging'     => ['qty in packaging', 'packaging qty', 'pack qty', 'qty per pack'],
        'packaging_unit_label' => ['packaging unit', 'unit', 'pack unit'],
        'last_purchase_price'  => ['purchase price', 'price', 'cost', 'purchase price (rs)', 'purchase price (₹)'],
        'mrp'                  => ['mrp', 'mrp (rs)', 'mrp (₹)'],
        'minimum_qty'          => ['minimum stock qty', 'minimum qty', 'min stock', 'minimum stock'],
        'reorder_level'        => ['reorder level'],
        'usage_type'           => ['usage type', 'usage'],
        'vendor_name'          => ['vendor', 'vendor name', 'supplier', 'primary supplier'],
        'treatment_tags'       => ['treatment tags', 'tags'],
        'description'          => ['description'],
        'product_notes'        => ['notes', 'product notes'],
        'is_active'            => ['active', 'is active', 'status'],
    ];

    private const VENDOR_COLUMN_MAP = [
        'vendor_name'    => ['vendor name', 'vendor', 'name', 'supplier name'],
        'contact_person' => ['contact person', 'contact'],
        'phone'          => ['phone', 'mobile', 'contact no'],
        'whatsapp'       => ['whatsapp'],
        'email'          => ['email'],
        'gst_no'         => ['gst no', 'gstin', 'gst'],
        'address'        => ['address'],
        'city'           => ['city'],
        'credit_days'    => ['credit days'],
        'is_active'      => ['active', 'is active', 'status'],
    ];

    private const TEMPLATE_HEADERS = [
        'Product Name', 'Category', 'Sub Type', 'Variant', 'Brand', 'Alternative Brands',
        'Company Name', 'Packaging Type', 'Qty in Packaging', 'Packaging Unit',
        'Purchase Price', 'MRP', 'Minimum Stock Qty', 'Reorder Level',
        'Usage Type', 'Vendor Name', 'Treatment Tags', 'Description', 'Notes', 'Active',
    ];

    private const VENDOR_TEMPLATE_HEADERS = [
        'Vendor Name', 'Contact Person', 'Phone', 'WhatsApp', 'Email',
        'GST No', 'Address', 'City', 'Credit Days', 'Active',
    ];

    // ── Import form ───────────────────────────────────────────────────────────

    public function importForm()
    {
        return view('inventory.products-import');
    }

    // ── Download blank template ────────────────────────────────────────────

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setTitle('Dentfluence Inventory Import Template');

        // ── Sheet 1: Products ──
        $sh = $spreadsheet->getActiveSheet()->setTitle('Products');
        foreach (array_values(self::TEMPLATE_HEADERS) as $i => $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue("{$col}1", $label);
            $sh->getStyle("{$col}1")->getFont()->setBold(true);
            $sh->getColumnDimension($col)->setAutoSize(true);
        }
        // One filled example row so the format is unambiguous.
        $sh->fromArray([
            'Filtek Z250 XT', 'Restorative Materials', 'Composite', 'A2 Shade', '3M', 'Ivoclar, GC',
            '3M ESPE', 'Syringe', '4', 'g', '850', '', '2', '1',
            'multiple_use', 'Prime Dental Supplies', 'Composite Filling, Aesthetic Dentistry',
            'Universal composite', 'Keep refrigerated', 'Yes',
        ], null, 'A2');

        // ── Sheet 2: Vendors (optional — leave blank rows if you have none to add) ──
        $spreadsheet->createSheet()->setTitle('Vendors');
        $sh2 = $spreadsheet->getSheetByName('Vendors');
        foreach (array_values(self::VENDOR_TEMPLATE_HEADERS) as $i => $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sh2->setCellValue("{$col}1", $label);
            $sh2->getStyle("{$col}1")->getFont()->setBold(true);
            $sh2->getColumnDimension($col)->setAutoSize(true);
        }
        $sh2->fromArray([
            'Prime Dental Supplies', 'Rajesh Kumar', '9876543210', '9876543210',
            'sales@primedental.example', '27AAAAA0000A1Z5', '12 MG Road', 'Pune', '30', 'Yes',
        ], null, 'A2');

        $spreadsheet->setActiveSheetIndex(0);

        $writer   = new Xlsx($spreadsheet);
        $filename = 'inventory-product-import-template.xlsx';
        $temp     = tempnam(sys_get_temp_dir(), 'inv_import_tpl_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Preview (step 1) — parse file, validate every row, show a report ────

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $tempPath = $request->file('file')->store('imports/temp', 'local');
            $parsed   = $this->parseFile($request->file('file'));
        } catch (\RuntimeException $e) {
            return redirect()->route('inventory.products.import')
                ->withErrors(['file' => $e->getMessage()]);
        }

        $rows    = $parsed['products'];
        $vendors = $parsed['vendors'];

        if (empty($rows) && empty($vendors)) {
            return redirect()->route('inventory.products.import')
                ->withErrors(['file' => 'No data rows found in the file. Check that the first row has column headers.']);
        }

        [$valid, $errors]               = $this->validateProductRows($rows);
        [$validVendors, $vendorErrors]  = $this->validateVendorRows($vendors);

        $request->session()->put('inventory_import_preview', [
            'temp_path' => $tempPath,
        ]);

        $preview       = array_slice($rows, 0, 10);
        $totalRows     = count($rows);
        $vendorPreview = array_slice($vendors, 0, 10);
        $totalVendors  = count($vendors);

        return view('inventory.products-import-preview', compact(
            'preview', 'totalRows', 'valid', 'errors',
            'vendorPreview', 'totalVendors', 'validVendors', 'vendorErrors'
        ));
    }

    // ── Import (step 2) — actually save to DB ────────────────────────────────

    public function store(Request $request)
    {
        $sessionData = $request->session()->get('inventory_import_preview');

        if (! $sessionData || empty($sessionData['temp_path'])) {
            return redirect()->route('inventory.products.import')
                ->withErrors(['file' => 'Session expired. Please upload the file again.']);
        }

        $fullPath = Storage::disk('local')->path($sessionData['temp_path']);
        $parsed   = $this->parseFileFromPath($fullPath, pathinfo($fullPath, PATHINFO_EXTENSION));

        [$valid, $errors]              = $this->validateProductRows($parsed['products']);
        [$validVendors, $vendorErrors] = $this->validateVendorRows($parsed['vendors']);

        $userId         = Auth::id();
        $importedVendor = 0;
        $imported       = 0;

        DB::transaction(function () use ($validVendors, $valid, $userId, &$importedVendor, &$imported) {
            // ── Vendors first, so product rows can link to a vendor created
            // in the very same workbook. ──
            foreach ($validVendors as $row) {
                $this->resolveVendor($row['vendor_name'], $row);
                $importedVendor++;
            }

            // ── Products ──
            foreach ($valid as $row) {
                $categoryId = $this->resolveCategory($row['category'] ?? '');
                $subTypeId  = $categoryId ? $this->resolveSubType($row['sub_type'] ?? '', $categoryId) : null;
                $variantId  = $subTypeId ? $this->resolveVariant($row['variant'] ?? '', $subTypeId) : null;
                $vendorId   = ! empty($row['vendor_name']) ? $this->resolveVendor($row['vendor_name']) : null;

                $usageType = strtolower(trim($row['usage_type'] ?? '')) === 'single_use'
                    ? 'single_use' : 'multiple_use';

                $qtyInPackaging = (float) $row['qty_in_packaging'];

                $altBrands = $this->splitList($row['alternative_brands'] ?? '');
                $tags      = $this->splitList($row['treatment_tags'] ?? '');

                $item = InventoryItem::create([
                    'item_code'              => 'ITEM-' . str_pad(InventoryItem::count() + 1, 4, '0', STR_PAD_LEFT),
                    'product_name'           => $row['product_name'],
                    'brand'                  => $row['brand'] ?: null,
                    'alternative_brands'     => $altBrands,
                    'company_name'           => $row['company_name'] ?: null,
                    'category_id'            => $categoryId,
                    'sub_type_id'            => $subTypeId,
                    'variant_id'             => $variantId,
                    'description'            => $row['description'] ?: null,
                    'product_notes'          => $row['product_notes'] ?: null,
                    'treatment_tags'         => $tags,
                    'usage_type'             => $usageType,
                    'inventory_behavior'     => $usageType === 'single_use' ? 'consumable' : 'reusable',
                    'is_reusable'            => $usageType !== 'single_use',
                    'is_sellable'            => false,
                    'packaging_type'         => $row['packaging_type'],
                    'qty_in_packaging'       => $qtyInPackaging,
                    'packaging_unit_label'   => $row['packaging_unit_label'] ?: 'units',
                    'purchase_unit'          => $row['packaging_type'],
                    'consumption_unit'       => $row['packaging_unit_label'] ?: 'units',
                    'pieces_per_unit'        => max(1, (int) round($qtyInPackaging)),
                    'minimum_order_qty'      => 1,
                    'last_purchase_price'    => is_numeric($row['last_purchase_price'] ?? null) ? (float) $row['last_purchase_price'] : 0,
                    'average_purchase_price' => is_numeric($row['last_purchase_price'] ?? null) ? (float) $row['last_purchase_price'] : 0,
                    'mrp'                    => is_numeric($row['mrp'] ?? null) ? (float) $row['mrp'] : null,
                    'minimum_qty'            => (float) $row['minimum_qty'],
                    'reorder_level'          => is_numeric($row['reorder_level'] ?? null) ? (float) $row['reorder_level'] : 0,
                    'is_active'              => ! in_array(strtolower(trim($row['is_active'] ?? 'yes')), ['no', 'false', '0', 'inactive'], true),
                    'created_by'             => $userId,
                ]);

                if ($vendorId) {
                    $item->dealers()->syncWithoutDetaching([$vendorId => ['is_primary' => true]]);
                }

                $imported++;
            }
        });

        Storage::disk('local')->delete($sessionData['temp_path']);
        $request->session()->forget('inventory_import_preview');

        $skipped       = count($errors);
        $skippedVendor = count($vendorErrors);
        $msg = "Import complete — {$imported} product(s) and {$importedVendor} vendor(s) added"
            . ($skipped || $skippedVendor ? ", " . ($skipped + $skippedVendor) . " row(s) skipped (see errors below)." : '.');

        if ($skipped || $skippedVendor) {
            return redirect()->route('inventory.products.import')
                ->with('import_success', $msg)
                ->with('import_errors', array_merge($errors, $vendorErrors));
        }

        return redirect()->route('inventory.products')->with('success', $msg);
    }

    // ── Row validation (shared by preview + store so counts always match) ───

    /**
     * @return array{0: array<int, array>, 1: array<int, string>} [validRows, errorsByRowNumber]
     */
    private function validateProductRows(array $rows): array
    {
        $valid  = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +1 for header row, +1 for 1-indexing
            $problems = [];

            if (empty($row['product_name'])) {
                $problems[] = 'missing Product Name';
            }
            if (empty($row['packaging_type'])) {
                $problems[] = 'missing Packaging Type';
            }
            if (! is_numeric($row['qty_in_packaging'] ?? null) || (float) $row['qty_in_packaging'] <= 0) {
                $problems[] = 'missing or invalid Qty in Packaging';
            }
            if (! is_numeric($row['minimum_qty'] ?? null) || (float) $row['minimum_qty'] < 0) {
                $problems[] = 'missing or invalid Minimum Stock Qty';
            }

            if ($problems) {
                $errors[$rowNum] = 'Products, row ' . $rowNum . ': ' . implode(', ', $problems) . '.';
                continue;
            }

            $valid[] = $row;
        }

        return [$valid, $errors];
    }

    /** @return array{0: array<int, array>, 1: array<int, string>} [validRows, errorsByRowNumber] */
    private function validateVendorRows(array $rows): array
    {
        $valid  = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            if (empty($row['vendor_name'])) {
                $errors[$rowNum] = 'Vendors, row ' . $rowNum . ': missing Vendor Name.';
                continue;
            }

            $valid[] = $row;
        }

        return [$valid, $errors];
    }

    /** Comma-separated cell value -> trimmed array, e.g. "Ivoclar, GC" -> ['Ivoclar', 'GC']. */
    private function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /** Find an existing category by case-insensitive name, or create one. Blank input = uncategorised. */
    private function resolveCategory(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = InventoryCategory::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        if ($existing) {
            return $existing->id;
        }

        $category = InventoryCategory::create([
            'name'      => $name,
            'slug'      => Str::slug($name) ?: Str::slug('category-' . uniqid()),
            'is_active' => true,
            'sort_order'=> (InventoryCategory::max('sort_order') ?? 0) + 1,
        ]);

        return $category->id;
    }

    /** Find-or-create a sub-type by name, scoped to its parent category. Blank input = none. */
    private function resolveSubType(string $name, int $categoryId): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = InventorySubType::where('category_id', $categoryId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($existing) {
            return $existing->id;
        }

        return InventorySubType::create([
            'category_id' => $categoryId,
            'name'        => $name,
        ])->id;
    }

    /** Find-or-create a variant by name, scoped to its parent sub-type. Blank input = none. */
    private function resolveVariant(string $name, int $subTypeId): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = InventoryVariant::where('sub_type_id', $subTypeId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();
        if ($existing) {
            return $existing->id;
        }

        return InventoryVariant::create([
            'sub_type_id' => $subTypeId,
            'name'        => $name,
        ])->id;
    }

    /**
     * Find-or-create a vendor by case-insensitive name. When creating (no
     * existing match), [$extra] supplies the other Vendors-sheet columns —
     * contact_person/phone/whatsapp/email/gst_no/address/city/credit_days.
     * Newly created vendors are auto-synced to Finance, same as the
     * Settings > Vendors "Add Vendor" flow (InventoryController::storeVendor()).
     */
    private function resolveVendor(string $name, array $extra = []): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = InventoryVendor::whereRaw('LOWER(vendor_name) = ?', [strtolower($name)])->first();
        if ($existing) {
            return $existing->id;
        }

        $vendor = InventoryVendor::create([
            'vendor_name'    => $name,
            'contact_person' => $extra['contact_person'] ?? null ?: null,
            'phone'          => $extra['phone'] ?? null ?: null,
            'whatsapp'       => $extra['whatsapp'] ?? null ?: null,
            'email'          => $extra['email'] ?? null ?: null,
            'gst_no'         => $extra['gst_no'] ?? null ?: null,
            'address'        => $extra['address'] ?? null ?: null,
            'city'           => $extra['city'] ?? null ?: null,
            'credit_days'    => is_numeric($extra['credit_days'] ?? null) ? (int) $extra['credit_days'] : null,
            'is_active'      => ! in_array(strtolower(trim($extra['is_active'] ?? 'yes')), ['no', 'false', '0', 'inactive'], true),
        ]);

        $vendor->syncToFinance();

        return $vendor->id;
    }

    // ── File parsing — CSV is products-only (single table); XLSX/XLS reads
    // a "Products" sheet (or the first sheet, if not named that) plus an
    // optional "Vendors" sheet from the same workbook. ─────────────────────

    /** @return array{products: array<int, array>, vendors: array<int, array>} */
    private function parseFile($uploadedFile): array
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        return $this->parseFileFromPath($uploadedFile->getRealPath(), $extension);
    }

    /** @return array{products: array<int, array>, vendors: array<int, array>} */
    private function parseFileFromPath(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['xlsx', 'xls']) && ! class_exists('ZipArchive')) {
            throw new \RuntimeException(
                'PHP zip extension is not enabled. Enable ext-zip in php.ini and restart your web server, or upload a CSV file instead.'
            );
        }

        if ($extension === 'csv') {
            $reader = IOFactory::createReader('Csv');
            $reader->setDelimiter(',');
            $spreadsheet = $reader->load($path);

            // CSV has no concept of a second sheet — products only.
            return [
                'products' => $this->extractRows($spreadsheet->getActiveSheet(), self::COLUMN_MAP),
                'vendors'  => [],
            ];
        }

        $spreadsheet = IOFactory::load($path);

        $productsSheet = $spreadsheet->getSheetByName('Products') ?? $spreadsheet->getActiveSheet();
        $vendorsSheet  = $spreadsheet->getSheetByName('Vendors');

        return [
            'products' => $this->extractRows($productsSheet, self::COLUMN_MAP),
            'vendors'  => $vendorsSheet ? $this->extractRows($vendorsSheet, self::VENDOR_COLUMN_MAP) : [],
        ];
    }

    /** Read one worksheet into field=>value row maps, using [$columnMap] for header matching. */
    private function extractRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columnMap): array
    {
        $data = $sheet->toArray(null, true, true, false);
        if (empty($data)) {
            return [];
        }

        $rawHeaders  = array_map(fn ($h) => strtolower(trim((string) $h)), $data[0]);
        $resolvedMap = $this->buildColumnMap($rawHeaders, $columnMap);

        $rows = [];
        foreach (array_slice($data, 1) as $row) {
            $mapped = [];
            foreach ($resolvedMap as $field => $colIndex) {
                $mapped[$field] = trim((string) ($row[$colIndex] ?? ''));
            }
            if (empty(array_filter($mapped))) {
                continue; // fully blank row
            }
            $rows[] = $mapped;
        }

        return $rows;
    }

    private function buildColumnMap(array $rawHeaders, array $columnMap): array
    {
        $colMap = [];
        foreach ($columnMap as $field => $aliases) {
            foreach ($rawHeaders as $idx => $header) {
                if (in_array($header, $aliases, true)) {
                    $colMap[$field] = $idx;
                    break;
                }
            }
        }

        return $colMap;
    }
}
