<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationSpdMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public array $spd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notifikasi SPD #'.$this->spd['id'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.spd-mail',
            with: [
                'user' => $this->user,
                'spd' => $this->spd,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(function () {
                try {
                    return Pdf::loadView('pdf.spd-pdf', [
                        'spd' => $this->spd,
                        'user' => $this->user,
                    ])->output();
                } catch (\Throwable $e) {
                    \Log::error('Gagal generate PDF SPD', [
                        'spd_id' => $this->spd['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e; // biarkan job gagal & masuk failed_jobs
                }
            }, 'SPD-'.str_pad((string) $this->spd['id'], 4, '0', STR_PAD_LEFT).'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
