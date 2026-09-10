<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\AlertRule;
use app\models\Notification;
use app\models\User;
use app\models\WatchlistItem;
use app\services\CoinConverter;
use app\services\CoinGeckoClient;
use app\services\DashboardBuilder;
use app\services\MarketSyncService;
use Yii;
use yii\filters\Cors;
use yii\web\Controller;
use yii\web\Response;

/**
 * All JSON endpoints used by the Vue UI.
 * Open this file to see the whole API surface.
 */
final class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['cors'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => [
                    Yii::$app->params['frontendUrl'],
                    'http://localhost:18100',
                    'http://127.0.0.1:18100',
                ],
                'Access-Control-Request-Method' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
                'Access-Control-Allow-Credentials' => true,
                'Access-Control-Request-Headers' => ['*'],
            ],
        ];
        return $behaviors;
    }

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return parent::beforeAction($action);
    }

    public function actionHealth(): array
    {
        return [
            'ok' => true,
            'app' => 'crypto-watchdesk',
            'hint' => 'Login as demo / demo1234 then POST /api/sync',
        ];
    }

    public function actionMe(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        return ['ok' => true, 'user' => ['id' => $user->id, 'username' => $user->username]];
    }

    public function actionCatalog(): array
    {
        if ($this->userOrNull() === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        try {
            return ['ok' => true, 'coins' => (new CoinGeckoClient())->topCatalog(50)];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 502;
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Price history from our snapshots — for the chart. */
    public function actionHistory(string $coingeckoId): array
    {
        if ($this->userOrNull() === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $rows = \app\models\PriceSnapshot::find()
            ->where(['coingecko_id' => $coingeckoId])
            ->orderBy(['captured_at' => SORT_ASC])
            ->limit(200)
            ->asArray()
            ->all();
        $points = [];
        foreach ($rows as $r) {
            $points[] = [
                't' => $r['captured_at'],
                'price' => (float)$r['price_usd'],
            ];
        }
        return ['ok' => true, 'coingecko_id' => $coingeckoId, 'points' => $points];
    }

    public function actionSearch(string $q = ''): array
    {
        if ($this->userOrNull() === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $q = trim($q !== '' ? $q : (string)Yii::$app->request->get('q', ''));
        if (strlen($q) < 2) {
            return ['ok' => true, 'coins' => []];
        }
        try {
            return ['ok' => true, 'coins' => (new CoinGeckoClient())->search($q)];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 502;
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function actionWatchlist(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $rows = WatchlistItem::find()->where(['user_id' => $user->id])->orderBy(['name' => SORT_ASC])->asArray()->all();
        return ['ok' => true, 'items' => $rows];
    }

    public function actionWatchlistAdd(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $body = Yii::$app->request->bodyParams;

        // Multi-add: { "coins": [ {id, symbol, name}, ... ] }
        $coins = $body['coins'] ?? null;
        $addedIds = [];
        if (is_array($coins)) {
            $added = [];
            foreach ($coins as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $coinId = trim((string)($c['id'] ?? $c['coingecko_id'] ?? ''));
                if ($coinId === '') {
                    continue;
                }
                $item = WatchlistItem::findOne(['user_id' => $user->id, 'coingecko_id' => $coinId]);
                if ($item === null) {
                    $item = new WatchlistItem();
                    $item->user_id = $user->id;
                    $item->coingecko_id = $coinId;
                }
                $item->symbol = strtolower((string)($c['symbol'] ?? $item->symbol));
                $item->name = (string)($c['name'] ?? $item->name ?: $coinId);
                $item->save(false);
                $added[] = $item->toArray();
                $addedIds[] = $coinId;
            }
            $sync = $this->syncAddedCoins($addedIds);
            return ['ok' => true, 'items' => $added, 'sync' => $sync];
        }

        $coinId = trim((string)($body['coingecko_id'] ?? ''));
        if ($coinId === '') {
            return ['ok' => false, 'error' => 'coingecko_id or coins[] required'];
        }
        $item = WatchlistItem::findOne(['user_id' => $user->id, 'coingecko_id' => $coinId]);
        if ($item === null) {
            $item = new WatchlistItem();
            $item->user_id = $user->id;
            $item->coingecko_id = $coinId;
        }
        $item->symbol = strtolower((string)($body['symbol'] ?? $item->symbol));
        $item->name = (string)($body['name'] ?? $item->name ?: $coinId);
        $item->save(false);
        $sync = $this->syncAddedCoins([$coinId]);
        return ['ok' => true, 'item' => $item->toArray(), 'sync' => $sync];
    }

    /**
     * @param list<string> $ids
     * @return array{coins:int, snapshots:int, alerts_fired:int, captured_at:string}|array{error:string}
     */
    private function syncAddedCoins(array $ids): array
    {
        try {
            return (new MarketSyncService())->syncCoins($ids);
        } catch (\Throwable $e) {
            Yii::warning('watchlist add sync failed: ' . $e->getMessage(), __METHOD__);
            return ['error' => $e->getMessage(), 'coins' => 0, 'snapshots' => 0, 'alerts_fired' => 0, 'captured_at' => date('Y-m-d H:i:s')];
        }
    }

    public function actionWatchlistRemove(string $coingeckoId): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        WatchlistItem::deleteAll(['user_id' => $user->id, 'coingecko_id' => $coingeckoId]);
        return ['ok' => true];
    }

    public function actionSync(): array
    {
        if ($this->userOrNull() === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        try {
            $result = (new MarketSyncService())->syncAllWatchlists();
            return ['ok' => true, 'sync' => $result];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 502;
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function actionDashboard(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        return ['ok' => true, 'data' => (new DashboardBuilder())->build((int)$user->id)];
    }

    /**
     * GET /api/convert?from=bitcoin&to=ethereum&amount=1
     * Uses latest snapshots (sync first if empty).
     */
    public function actionConvert(): array
    {
        if ($this->userOrNull() === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $from = trim((string)Yii::$app->request->get('from', ''));
        $to = trim((string)Yii::$app->request->get('to', ''));
        $amount = (float)Yii::$app->request->get('amount', 0);
        if ($from === '' || $to === '' || $amount < 0) {
            return ['ok' => false, 'error' => 'from, to, amount required'];
        }
        try {
            return ['ok' => true, 'result' => (new CoinConverter())->convertByIds($from, $to, $amount)];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function actionAlerts(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        return [
            'ok' => true,
            'rules' => AlertRule::find()->where(['user_id' => $user->id])->asArray()->all(),
        ];
    }

    public function actionAlertsCreate(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $body = Yii::$app->request->bodyParams;
        $coinId = trim((string)($body['coingecko_id'] ?? ''));
        $threshold = (float)($body['threshold_percent'] ?? 0);
        if ($coinId === '' || $threshold <= 0) {
            return ['ok' => false, 'error' => 'coingecko_id and threshold_percent > 0 required'];
        }
        $rule = new AlertRule();
        $rule->user_id = $user->id;
        $rule->coingecko_id = $coinId;
        $rule->threshold_percent = (string)$threshold;
        $rule->is_active = true;
        $rule->save(false);
        return ['ok' => true, 'rule' => $rule->toArray()];
    }

    public function actionAlertsRemove(int $id): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        AlertRule::deleteAll(['id' => $id, 'user_id' => $user->id]);
        return ['ok' => true];
    }

    public function actionNotifications(): array
    {
        $user = $this->userOrNull();
        if ($user === null) {
            return ['ok' => false, 'error' => 'unauthorized'];
        }
        $rows = Notification::find()
            ->where(['user_id' => $user->id])
            ->orderBy(['id' => SORT_DESC])
            ->limit(50)
            ->asArray()
            ->all();
        return ['ok' => true, 'notifications' => $rows];
    }

    private function userOrNull(): ?User
    {
        $user = Yii::$app->user->identity;
        if (!$user instanceof User) {
            Yii::$app->response->statusCode = 401;
            return null;
        }
        return $user;
    }
}
