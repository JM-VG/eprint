<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Models\JobLog;
use App\Models\PrintJob;
use App\Models\User;
use App\Notifications\JobReady;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place job statuses change. Every change writes a job_logs row.
 */
class JobStatusService
{
    /**
     * @var array<string, list<JobStatus>>
     */
    private const ALLOWED = [
        'pending' => [JobStatus::Accepted, JobStatus::Rejected, JobStatus::Cancelled],
        'accepted' => [JobStatus::Printing, JobStatus::Rejected, JobStatus::Cancelled],
        'printing' => [JobStatus::Ready],
        'ready' => [JobStatus::Claimed],
    ];

    public function __construct(private PriceCalculator $prices) {}

    public function canTransition(JobStatus $from, JobStatus $to): bool
    {
        return in_array($to, self::ALLOWED[$from->value] ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $attributes  extra columns saved with the change
     *
     * @throws InvalidJobTransition
     */
    public function transition(PrintJob $job, JobStatus $to, User $actor, ?string $note = null, array $attributes = []): PrintJob
    {
        $job = DB::transaction(function () use ($job, $to, $actor, $note, $attributes) {
            $job = PrintJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $from = $job->status;

            if (! $this->canTransition($from, $to)) {
                throw InvalidJobTransition::between($from, $to);
            }

            $job->forceFill($attributes + ['status' => $to])->save();

            JobLog::create([
                'print_job_id' => $job->id,
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);

            return $job;
        });

        if ($to === JobStatus::Ready) {
            $job->user->notify(new JobReady($job));
        }

        return $job;
    }

    /**
     * Accept requires the final page count and final total; sheets used follow the range, copies and duplex.
     */
    public function accept(PrintJob $job, User $actor, int $pageCount, string $finalTotal): PrintJob
    {
        if ($pageCount < 1 || $pageCount > PriceCalculator::MAX_PAGES) {
            throw new InvalidArgumentException('Enter the final page count.');
        }

        if (! is_numeric($finalTotal) || (float) $finalTotal < 0) {
            throw new InvalidArgumentException('Enter the final total.');
        }

        $pages = $job->sets !== null ? 1 : $this->prices->pagesToPrint($job->page_range, $pageCount);
        $sheets = $job->sets !== null
            ? $job->sets
            : $job->copies * (int) ceil($pages / ($job->duplex ? 2 : 1));

        return $this->transition($job, JobStatus::Accepted, $actor, null, [
            'page_count' => $pageCount,
            'final_total' => number_format((float) $finalTotal, 2, '.', ''),
            'sheets_used' => $sheets,
        ]);
    }

    /**
     * Reject requires a reason, shown to the student.
     */
    public function reject(PrintJob $job, User $actor, string $reason): PrintJob
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reject reason is required.');
        }

        return $this->transition($job, JobStatus::Rejected, $actor, $reason, ['reject_reason' => $reason]);
    }
}
