<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AccountApproved extends Notification
{
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
            'message' => 'Your account has been approved. You can now submit print jobs.',
        ];
    }
}
