<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $payment)
    {
        $this->user = $user;
        $this->payment = $payment;
       
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Subscription Invoice - ' . $this->subscription->name)
            ->view('emails.invoice')
            ->with([
                'user' => $this->user,
                'payment' => $this->payment,
            ]);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
