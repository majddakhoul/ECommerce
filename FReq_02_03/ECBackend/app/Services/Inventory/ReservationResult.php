<?php

namespace App\Services\Inventory;

class ReservationResult
{
    public function __construct(
        public readonly bool $reserved,
        public readonly string $reservationId,
        public readonly array $shortages = []
    ) {}
}
