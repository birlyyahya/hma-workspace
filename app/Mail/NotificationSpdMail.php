<?php

namespace App\Mail;

use App\Services\SpdPdfComposer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
     * PDF disusun via SpdPdfComposer varian penerima (tanpa halaman salinan
     * administrasi) — email ini untuk karyawan, bukan arsip pembuat.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(function () {
                try {
                    return app(SpdPdfComposer::class)->render($this->spd, $this->user, withAdminCopy: false);
                } catch (\Throwable $e) {
                    Log::error('Gagal generate PDF SPD untuk email', [
                        'spd_id' => $this->spd['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e; // biarkan job gagal & masuk failed_jobs
                }
            }, 'SPD-'.str_pad((string) $this->spd['id'], 4, '0', STR_PAD_LEFT).'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Catat activity setelah email benar-benar diserahkan ke mailer (berjalan
     * di worker untuk mail yang di-queue, atau inline untuk kirim langsung).
     *
     * @param  \Illuminate\Contracts\Mail\Factory|\Illuminate\Contracts\Mail\Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        $sent = parent::send($mailer);

        activity('izin')
            ->event('sent')
            ->withProperties(['spd_id' => $this->spd['id'] ?? null, 'email' => $this->user->email ?? null])
            ->log('Email notifikasi SPD #'.($this->spd['id'] ?? '-').' terkirim ke '.($this->user->email ?? '-'));

        return $sent;
    }

    /**
     * Dipanggil worker saat job pengiriman gagal — catat ke activity log agar
     * kegagalan notifikasi terlihat tanpa harus membuka log server.
     */
    public function failed(\Throwable $e): void
    {
        activity('izin')
            ->event('failed')
            ->withProperties([
                'spd_id' => $this->spd['id'] ?? null,
                'email' => $this->user->email ?? null,
                'error' => $e->getMessage(),
            ])
            ->log('Email notifikasi SPD #'.($this->spd['id'] ?? '-').' gagal dikirim');
    }
}
