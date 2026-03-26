<?php

namespace App\Notifications;

use App\Models\Problem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProblemResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Problem $problem,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'PROBLEM_RESOLVED',
            'problem_id' => $this->problem->id,
            'problem_name' => $this->problem->name,
            'member_id' => $this->problem->member_id,
            'resolved_by' => $this->problem->resolved_by,
            'resolved_at' => $this->problem->resolved_at?->toISOString(),
        ];
    }
}
