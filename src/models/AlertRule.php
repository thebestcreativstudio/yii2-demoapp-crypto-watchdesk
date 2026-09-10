<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $id @property int $user_id @property string $coingecko_id @property string $threshold_percent @property bool $is_active */
final class AlertRule extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%alert_rule}}';
    }
}
