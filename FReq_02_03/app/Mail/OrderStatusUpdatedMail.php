<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $previousStatus) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your order #{$this->order->id} is now {$this->order->status}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order_status_updated',
            with: [
                'order' => $this->order,
                'user' => $this->order->user,
                'previousStatus' => $this->previousStatus,
                'statusMessage' => $this->statusMessage(),
            ],
        );
    }

    private function statusMessage(): string
    {
        return match ($this->order->status) {
            'confirmed' => 'Great news, your order has been confirmed.',
            'processing' => 'Your order is being prepared.',
            'delivered' => 'Your order has been delivered. We hope you enjoy it.',
            'cancelled' => 'Your order has been cancelled and the items were returned to stock.',
            default => 'The status of your order has changed.',
        };
    }
}
