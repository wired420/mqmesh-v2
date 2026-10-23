<?php

namespace App\Notifications;

use App\Models\FileVersion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UploadAwaitingReview extends Notification
{
    public function __construct(public FileVersion $file)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $file = $this->file->loadMissing(['zone', 'user']);

        // The uploader's notes stay on the moderation page: text a stranger
        // typed never goes into an email this site sends.
        return (new MailMessage)
            ->subject("Upload to review: {$file->zone->name} ({$file->typeLabel()} v{$file->version})")
            ->greeting("Hi {$notifiable->name},")
            ->line("A new {$file->typeLabel()} file for {$file->zone->name} ({$file->zone->short_name}) is waiting for review.")
            ->line("Version {$file->version}, {$file->humanSize()}. The uploader's name and notes are on the moderation page.")
            ->action('Review uploads', route('moderation'))
            ->line('You get this email because you are a moderator on MQMesh.');
    }
}
