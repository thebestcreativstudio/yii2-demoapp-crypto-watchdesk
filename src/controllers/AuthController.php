<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\User;
use Yii;
use yii\web\Controller;
use yii\web\Response;

final class AuthController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return parent::beforeAction($action);
    }

    /** POST { "username":"demo", "password":"demo1234" } */
    public function actionLogin(): array
    {
        $body = Yii::$app->request->bodyParams;
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $user = User::findByUsername($username);
        if ($user === null || !$user->validatePassword($password)) {
            Yii::$app->response->statusCode = 401;
            return ['ok' => false, 'error' => 'Invalid username or password'];
        }
        Yii::$app->user->login($user, 3600 * 24 * 14);
        return ['ok' => true, 'user' => ['id' => $user->id, 'username' => $user->username]];
    }

    public function actionLogout(): array
    {
        Yii::$app->user->logout();
        return ['ok' => true];
    }
}
