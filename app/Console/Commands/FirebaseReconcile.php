<?php

namespace App\Console\Commands;

use App\Services\ReconciliationService;
use Illuminate\Console\Command;

class FirebaseReconcile extends Command
{
    protected $signature = 'firebase:reconcile {--fix : Repair derived values instead of only reporting them}';

    protected $description = 'Check (and optionally repair) Realtime Database consistency: counters, references, commissions, payouts and claims';

    public function handle(ReconciliationService $reconciliation): int
    {
        $issues = $reconciliation->run((bool) $this->option('fix'));

        if ($issues === []) {
            $this->info('No problems found.');

            return self::SUCCESS;
        }

        $this->table(['Check', 'Problem', 'Fixed'], array_map(
            fn (array $issue) => [$issue['check'], $issue['message'], $issue['fixed'] ? 'yes' : 'no'],
            $issues,
        ));

        $unfixed = count(array_filter($issues, fn (array $issue) => ! $issue['fixed']));

        if ($unfixed > 0) {
            $this->warn("{$unfixed} problem(s) need attention.");
        }

        return $unfixed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
