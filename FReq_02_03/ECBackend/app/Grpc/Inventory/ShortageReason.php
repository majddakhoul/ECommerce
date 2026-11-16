<?php

namespace App\Grpc\Inventory;

use UnexpectedValueException;

class ShortageReason
{
    const SHORTAGE_REASON_UNSPECIFIED = 0;

    const INSUFFICIENT_STOCK = 1;

    const PRODUCT_NOT_FOUND = 2;

    private static $valueToName = [
        self::SHORTAGE_REASON_UNSPECIFIED => 'SHORTAGE_REASON_UNSPECIFIED',
        self::INSUFFICIENT_STOCK => 'INSUFFICIENT_STOCK',
        self::PRODUCT_NOT_FOUND => 'PRODUCT_NOT_FOUND',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }

    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

