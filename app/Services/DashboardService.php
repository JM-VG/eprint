<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\SaleSource;
use App\Enums\UserStatus;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        private StationService $stations,
        private QueueService $queue,
        private AvailabilityService $availability,
        private CommissionReportService $commission,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function student(User $student): array
    {
        $jobs = PrintJob::where('user_id', $student->id);
        $claimed = (clone $jobs)->where('status', JobStatus::Claimed);

        return [
            'active' => (clone $jobs)->whereIn('status', QueueService::ACTIVE)->count(),
            'ready' => (clone $jobs)->where('status', JobStatus::Ready)->count(),
            'completed' => (clone $claimed)->count(),
            'ready_jobs' => (clone $jobs)->with('location')->where('status', JobStatus::Ready)->oldest()->get(),
            'recent' => (clone $jobs)->with('location')->latest()->latest('id')->limit(5)->get(),
            'spent_total' => $this->money((clone $claimed)->sum('final_total')),
            'spent_month' => $this->money((clone $claimed)->where('created_at', '>=', now()->startOfMonth())->sum('final_total')),
            'pages_month' => (int) (clone $jobs)->where('created_at', '>=', now()->startOfMonth())->sum('sheets_used'),
            'statuses' => $this->statusCounts((clone $jobs)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staff(User $staff): array
    {
        $session = $this->stations->current($staff)?->load('location');
        $counts = $this->queue->pendingCounts();
        $commission = $this->commission->summary($staff);
        $today = Sale::where('user_id', $staff->id)->where('created_at', '>=', now()->startOfDay());

        return [
            'station' => $session?->location->name,
            'pending_here' => $session ? ($counts[$session->location_id] ?? 0) : 0,
            'commission_today' => $commission['today'],
            'unpaid' => $commission['unpaid'],
            'commission' => $commission,
            'sales_today' => [
                'count' => (clone $today)->count(),
                'amount' => $this->money((clone $today)->sum('amount')),
            ],
            'trend' => $this->daily(Sale::where('user_id', $staff->id), 'commission_amount', 14),
            'recent_sales' => Sale::with('location')->where('user_id', $staff->id)->latest()->latest('id')->limit(5)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function admin(): array
    {
        $today = Sale::where('created_at', '>=', now()->startOfDay())->get(['amount', 'commission_amount']);
        $jobs = PrintJob::whereIn('status', QueueService::ACTIVE)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $since = now()->subDays(29)->startOfDay();
        $period = Sale::where('created_at', '>=', $since)->get(['amount', 'commission_amount', 'source', 'payment_method']);
        $previous = (float) Sale::whereBetween('created_at', [$since->copy()->subDays(30), $since])->sum('amount');
        $periodAmount = (float) $period->sum('amount');
        $month = Sale::where('created_at', '>=', now()->startOfMonth());

        return [
            'sales_today' => [
                'count' => $today->count(),
                'amount' => $today->reduce(fn (string $sum, Sale $s) => bcadd($sum, (string) $s->amount, 2), '0.00'),
                'commission' => $today->reduce(fn (string $sum, Sale $s) => bcadd($sum, (string) $s->commission_amount, 2), '0.00'),
            ],
            'pending_students' => User::where('role', Role::Student)->where('status', UserStatus::Pending)->count(),
            'jobs' => collect(QueueService::ACTIVE)->mapWithKeys(fn (JobStatus $s) => [$s->value => (int) ($jobs[$s->value] ?? 0)])->all(),
            'locations' => $this->availability->locations(),
            'unpaid_commission' => Sale::whereNull('payout_id')->get(['commission_amount'])
                ->reduce(fn (string $sum, Sale $s) => bcadd($sum, (string) $s->commission_amount, 2), '0.00'),
            'period' => [
                'amount' => $this->money($periodAmount),
                'count' => $period->count(),
                'commission' => $this->money($period->sum('commission_amount')),
                'change' => $previous > 0 ? round(($periodAmount - $previous) / $previous * 100, 1) : null,
            ],
            'month' => [
                'amount' => $this->money((clone $month)->sum('amount')),
                'commission' => $this->money((clone $month)->sum('commission_amount')),
            ],
            'trend' => $this->daily(Sale::query(), 'amount', 30),
            'by_source' => collect(SaleSource::cases())->mapWithKeys(fn (SaleSource $s) => [
                $s->value => (float) $period->where('source', $s)->sum('amount'),
            ])->all(),
            'by_payment' => collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [
                $m->value => (float) $period->where('payment_method', $m)->sum('amount'),
            ])->all(),
            'top_staff' => $this->topStaff(),
            'recent_sales' => Sale::with(['user', 'location'])->latest()->latest('id')->limit(6)->get(),
            'jobs_today' => PrintJob::where('created_at', '>=', now()->startOfDay())->count(),
        ];
    }

    /**
     * Daily totals of $column for the last $days days, oldest first, zero-filled.
     *
     * @return list<array{date: string, label: string, value: float}>
     */
    private function daily(Builder $query, string $column, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $totals = $query->where('created_at', '>=', $start)
            ->get(['created_at', $column])
            ->groupBy(fn ($row) => $row->created_at->format('Y-m-d'))
            ->map(fn ($rows) => (float) $rows->sum($column));

        return collect(range(0, $days - 1))->map(function (int $i) use ($start, $totals) {
            $day = $start->copy()->addDays($i);

            return ['date' => $day->format('Y-m-d'), 'label' => $day->format('M j'), 'value' => $totals[$day->format('Y-m-d')] ?? 0.0];
        })->all();
    }

    /**
     * @return list<array{name: string, count: int, amount: string, commission: string}>
     */
    private function topStaff(): array
    {
        return Sale::with('user')
            ->where('created_at', '>=', now()->startOfMonth())
            ->get(['user_id', 'amount', 'commission_amount'])
            ->groupBy('user_id')
            ->map(fn ($sales) => [
                'name' => $sales->first()->user?->name ?? __('Unknown'),
                'count' => $sales->count(),
                'amount' => $this->money($sales->sum('amount')),
                'commission' => $this->money($sales->sum('commission_amount')),
                'sort' => (float) $sales->sum('commission_amount'),
            ])
            ->sortByDesc('sort')
            ->take(5)
            ->map(fn (array $row) => collect($row)->except('sort')->all())
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(Builder $jobs): array
    {
        $counts = $jobs->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all();
    }

    private function money(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
