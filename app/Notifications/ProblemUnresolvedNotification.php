<?php

namespace App\Notifications;

use App\Models\Problem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProblemUnresolvedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Problem $problem,
        public string $note,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'PROBLEM_UNRESOLVED',
            'problem_id' => $this->problem->id,
            'problem_name' => $this->problem->name,
            'member_id' => $this->problem->member_id,
            'unresolved_by' => auth()->id(),
            'note' => $this->note,
        ];
    }
}
