<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyBusinessSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $summary,
        public Business $business
    ) {
    }

    public function envelope(): Envelope
    {
        $businessName = $this->business->name;
        $dateFormatted = $this->summary['date_formatted'] ?? now()->format('d/m/Y');

        return new Envelope(
            subject: "Resumen Operativo Diario — {$businessName} ({$dateFormatted})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-summary',
        );
    }
}
