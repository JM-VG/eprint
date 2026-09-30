<?php

namespace App\Notifications;

use App\Models\PrintJob;
use Illuminate\Notifications\Notification;

class JobReady extends Notification
{
    public function __construct(public PrintJob $job) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $location = $this->job->location->name;

        return [
            'print_job_id' => $this->job->id,
            'claim_code' => $this->job->claim_code,
            'message' => "Your job {$this->job->claim_code} is ready for pickup at {$location}.",
            'url' => route('student.jobs.show', $this->job),
        ];
    }
}
