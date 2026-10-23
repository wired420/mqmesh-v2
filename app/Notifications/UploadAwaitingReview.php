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

        $message = (new MailMessage)
            ->subject("Upload to review: {$file->zone->name} ({$file->typeLabel()} v{$file->version})")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$file->user?->name} uploaded a new {$file->typeLabel()} file for {$file->zone->name} ({$file->zone->short_name}).")
            ->line("Version {$file->version}, {$file->humanSize()}.");

        if ($file->notes) {
            $message->line('Their notes: '.$file->notes);
        }

        return $message
            ->action('Review uploads', route('moderation'))
            ->line('You get this email because you are a moderator on MQMesh.');
    }
}
