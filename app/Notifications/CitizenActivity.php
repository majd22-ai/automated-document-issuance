<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class CitizenActivity extends Notification
{
    public function __construct(public string $eventKey,public array $payload,public ?int $requestId=null,public ?int $documentId=null,public bool $email=false) {}
    public function via($notifiable): array { return [\App\Notifications\Channels\InboxChannel::class]; }
    public function toDatabase($notifiable): array { return $this->payload; }
}
