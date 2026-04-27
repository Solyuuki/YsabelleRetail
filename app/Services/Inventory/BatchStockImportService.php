<?php

namespace App\Services\Inventory;

use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BatchStockImportService
{
    public const REQUIRED_COLUMNS = [
        'sku',
        'product_name',
        'variant',
        'quantity',
        'cost_price',
        'supplier',
        'notes',
    ];

    public function __construct(
        private readonly InventoryManager $inventoryManager,
    ) {
    }

    public function preview(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is empty.',
            ]);
        }

        $headers = array_map([$this, 'normalizeHeader'], array_keys($rows[0]));
        $missingColumns = collect(self::REQUIRED_COLUMNS)->diff($headers)->values()->all();

        if ($missingColumns !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required columns: '.implode(', ', $missingColumns).'.',
            ]);
        }

        $normalizedRows = collect($rows)
            ->values()
            ->map(function (array $row, int $index): array {
                $normalized = $this->normalizeRow($row);
                $errors = [];

                $variant = ProductVariant::query()->with('product')->where('sku', $normalized['sku'])->first();

                if (! $variant) {
                    $errors[] = 'SKU not found.';
                }

                if ($normalized['quantity'] <= 0) {
                    $errors[] = 'Quantity must be greater than zero.';
                }

                if ($normalized['cost_price'] !== null && ! is_numeric((string) $normalized['cost_price'])) {
                    $errors[] = 'Cost price must be numeric.';
                }

                return [
                    'row_number' => $index + 2,
                    'values' => $normalized,
                    'variant_id' => $variant?->id,
                    'product_name' => $variant?->product?->name,
                    'variant_name' => $variant?->name,
                    'errors' => $errors,
                ];
            });

        return [
            'filename' => $file->getClientOriginalName(),
            'token' => (string) Str::uuid(),
            'rows' => $normalizedRows->all(),
            'summary' => [
                'total_rows' => $normalizedRows->count(),
                'valid_rows' => $normalizedRows->where('errors', [])->count(),
                'invalid_rows' => $normalizedRows->where('errors', '!=', [])->count(),
            ],
        ];
    }

    public function commit(array $previewPayload, User $actor): InventoryImportBatch
    {
        $rows = collect($previewPayload['rows'] ?? []);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'There is no import preview to commit.',
            ]);
        }

        if ($rows->contains(fn (array $row) => $row['errors'] !== [])) {
            throw ValidationException::withMessages([
                'file' => 'Resolve all invalid rows before importing.',
            ]);
        }

        return DB::transaction(function () use ($previewPayload, $rows, $actor): InventoryImportBatch {
            $batch = InventoryImportBatch::query()->create([
                'reference_number' => 'IMP-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4)),
                'uploaded_by_user_id' => $actor->id,
                'original_filename' => $previewPayload['filename'] ?? 'stock-import.csv',
                'status' => 'completed',
                'total_rows' => $rows->count(),
                'imported_rows' => $rows->count(),
                'failed_rows' => 0,
                'metadata' => [
                    'preview_token' => $previewPayload['token'] ?? null,
                ],
            ]);

            $variants = ProductVariant::query()
                ->with('inventoryItem')
                ->whereIn('id', $rows->pluck('variant_id'))
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                $values = $row['values'];
                $variant = $variants->get($row['variant_id']);

                $this->inventoryManager->importStock(
                    variant: $variant,
                    quantity: $values['quantity'],
                    batch: $batch,
                    actor: $actor,
                    notes: $values['notes'],
                    unitCost: $values['cost_price'],
                    supplierName: $values['supplier'],
                    metadata: [
                        'sku' => $values['sku'],
                        'file_row' => $row['row_number'],
                    ],
                );
            }

            return $batch->fresh();
        });
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->readCsv($file);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if ($sheetRows === []) {
            return [];
        }

        $headers = array_map([$this, 'normalizeHeader'], array_values(array_shift($sheetRows)));

        return collect($sheetRows)
            ->filter(fn (array $row): bool => collect($row)->filter(fn ($value) => $value !== null && trim((string) $value) !== '')->isNotEmpty())
            ->map(function (array $row) use ($headers): array {
                $values = array_values($row);

                return collect($headers)
                    ->mapWithKeys(fn (string $header, int $index): array => [$header => trim((string) ($values[$index] ?? ''))])
                    ->all();
            })
            ->values()
            ->all();
    }

    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded CSV file.',
            ]);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $headers = array_map([$this, 'normalizeHeader'], $headers);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (collect($row)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $rows[] = collect($headers)
                ->mapWithKeys(fn (string $header, int $index): array => [$header => trim((string) ($row[$index] ?? ''))])
                ->all();
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeHeader(mixed $header): string
    {
        return Str::of((string) $header)
            ->trim()
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->toString();
    }

    private function normalizeRow(array $row): array
    {
        return [
            'sku' => trim((string) Arr::get($row, 'sku')),
            'product_name' => trim((string) Arr::get($row, 'product_name')),
            'variant' => trim((string) Arr::get($row, 'variant')),
            'quantity' => (int) Arr::get($row, 'quantity'),
            'cost_price' => Arr::get($row, 'cost_price') === '' ? null : (float) Arr::get($row, 'cost_price'),
            'supplier' => trim((string) Arr::get($row, 'supplier')),
            'notes' => trim((string) Arr::get($row, 'notes')),
        ];
    }
}
