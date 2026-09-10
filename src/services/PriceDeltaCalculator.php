<?php

declare(strict_types=1);

namespace app\services;

/**
 * Pure math — easy to unit-test, no DB / HTTP.
 *
 * delta_percent = (now - then) / then * 100
 */
final class PriceDeltaCalculator
{
    /**
     * @return array{price:float, delta_abs:float|null, delta_percent:float|null}
     */
    public function calc(float $currentPrice, ?float $previousPrice): array
    {
        if ($previousPrice === null || $previousPrice == 0.0) {
            return [
                'price' => $currentPrice,
                'delta_abs' => null,
                'delta_percent' => null,
            ];
        }

        $abs = $currentPrice - $previousPrice;
        return [
            'price' => $currentPrice,
            'delta_abs' => $abs,
            'delta_percent' => ($abs / $previousPrice) * 100,
        ];
    }
}
