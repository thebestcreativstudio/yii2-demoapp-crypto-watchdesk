<?php

declare(strict_types=1);

namespace app\services;

use app\models\PriceSnapshot;
use app\models\WatchlistItem;
use Yii;

/**
 * Builds the dashboard JSON for one user (watchlist + volume flags).
 */
final class DashboardBuilder
{
    public function __construct(
        private readonly PriceDeltaCalculator $deltaCalc = new PriceDeltaCalculator(),
        private readonly VolumeAnomalyDetector $anomaly = new VolumeAnomalyDetector(),
    ) {
    }

    public function build(int $userId): array
    {
        $lookback = (int)Yii::$app->params['anomalyLookback'];
        $ratio = (float)Yii::$app->params['anomalyVolumeRatio'];

        $items = WatchlistItem::find()->where(['user_id' => $userId])->orderBy(['name' => SORT_ASC])->all();
        $watch = [];
        foreach ($items as $item) {
            $watch[] = $this->rowForCoin($item->coingecko_id, $item->symbol, $item->name, $lookback, $ratio);
        }

        $anomalies = array_values(array_filter($watch, static fn(array $r) => !empty($r['unusual_volume'])));

        return [
            'watchlist' => $watch,
            'unusual_volume' => $anomalies,
        ];
    }

    private function rowForCoin(string $coinId, string $symbol, string $name, int $lookback, float $ratio): array
    {
        $latest = PriceSnapshot::find()
            ->where(['coingecko_id' => $coinId])
            ->orderBy(['captured_at' => SORT_DESC])
            ->one();

        if ($latest === null) {
            return [
                'coingecko_id' => $coinId,
                'symbol' => $symbol,
                'name' => $name,
                'price_usd' => null,
                'delta_percent' => null,
                'delta_abs' => null,
                'volume_24h' => null,
                'unusual_volume' => false,
                'captured_at' => null,
            ];
        }

        $target = date('Y-m-d H:i:s', strtotime($latest->captured_at) - 86400);
        $prev = PriceSnapshot::find()
            ->where(['coingecko_id' => $coinId])
            ->andWhere(['<=', 'captured_at', $target])
            ->orderBy(['captured_at' => SORT_DESC])
            ->one();
        if ($prev === null) {
            $prev = PriceSnapshot::find()
                ->where(['coingecko_id' => $coinId])
                ->andWhere(['<', 'id', $latest->id])
                ->orderBy(['captured_at' => SORT_ASC])
                ->one();
        }

        $delta = $this->deltaCalc->calc(
            (float)$latest->price_usd,
            $prev ? (float)$prev->price_usd : null
        );

        $prevVolumes = PriceSnapshot::find()
            ->select('volume_24h')
            ->where(['coingecko_id' => $coinId])
            ->andWhere(['<', 'id', $latest->id])
            ->orderBy(['captured_at' => SORT_DESC])
            ->limit($lookback)
            ->column();
        $prevVolumes = array_map('floatval', $prevVolumes);
        $unusual = $this->anomaly->isAnomalous((float)$latest->volume_24h, $prevVolumes, $ratio);

        return [
            'coingecko_id' => $coinId,
            'symbol' => $symbol,
            'name' => $name,
            'price_usd' => $delta['price'],
            'delta_percent' => $delta['delta_percent'],
            'delta_abs' => $delta['delta_abs'],
            'volume_24h' => (float)$latest->volume_24h,
            'unusual_volume' => $unusual,
            'captured_at' => $latest->captured_at,
            'compared_at' => $prev?->captured_at,
        ];
    }
}
