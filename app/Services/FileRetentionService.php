<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Models\PrintJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes uploaded files after retention_days while keeping job records (files_deleted_at is set).
 * Only finished jobs are cleaned, so a job still waiting in the queue never loses its file.
 */
class FileRetentionService
{
    /**
     * @var list<JobStatus>
     */
    public const FINISHED = [JobStatus::Claimed, JobStatus::Rejected, JobStatus::Cancelled];

    /**
     * @return int number of jobs whose files were removed
     */
    public function cleanup(): int
    {
        $days = max(1, (int) Setting::read('retention_days', '7'));
        $disk = Storage::disk(JobSubmissionService::DISK);
        $cleaned = 0;

        PrintJob::whereNull('files_deleted_at')
            ->whereIn('status', self::FINISHED)
            ->where('created_at', '<', now()->subDays($days))
            ->chunkById(200, function ($jobs) use ($disk, &$cleaned) {
                foreach ($jobs as $job) {
                    $disk->delete(array_filter([$job->stored_path, $job->pdf_path]));
                    $job->forceFill(['files_deleted_at' => now()])->save();
                    $cleaned++;
                }
            });

        return $cleaned;
    }
}
