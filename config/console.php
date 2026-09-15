<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

return [
    'id' => 'crypto-watchdesk-console',
    'basePath' => dirname(__DIR__),
    'vendorPath' => dirname(__DIR__) . '/vendor',
    'controllerNamespace' => 'app\\commands',
    'controllerMap' => [
        'migrate' => [
            'class' => yii\console\controllers\MigrateController::class,
            'migrationPath' => ['@app/migrations'],
        ],
    ],
    'aliases' => [
        '@app' => dirname(__DIR__),
        '@runtime' => dirname(__DIR__) . '/runtime',
    ],
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => (string)env('DB_DSN', 'mysql:host=127.0.0.1;dbname=crypto_watchdesk'),
            'username' => (string)env('DB_USER', 'root'),
            'password' => (string)env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
    ],
    'params' => array_merge(require __DIR__ . '/params.php', [
        'coingeckoBaseUrl' => (string)env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'coingeckoApiKey' => (string)env('COINGECKO_API_KEY', ''),
        'syncIntervalSeconds' => (int)env('SYNC_INTERVAL_SECONDS', 300),
        'redisHost' => (string)env('REDIS_HOST', '127.0.0.1'),
        'redisPort' => (int)env('REDIS_PORT', 6379),
        'redisStream' => (string)env('REDIS_STREAM', 'desk:sync'),
        'centrifugoChannel' => (string)env('CENTRIFUGO_CHANNEL', 'desk'),
    ]),
];
