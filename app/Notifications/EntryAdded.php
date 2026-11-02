<?php

namespace App\Notifications;

use App\Support\PushPayload;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class EntryAdded extends Notification
{
    public function __construct(public readonly PushPayload $payload) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class]; // a future Firebase channel for the native apps is added here
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->payload->title)
            ->body($this->payload->body)
            ->icon('/icons/icon-192.png')
            ->tag($this->payload->tag)
            ->data(['url' => $this->payload->url])
            ->options(['TTL' => 86400]); // keep it for a day if the phone is off
    }
}
