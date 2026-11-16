<?php

namespace App\Services;

use App\Mail\OrderPlacedAdminMail;
use App\Mail\OrderPlacedUserMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendOrderPlaced(Order $order): void
    {
        $order->loadMissing(['items', 'user']);

        $this->deliver($order->user->email, new OrderPlacedUserMail($order), $order->id);

        $admins = User::role(AccessControl::ADMIN_ROLE, AccessControl::GUARD)->get();

        foreach ($admins as $admin) {
            $this->deliver($admin->email, new OrderPlacedAdminMail($order), $order->id);
        }
    }

    public function sendOrderStatusUpdated(Order $order, string $previousStatus): void
    {
        $order->loadMissing(['items', 'user']);

        $this->deliver($order->user->email, new OrderStatusUpdatedMail($order, $previousStatus), $order->id);
    }

    private function deliver(string $to, $mailable, int $orderId): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Order email failed', [
                'order_id' => $orderId,
                'recipient' => $to,
                'mail' => $mailable::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
