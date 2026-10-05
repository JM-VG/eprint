<?php

namespace App\Repositories;

class JobLogRepository extends Repository
{
    public function table(): string
    {
        return 'job_logs';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forJob(int|string $printJobId): array
    {
        return $this->whereColumn('print_job_id', $printJobId);
    }
}
