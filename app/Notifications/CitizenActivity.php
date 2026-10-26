<?php

namespace App\Notifications;

use App\Notifications\Channels\InboxChannel;
use Illuminate\Notifications\Notification;

class CitizenActivity extends Notification
{
    public function __construct(public string $eventKey, public array $payload, public ?int $requestId = null, public ?int $documentId = null, public bool $email = false) {}

    public function via($notifiable): array
    {
        return [InboxChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload;
    }
}
