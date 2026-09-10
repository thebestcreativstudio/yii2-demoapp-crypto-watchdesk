<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $coingecko_id
 * @property string $captured_at
 * @property string $price_usd
 * @property string $volume_24h
 * @property string $market_cap
 */
final class PriceSnapshot extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%price_snapshot}}';
    }
}
