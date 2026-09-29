<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AccountRejected extends Notification
{
    public function __construct(public string $reason) {}

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
        return [
            'reason' => $this->reason,
            'message' => "Your sign up was rejected: {$this->reason}",
        ];
    }
}
