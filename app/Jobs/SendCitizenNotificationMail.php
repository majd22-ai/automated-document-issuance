<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class SendCitizenNotificationMail implements ShouldQueue
{
    use Queueable;
    public int $tries=1;
    public int $timeout=60;
    public function __construct(public string $notificationId) {}
    public function handle(\App\Services\Notifications\NotificationMail $delivery): void { $delivery->send($this->notificationId); }
}
