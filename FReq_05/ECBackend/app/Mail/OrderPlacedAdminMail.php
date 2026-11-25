<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New order #{$this->order->id} received");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order_placed_admin',
            with: ['order' => $this->order, 'customer' => $this->order->user],
        );
    }
}
