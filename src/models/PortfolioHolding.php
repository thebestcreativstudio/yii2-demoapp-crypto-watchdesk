<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/** @property int $id @property int $user_id @property string $coingecko_id @property string $quantity @property string|null $cost_basis_usd */
final class PortfolioHolding extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%portfolio_holding}}';
    }
}
