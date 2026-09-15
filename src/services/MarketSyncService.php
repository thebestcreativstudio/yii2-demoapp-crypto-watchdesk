<?php

declare(strict_types=1);

namespace app\services;

use app\models\PriceSnapshot;
use app\models\WatchlistItem;

/**
 * 1) Read all unique coins from watchlists
 * 2) Fetch CoinGecko markets
 * 3) Insert price_snapshot rows
 * 4) Evaluate alert rules → notifications
 */
final class MarketSyncService
{
    public function __construct(
        private readonly CoinGeckoClient $client = new CoinGeckoClient(),
        private readonly AlertEvaluator $alerts = new AlertEvaluator(),
    ) {
    }

    /**
     * @return array{coins:int, snapshots:int, alerts_fired:int, captured_at:string}
     */
    public function syncAllWatchlists(): array
    {
        $ids = WatchlistItem::find()
            ->select('coingecko_id')
            ->distinct()
            ->column();

        /** @var list<string> $ids */
        $ids = array_values(array_unique(array_map('strval', $ids)));
        return $this->syncCoins($ids);
    }

    /**
     * Fetch markets + write snapshots for given coin ids.
     *
     * @param list<string> $ids
     * @return array{coins:int, snapshots:int, alerts_fired:int, captured_at:string}
     */
    public function syncCoins(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return ['coins' => 0, 'snapshots' => 0, 'alerts_fired' => 0, 'captured_at' => date('Y-m-d H:i:s')];
        }

        $markets = $this->client->markets($ids);
        $now = date('Y-m-d H:i:s');
        $written = 0;

        foreach ($markets as $coinId => $m) {
            $snap = new PriceSnapshot();
            $snap->coingecko_id = $coinId;
            $snap->captured_at = $now;
            $snap->price_usd = (string)$m['price'];
            $snap->volume_24h = (string)$m['volume'];
            $snap->market_cap = (string)$m['market_cap'];
            $snap->save(false);
            $written++;

            WatchlistItem::updateAll(
                ['symbol' => $m['symbol'], 'name' => $m['name']],
                ['coingecko_id' => $coinId]
            );
        }

        $fired = $this->alerts->evaluateAfterSync($markets);

        $result = [
            'coins' => count($ids),
            'snapshots' => $written,
            'alerts_fired' => $fired,
            'captured_at' => $now,
        ];
        if ($written > 0) {
            (new RealtimePublisher())->publishSynced($result);
        }
        return $result;
    }
}
