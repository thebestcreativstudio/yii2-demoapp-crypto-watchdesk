<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

return [
    'id' => 'crypto-watchdesk-web',
    'basePath' => dirname(__DIR__),
    'vendorPath' => dirname(__DIR__) . '/vendor',
    'controllerNamespace' => 'app\\controllers',
    'defaultRoute' => 'site/index',
    'aliases' => [
        '@app' => dirname(__DIR__),
        '@runtime' => dirname(__DIR__) . '/runtime',
        '@webroot' => dirname(__DIR__) . '/web',
        '@web' => '',
    ],
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => (string)env('DB_DSN', 'mysql:host=127.0.0.1;dbname=crypto_watchdesk'),
            'username' => (string)env('DB_USER', 'root'),
            'password' => (string)env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],
        'request' => [
            'cookieValidationKey' => (string)env('COOKIE_VALIDATION_KEY', 'dev'),
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'user' => [
            'identityClass' => app\models\User::class,
            'enableAutoLogin' => true,
            'loginUrl' => null,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET api/health' => 'api/health',
                'POST api/auth/login' => 'auth/login',
                'POST api/auth/logout' => 'auth/logout',
                'GET api/me' => 'api/me',

                'GET api/watchlist' => 'api/watchlist',
                'POST api/watchlist' => 'api/watchlist-add',
                'DELETE api/watchlist/<coingeckoId:[\\w-]+>' => 'api/watchlist-remove',

                'GET api/catalog' => 'api/catalog',
                'GET api/history/<coingeckoId:[\\w-]+>' => 'api/history',
                'GET api/search' => 'api/search',
                'POST api/sync' => 'api/sync',
                'GET api/dashboard' => 'api/dashboard',
                'GET api/convert' => 'api/convert',

                'GET api/alerts' => 'api/alerts',
                'POST api/alerts' => 'api/alerts-create',
                'DELETE api/alerts/<id:\\d+>' => 'api/alerts-remove',

                'GET api/notifications' => 'api/notifications',
                'GET api/realtime/token' => 'api/realtime-token',
            ],
        ],
        'log' => [
            'targets' => [[
                'class' => yii\log\FileTarget::class,
                'levels' => ['error', 'warning'],
                'logFile' => '@runtime/app.log',
            ]],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
    ],
    'params' => array_merge(require __DIR__ . '/params.php', [
        'coingeckoBaseUrl' => (string)env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'coingeckoApiKey' => (string)env('COINGECKO_API_KEY', ''),
        'frontendUrl' => (string)env('FRONTEND_URL', 'http://localhost:18100'),
        'syncIntervalSeconds' => (int)env('SYNC_INTERVAL_SECONDS', 300),
        'redisHost' => (string)env('REDIS_HOST', '127.0.0.1'),
        'redisPort' => (int)env('REDIS_PORT', 6379),
        'redisStream' => (string)env('REDIS_STREAM', 'desk:sync'),
        'centrifugoHmacSecret' => (string)env('CENTRIFUGO_HMAC_SECRET', 'crypto-watchdesk-centrifugo-hmac'),
        'centrifugoChannel' => (string)env('CENTRIFUGO_CHANNEL', 'desk'),
    ]),
];
