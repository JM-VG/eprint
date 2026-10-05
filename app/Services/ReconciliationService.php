<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Firebase\BatchingDatabaseClient;
use App\Models\CommissionPayout;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Realtime\Database\TableSchema;
use App\Repositories\RepositoryRegistry;

/**
 * Self-healing checks for the Realtime Database, which has no cross-record
 * constraints or transactions spanning requests.
 *
 * Derived values (commission amounts, id counters, references to deleted
 * records) are repaired when $fix is true. Money that has already changed
 * hands (payout totals, claimed jobs without a sale) is only reported.
 */
class ReconciliationService
{
    public function __construct(
        private CommissionService $commissions,
        private RepositoryRegistry $repositories,
        private TableSchema $schema,
        private BatchingDatabaseClient $database,
    ) {}

    /**
     * @return list<array{check: string, message: string, fixed: bool}>
     */
    public function run(bool $fix = false): array
    {
        return [
            ...$this->counters($fix),
            ...$this->danglingReferences($fix),
            ...$this->commissionAmounts($fix),
            ...$this->payoutTotals(),
            ...$this->claimedJobs(),
        ];
    }

    /**
     * Id counters must be at least the highest id, or new records would overwrite old ones.
     *
     * @return list<array{check: string, message: string, fixed: bool}>
     */
    private function counters(bool $fix): array
    {
        $issues = [];

        foreach (array_keys(RepositoryRegistry::TABLES) as $table) {
            $ids = array_filter(array_keys($this->repositories->for($table)->all()), 'is_int');
            $max = $ids === [] ? 0 : max($ids);
            $counter = (int) $this->database->get("counters/{$table}");

            if ($counter < $max) {
                if ($fix) {
                    $this->database->transaction("counters/{$table}", fn ($current) => max((int) $current, $max));
                }

                $issues[] = $this->issue('counters', "counters/{$table} is {$counter} but the highest id is {$max}.", $fix);
            }
        }

        return $issues;
    }

    /**
     * Apply the on-delete rule of references to records that no longer exist.
     *
     * @return list<array{check: string, message: string, fixed: bool}>
     */
    private function danglingReferences(bool $fix): array
    {
        $issues = [];

        foreach ($this->schema->tables() as $table) {
            if (! isset(RepositoryRegistry::TABLES[$table])) {
                continue;
            }

            foreach ($this->schema->foreignKeys($table) as $column => [$parent, $onDelete]) {
                $parents = $this->repositories->for($parent)->all();
                $repository = $this->repositories->for($table);

                foreach ($repository->all() as $id => $record) {
                    $reference = $record[$column] ?? null;

                    if ($reference === null || isset($parents[$reference])) {
                        continue;
                    }

                    $action = match ($onDelete) {
                        TableSchema::CASCADE => 'delete it',
                        TableSchema::SET_NULL => "clear {$column}",
                        default => null,
                    };

                    $canFix = $fix && $action !== null;

                    if ($canFix) {
                        $onDelete === TableSchema::CASCADE
                            ? $repository->remove($id)
                            : $repository->patch($id, [$column => null]);
                    }

                    $issues[] = $this->issue(
                        'references',
                        "{$table}/{$id}.{$column} points at missing {$parent}/{$reference}".($action ? " ({$action})." : '.'),
                        $canFix,
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * commission_amount is derived from amount and the commission rate snapshot.
     *
     * @return list<array{check: string, message: string, fixed: bool}>
     */
    private function commissionAmounts(bool $fix): array
    {
        $issues = [];

        foreach (Sale::all() as $sale) {
            $expected = $this->commissions->commission((string) $sale->amount, (string) $sale->commission_rate);

            if (bccomp((string) $sale->commission_amount, $expected, 2) !== 0) {
                if ($fix) {
                    $sale->forceFill(['commission_amount' => $expected])->save();
                }

                $issues[] = $this->issue('commission', "sales/{$sale->id} commission is {$sale->commission_amount}, expected {$expected}.", $fix);
            }
        }

        return $issues;
    }

    /**
     * A payout's amount should equal the commission of the sales it paid.
     *
     * @return list<array{check: string, message: string, fixed: bool}>
     */
    private function payoutTotals(): array
    {
        $issues = [];
        $paid = Sale::whereNotNull('payout_id')->get(['payout_id', 'commission_amount'])->groupBy('payout_id');

        foreach (CommissionPayout::all() as $payout) {
            $total = ($paid[$payout->id] ?? collect())
                ->reduce(fn (string $sum, Sale $sale) => bcadd($sum, (string) $sale->commission_amount, 2), '0.00');

            if (bccomp($total, (string) $payout->amount, 2) !== 0) {
                $issues[] = $this->issue('payouts', "commission_payouts/{$payout->id} is {$payout->amount} but its sales total {$total}.", false);
            }
        }

        return $issues;
    }

    /**
     * Every claimed job has exactly one sale, and every job sale belongs to a claimed job.
     *
     * @return list<array{check: string, message: string, fixed: bool}>
     */
    private function claimedJobs(): array
    {
        $issues = [];
        $sales = Sale::whereNotNull('print_job_id')->get(['id', 'print_job_id'])->groupBy('print_job_id');
        $jobs = PrintJob::all(['id', 'status'])->keyBy('id');

        foreach ($jobs as $job) {
            $count = count($sales[$job->id] ?? []);

            if ($job->status === JobStatus::Claimed && $count !== 1) {
                $issues[] = $this->issue('claims', "print_jobs/{$job->id} is claimed but has {$count} sales.", false);
            }
        }

        foreach ($sales as $jobId => $jobSales) {
            $job = $jobs[$jobId] ?? null;

            if ($job !== null && $job->status !== JobStatus::Claimed) {
                $issues[] = $this->issue('claims', "print_jobs/{$jobId} has a sale but is {$job->status->value}.", false);
            }
        }

        return $issues;
    }

    /**
     * @return array{check: string, message: string, fixed: bool}
     */
    private function issue(string $check, string $message, bool $fixed): array
    {
        return compact('check', 'message', 'fixed');
    }
}
