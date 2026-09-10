<?php

declare(strict_types=1);

namespace app\services;

use app\models\AlertRule;
use app\models\Notification;
use app\models\PriceSnapshot;

/**
 * After each sync: for every active rule, compare latest price vs ~24h ago.
 * If |delta%| >= threshold → write a notification (simple de-dupe: one per coin per hour).
 */
final class AlertEvaluator
{
    public function __construct(
        private readonly PriceDeltaCalculator $delta = new PriceDeltaCalculator(),
    ) {
    }

    /**
     * @param array<string, array{price:float, volume:float, market_cap:float, symbol:string, name:string}> $markets
     */
    public function evaluateAfterSync(array $markets): int
    {
        $rules = AlertRule::find()->where(['is_active' => 1])->all();
        $fired = 0;

        foreach ($rules as $rule) {
            $coinId = $rule->coingecko_id;
            if (!isset($markets[$coinId])) {
                continue;
            }

            $latest = PriceSnapshot::find()
                ->where(['coingecko_id' => $coinId])
                ->orderBy(['captured_at' => SORT_DESC])
                ->one();
            if ($latest === null) {
                continue;
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
            if ($prev === null) {
                continue;
            }

            $calc = $this->delta->calc((float)$latest->price_usd, (float)$prev->price_usd);
            $pct = $calc['delta_percent'];
            if ($pct === null) {
                continue;
            }
            if (abs($pct) < (float)$rule->threshold_percent) {
                continue;
            }

            // De-dupe: skip if same user+coin notified in last hour
            $recent = Notification::find()
                ->where(['user_id' => $rule->user_id, 'coingecko_id' => $coinId])
                ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', time() - 3600)])
                ->exists();
            if ($recent) {
                continue;
            }

            $sign = $pct >= 0 ? '+' : '';
            $n = new Notification();
            $n->user_id = $rule->user_id;
            $n->coingecko_id = $coinId;
            $n->message = sprintf(
                '%s moved %s%.2f%% (threshold %.2f%%). Price $%s',
                $markets[$coinId]['name'] ?: $coinId,
                $sign,
                $pct,
                (float)$rule->threshold_percent,
                number_format((float)$latest->price_usd, 4, '.', '')
            );
            $n->save(false);
            $fired++;
        }

        return $fired;
    }
}
