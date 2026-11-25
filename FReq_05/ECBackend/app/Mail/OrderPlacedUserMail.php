<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order #{$this->order->id} has been placed");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order_placed_user',
            with: ['order' => $this->order, 'user' => $this->order->user],
        );
    }
}
