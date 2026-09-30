<?php

namespace App\Notifications;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\PrintJob;
use App\Models\Sale;
use Illuminate\Notifications\Notification;

/**
 * Sent to the job owner (who picked it up) and to the staff member who confirmed payment.
 */
class JobClaimed extends Notification
{
    public function __construct(public PrintJob $job, public Sale $sale, public bool $forStaff) {}

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
        $code = $this->job->claim_code;
        $amount = '₱'.number_format((float) $this->sale->amount, 2);
        $method = $this->sale->payment_method === PaymentMethod::Gcash ? 'GCash' : 'cash';

        if ($this->forStaff) {
            $message = "Claim confirmed: {$code} for {$this->job->user->name}, {$amount} paid by {$method}.";
            if ($this->sale->user_id === $notifiable->id) {
                $message .= ' Commission +₱'.number_format((float) $this->sale->commission_amount, 2).'.';
            }
            $url = route('staff.commission');
        } else {
            $message = "You picked up {$code} at {$this->job->location->name}. Paid {$amount} by {$method}. Thank you!";
            $url = $notifiable->role === Role::Student ? route('student.jobs.show', $this->job) : route('dashboard');
        }

        return [
            'kind' => 'job_claimed',
            'print_job_id' => $this->job->id,
            'claim_code' => $code,
            'message' => $message,
            'url' => $url,
        ];
    }
}
