<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WorkshopTomorrowReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Workshop>  $workshops
     */
    public function __construct(
        public User $user,
        public Collection $workshops,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->workshops->count();
        $subject = $count === 1
            ? 'Reminder: '.$this->workshops->first()->name.' is tomorrow'
            : 'Reminder: '.$count.' workshops tomorrow';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.workshop-tomorrow-reminder',
            with: [
                'tomorrowLabel' => $this->workshops->first()?->starts_at
                    ?->timezone((string) config('app.timezone'))
                    ->isoFormat('dddd, MMMM D, Y'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
