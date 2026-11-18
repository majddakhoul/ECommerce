<?php

namespace App\Support\Processing;

use InvalidArgumentException;

final class ProcessingMode
{
    public const SYNC = 'sync';

    public const ASYNC = 'async';

    public const ALL = [self::SYNC, self::ASYNC];

    private string $mode = self::SYNC;

    public function set(string $mode): void
    {
        if (!in_array($mode, self::ALL, true)) {
            throw new InvalidArgumentException("Unknown processing mode '{$mode}'.");
        }

        $this->mode = $mode;
    }

    public function current(): string
    {
        return $this->mode;
    }

    public function isAsync(): bool
    {
        return $this->mode === self::ASYNC;
    }
}
