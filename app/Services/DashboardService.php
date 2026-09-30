<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\PrintJob;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    public function __construct(
        private StationService $stations,
        private QueueService $queue,
        private AvailabilityService $availability,
        private CommissionReportService $commission,
    ) {}

    /**
     * @return array{active: int, ready: int, completed: int, ready_jobs: Collection<int, PrintJob>, recent: Collection<int, PrintJob>}
     */
    public function student(User $student): array
    {
        $jobs = PrintJob::where('user_id', $student->id);

        return [
            'active' => (clone $jobs)->whereIn('status', QueueService::ACTIVE)->count(),
            'ready' => (clone $jobs)->where('status', JobStatus::Ready)->count(),
            'completed' => (clone $jobs)->where('status', JobStatus::Claimed)->count(),
            'ready_jobs' => (clone $jobs)->with('location')->where('status', JobStatus::Ready)->oldest()->get(),
            'recent' => (clone $jobs)->with('location')->latest()->latest('id')->limit(5)->get(),
        ];
    }

    /**
     * @return array{station: ?string, pending_here: int, commission_today: string, unpaid: string}
     */
    public function staff(User $staff): array
    {
        $session = $this->stations->current($staff)?->load('location');
        $counts = $this->queue->pendingCounts();
        $commission = $this->commission->summary($staff);

        return [
            'station' => $session?->location->name,
            'pending_here' => $session ? ($counts[$session->location_id] ?? 0) : 0,
            'commission_today' => $commission['today'],
            'unpaid' => $commission['unpaid'],
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
        ];
    }
}
