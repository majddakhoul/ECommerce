<?php

namespace App\Services;

use App\Jobs\SendOrderMail;
use App\Mail\OrderPlacedAdminMail;
use App\Mail\OrderPlacedUserMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\Processing\ProcessingMode;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class NotificationService
{
    public const PLACED_USER = 'placed_user';

    public const PLACED_ADMIN = 'placed_admin';

    public const STATUS_UPDATED = 'status_updated';

    public function __construct(private readonly ProcessingMode $processing) {}

    public function sendOrderPlaced(Order $order): void
    {
        $order->loadMissing(['items', 'user']);

        $this->route($order, self::PLACED_USER, $order->user->email);

        $admins = User::role(AccessControl::ADMIN_ROLE, AccessControl::GUARD)->get();

        foreach ($admins as $admin) {
            $this->route($order, self::PLACED_ADMIN, $admin->email);
        }
    }

    public function sendOrderStatusUpdated(Order $order, string $previousStatus): void
    {
        $order->loadMissing(['items', 'user']);

        $this->route($order, self::STATUS_UPDATED, $order->user->email, $previousStatus);
    }

    public function deliverQueued(int $orderId, string $kind, string $recipient, ?string $previousStatus): void
    {
        $order = Order::with(['items', 'user'])->find($orderId);

        if ($order === null) {
            return;
        }

        Mail::to($recipient)->send($this->mailable($order, $kind, $previousStatus));
    }

    private function route(Order $order, string $kind, string $recipient, ?string $previousStatus = null): void
    {
        if ($this->processing->isAsync()) {
            SendOrderMail::dispatch($order->id, $kind, $recipient, $previousStatus);

            return;
        }

        $this->deliverInline($order, $kind, $recipient, $previousStatus);
    }

    private function deliverInline(Order $order, string $kind, string $recipient, ?string $previousStatus): void
    {
        try {
            Mail::to($recipient)->send($this->mailable($order, $kind, $previousStatus));
        } catch (\Throwable $e) {
            Log::error('Order email failed', [
                'order_id' => $order->id,
                'recipient' => $recipient,
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mailable(Order $order, string $kind, ?string $previousStatus): Mailable
    {
        return match ($kind) {
            self::PLACED_USER => new OrderPlacedUserMail($order),
            self::PLACED_ADMIN => new OrderPlacedAdminMail($order),
            self::STATUS_UPDATED => new OrderStatusUpdatedMail($order, (string) $previousStatus),
            default => throw new InvalidArgumentException("Unknown mail kind '{$kind}'."),
        };
    }
}
