<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
defined('YII_DEBUG') or define('YII_DEBUG', (bool)(int)env('APP_DEBUG', '0'));
defined('YII_ENV') or define('YII_ENV', YII_DEBUG ? 'dev' : 'prod');
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
$config = require __DIR__ . '/../config/web.php';
(new yii\web\Application($config))->run();
