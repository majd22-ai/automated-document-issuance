<?php

namespace App\Jobs;

use App\Services\Notifications\NotificationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCitizenNotificationMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $notificationId) {}

    public function handle(NotificationMail $delivery): void
    {
        $delivery->send($this->notificationId);
    }
}
