<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $id @property int $user_id @property string $message @property string|null $coingecko_id @property string $created_at @property bool $is_read */
final class Notification extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%notification}}';
    }
}
