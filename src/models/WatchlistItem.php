<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $id @property int $user_id @property string $coingecko_id @property string $symbol @property string $name */
final class WatchlistItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%watchlist_item}}';
    }
}
