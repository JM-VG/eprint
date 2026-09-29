<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class NewSignup extends Notification
{
    public function __construct(public User $student) {}

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
            'user_id' => $this->student->id,
            'student_id' => $this->student->student_id,
            'name' => $this->student->name,
            'message' => "New student signup: {$this->student->name} ({$this->student->student_id}) is waiting for approval.",
        ];
    }
}
