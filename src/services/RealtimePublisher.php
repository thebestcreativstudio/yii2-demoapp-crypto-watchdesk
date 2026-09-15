<?php

declare(strict_types=1);

namespace app\services;

use Predis\Client;
use Yii;

/**
 * Worker/API writes a Centrifugo publish command to Redis Stream.
 * Centrifugo consumes the stream and pushes to WS subscribers.
 */
final class RealtimePublisher
{
    /**
     * @param array{coins?:int, snapshots?:int, alerts_fired?:int, captured_at?:string} $sync
     */
    public function publishSynced(array $sync): void
    {
        try {
            $redis = new Client([
                'host' => (string)Yii::$app->params['redisHost'],
                'port' => (int)Yii::$app->params['redisPort'],
            ]);
            $payload = json_encode([
                'channel' => (string)Yii::$app->params['centrifugoChannel'],
                'data' => [
                    'type' => 'synced',
                    'coins' => (int)($sync['coins'] ?? 0),
                    'snapshots' => (int)($sync['snapshots'] ?? 0),
                    'alerts_fired' => (int)($sync['alerts_fired'] ?? 0),
                    'captured_at' => (string)($sync['captured_at'] ?? ''),
                ],
            ], JSON_UNESCAPED_UNICODE);
            $redis->xadd((string)Yii::$app->params['redisStream'], [
                'method' => 'publish',
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            error_log('[realtime] publish failed: ' . $e->getMessage());
        }
    }
}
