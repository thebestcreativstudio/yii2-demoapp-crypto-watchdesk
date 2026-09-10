<?php

declare(strict_types=1);

namespace app\services;

use app\models\PriceSnapshot;

/**
 * Convert amount of coin A into coin B using latest USD prices from snapshots.
 *
 * amount_to = amount_from * (price_from_usd / price_to_usd)
 */
final class CoinConverter
{
    /**
     * Pure math (unit-tested).
     *
     * @return array{amount_to:float, rate:float}
     */
    public function convert(float $amountFrom, float $priceFromUsd, float $priceToUsd): array
    {
        if ($amountFrom < 0) {
            throw new \InvalidArgumentException('amount must be >= 0');
        }
        if ($priceFromUsd <= 0 || $priceToUsd <= 0) {
            throw new \InvalidArgumentException('prices must be > 0');
        }

        $rate = $priceFromUsd / $priceToUsd; // 1 from = rate to
        return [
            'amount_to' => $amountFrom * $rate,
            'rate' => $rate,
        ];
    }

    /**
     * Load latest snapshot prices and convert.
     *
     * @return array{
     *   from:string,
     *   to:string,
     *   amount_from:float,
     *   amount_to:float,
     *   rate:float,
     *   price_from_usd:float,
     *   price_to_usd:float,
     *   captured_from:?string,
     *   captured_to:?string
     * }
     */
    public function convertByIds(string $fromId, string $toId, float $amountFrom): array
    {
        $fromSnap = $this->latest($fromId);
        $toSnap = $this->latest($toId);
        if ($fromSnap === null || $toSnap === null) {
            throw new \RuntimeException(
                'No price snapshot for one of the coins. Add them to watchlist and press Sync.'
            );
        }

        $priceFrom = (float)$fromSnap->price_usd;
        $priceTo = (float)$toSnap->price_usd;
        $math = $this->convert($amountFrom, $priceFrom, $priceTo);

        return [
            'from' => $fromId,
            'to' => $toId,
            'amount_from' => $amountFrom,
            'amount_to' => $math['amount_to'],
            'rate' => $math['rate'],
            'price_from_usd' => $priceFrom,
            'price_to_usd' => $priceTo,
            'captured_from' => $fromSnap->captured_at,
            'captured_to' => $toSnap->captured_at,
        ];
    }

    private function latest(string $coinId): ?PriceSnapshot
    {
        return PriceSnapshot::find()
            ->where(['coingecko_id' => $coinId])
            ->orderBy(['captured_at' => SORT_DESC])
            ->one();
    }
}
