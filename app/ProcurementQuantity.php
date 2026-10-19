<?php

namespace App;

use InvalidArgumentException;

final class ProcurementQuantity
{
    public static function toMilliUnits(string $quantity): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity)) {
            throw new InvalidArgumentException('Procurement quantities must be nonnegative decimals with at most three places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    public static function toSignedMilliUnits(string $quantity): int
    {
        if (! preg_match('/^-?\d+(?:\.\d{1,3})?$/', $quantity)) {
            throw new InvalidArgumentException('Signed quantities must be decimals with at most three places.');
        }

        $isNegative = str_starts_with($quantity, '-');
        $absoluteMilliUnits = self::toMilliUnits(ltrim($quantity, '-'));

        return $isNegative ? -$absoluteMilliUnits : $absoluteMilliUnits;
    }

    public static function fromMilliUnits(int $quantity): string
    {
        return intdiv($quantity, 1000).'.'.str_pad((string) ($quantity % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function fromSignedMilliUnits(int $quantity): string
    {
        return $quantity < 0
            ? '-'.self::fromMilliUnits(abs($quantity))
            : self::fromMilliUnits($quantity);
    }

    public static function sumMilliUnits(iterable $quantities): int
    {
        $total = 0;

        foreach ($quantities as $quantity) {
            $total += self::toMilliUnits((string) $quantity);
        }

        return $total;
    }
}
