<?php

declare(strict_types=1);

namespace app\commands;

use app\services\MarketSyncService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Worker / cron entry:
 *   php yii sync/once
 */
final class SyncController extends Controller
{
    public function actionOnce(): int
    {
        try {
            $result = (new MarketSyncService())->syncAllWatchlists();
            $this->stdout(json_encode($result, JSON_PRETTY_PRINT) . "\n");
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
