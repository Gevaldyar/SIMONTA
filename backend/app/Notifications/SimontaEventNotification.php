<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SimontaEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly array $payload
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
