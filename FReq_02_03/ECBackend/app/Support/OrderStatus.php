<?php

namespace App\Support;

final class OrderStatus
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const PROCESSING = 'processing';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::PROCESSING,
        self::DELIVERED,
        self::CANCELLED,
    ];

    public const TRANSITIONS = [
        self::PENDING => [self::CONFIRMED, self::PROCESSING, self::CANCELLED],
        self::CONFIRMED => [self::PROCESSING, self::DELIVERED, self::CANCELLED],
        self::PROCESSING => [self::DELIVERED, self::CANCELLED],
        self::DELIVERED => [],
        self::CANCELLED => [],
    ];

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isCancellable(string $status): bool
    {
        return self::canMove($status, self::CANCELLED);
    }
}
