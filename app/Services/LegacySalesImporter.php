<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SaleSource;
use App\Models\GcashPayment;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkinItem;
use App\Realtime\Atomic;
use App\Rules\RecordExists;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * One-time import of walk-in sales from the old per-PC system.
 * CSV header: date,officer,location,service,quantity,sheets_used,amount,payment_method,reference_no,commission_rate
 */
class LegacySalesImporter
{
    public const HEADER = ['date', 'officer', 'location', 'service', 'quantity', 'sheets_used', 'amount', 'payment_method', 'reference_no', 'commission_rate'];

    public function __construct(private CommissionService $commissions) {}

    /**
     * Read and validate every row. Errors are keyed by file line number.
     *
     * @return array{rows: list<array<string, string>>, errors: array<int, list<string>>}
     *
     * @throws RuntimeException for a missing file or wrong header
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\u{FEFF}")), fgetcsv($handle) ?: []);

        if ($header !== self::HEADER) {
            fclose($handle);
            throw new RuntimeException('Unexpected header. Expected: '.implode(',', self::HEADER));
        }

        $rows = [];
        $errors = [];
        $references = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }

            $row = array_combine(self::HEADER, array_map(fn ($v) => trim((string) $v), array_pad($values, count(self::HEADER), '')));
            $messages = $this->validate($row);

            if ($row['reference_no'] !== '' && isset($references[$row['reference_no']])) {
                $messages[] = 'The reference number repeats line '.$references[$row['reference_no']].'.';
            }
            if ($row['reference_no'] !== '') {
                $references[$row['reference_no']] ??= $line;
            }

            if (! $messages && $row['payment_method'] === PaymentMethod::Gcash->value) {
                $existing = GcashPayment::with('sale')->where('reference_no', $row['reference_no'])->first();

                if ($existing && ! $this->sameSale($existing->sale, $row)) {
                    $messages[] = 'The reference number is already used by a different sale.';
                } elseif ($existing) {
                    $row['_already_imported'] = '1';
                }
            }

            if ($messages) {
                $errors[$line] = $messages;
            } else {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Save valid rows in one atomic write.
     *
     * Re-runs are safe: for each (officer, location, amount, minute, payment method) the rows are
     * matched against sales that already existed before this run, so a file imported twice adds
     * nothing, while genuinely identical sales inside one file are all kept.
     *
     * @param  list<array<string, string>>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function import(array $rows): array
    {
        return Atomic::run(function () use ($rows) {
            $imported = 0;
            $skipped = 0;
            $alreadyThere = [];

            foreach ($rows as $row) {
                $officer = User::where('username', $row['officer'])->firstOrFail();
                $location = Location::where('slug', $row['location'])->firstOrFail();
                $date = Carbon::createFromFormat('Y-m-d H:i', $row['date']);
                $amount = number_format((float) $row['amount'], 2, '.', '');
                $method = PaymentMethod::from($row['payment_method']);
                $key = implode('|', [$officer->id, $location->id, $amount, $date->format('Y-m-d H:i'), $method->value]);

                if ($method === PaymentMethod::Gcash) {
                    // A GCash sale is identified by its reference number.
                    if (isset($row['_already_imported'])) {
                        $skipped++;

                        continue;
                    }
                } else {
                    // Cash sales have no reference: count matching cash sales once, before this run
                    // inserts any for the same key, and skip only that many rows.
                    $alreadyThere[$key] ??= Sale::where('user_id', $officer->id)
                        ->where('location_id', $location->id)
                        ->where('source', SaleSource::Walkin)
                        ->where('payment_method', $method)
                        ->where('amount', $amount)
                        ->where('created_at', $date)
                        ->count();

                    if ($alreadyThere[$key] > 0) {
                        $alreadyThere[$key]--;
                        $skipped++;

                        continue;
                    }
                }

                $sale = $this->commissions->recordSale(
                    creditedTo: $officer->id,
                    locationId: $location->id,
                    source: SaleSource::Walkin,
                    method: $method,
                    amount: $amount,
                    rate: $row['commission_rate'] !== '' ? $row['commission_rate'] : null,
                );
                $sale->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

                $service = $row['service'] !== '' ? Service::where('name', $row['service'])->first() : null;
                WalkinItem::create([
                    'sale_id' => $sale->id,
                    'service_id' => $service?->id,
                    'quantity' => (int) $row['quantity'],
                    'sheets_used' => (int) $row['sheets_used'],
                    'unit_price' => $service?->price ?? $amount,
                    'amount' => $amount,
                ]);

                if ($method === PaymentMethod::Gcash) {
                    GcashPayment::create([
                        'reference_no' => $row['reference_no'],
                        'amount' => $amount,
                        'sale_id' => $sale->id,
                        'received_by' => $officer->id,
                    ]);
                }

                $imported++;
            }

            return ['imported' => $imported, 'skipped' => $skipped];
        });
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function validate(array $row): array
    {
        $validator = Validator::make($row, [
            'date' => ['required', 'date_format:Y-m-d H:i'],
            'officer' => ['required', RecordExists::of(User::class, 'username')->whereIn('role', [Role::Staff->value, Role::Admin->value])],
            'location' => ['required', RecordExists::of(Location::class, 'slug')],
            'service' => ['nullable', RecordExists::of(Service::class, 'name')],
            'quantity' => ['required', 'integer', 'min:1'],
            'sheets_used' => ['required', 'integer', 'min:0'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'required_if:payment_method,gcash', 'max:50'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return $validator->errors()->all();
    }

    /**
     * Whether an existing sale is the one this row describes (used for GCash re-runs).
     *
     * @param  array<string, string>  $row
     */
    private function sameSale(?Sale $sale, array $row): bool
    {
        return $sale !== null
            && $sale->source === SaleSource::Walkin
            && $sale->user?->username === $row['officer']
            && $sale->location?->slug === $row['location']
            && $sale->amount === number_format((float) $row['amount'], 2, '.', '')
            && $sale->created_at->format('Y-m-d H:i') === $row['date'];
    }
}
