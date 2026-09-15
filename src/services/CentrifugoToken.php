<?php

declare(strict_types=1);

namespace app\services;

use app\models\User;
use Firebase\JWT\JWT;
use Yii;

final class CentrifugoToken
{
    /**
     * @return array{token:string, channel:string, expires_in:int}
     */
    public function forUser(User $user): array
    {
        $ttl = 3600;
        $secret = (string)Yii::$app->params['centrifugoHmacSecret'];
        $token = JWT::encode([
            'sub' => (string)$user->id,
            'exp' => time() + $ttl,
            'iat' => time(),
        ], $secret, 'HS256');

        return [
            'token' => $token,
            'channel' => (string)Yii::$app->params['centrifugoChannel'],
            'expires_in' => $ttl,
        ];
    }
}
