<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

final class SiteController extends Controller
{
    public function actionIndex(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->data = [
            'ok' => true,
            'message' => 'Crypto Watchdesk API. Open the Vue UI via nginx on :18100',
            'health' => '/api/health',
        ];
        return Yii::$app->response;
    }

    public function actionError(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['ok' => false, 'error' => Yii::$app->errorHandler->exception?->getMessage() ?: 'error'];
    }
}
