<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeTenantMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public User $adminUser,
        public string $plainPassword
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bienvenido a PuntoStock POS — Credenciales de acceso para {$this->business->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-tenant',
        );
    }
}

