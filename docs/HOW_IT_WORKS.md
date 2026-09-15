# Как это работает (простыми словами)

## Одна картинка в голове

```
Vue UI  ──HTTP──►  Yii2 API  ──►  MySQL
                      ▲
                      │
Worker (sync/once) ───┘  ◄── CoinGecko API
      │
      └── Redis Stream ──► Centrifugo ──WS──► Vue (Pinia)
```

1. Логин (`demo` / `demo1234`).
2. **Додати для відстеження** — multi-select з каталогу CoinGecko → watchlist.
3. **Sync** дергает CoinGecko и пишет строки в `price_snapshot`.
4. UI показывает **курс USD**; кнопка **Графік** — история из наших snapshots (`/api/history`).
5. На бэке по-прежнему есть Δ% / unusual volume (для badge и alert rules).
6. **Converter**: монета + кількість → таблиця курсів на інші з watchlist.
7. Alert: если |Δ%| ≥ порог → строка в `notification`.
8. После sync воркер кладёт команду в Redis Stream; Centrifugo пушит в WebSocket; Vue обновляет Pinia без кнопки.

## Где смотреть код

| Файл | Зачем |
|------|--------|
| `src/services/CoinGeckoClient.php` | HTTP к CoinGecko |
| `src/services/MarketSyncService.php` | sync + snapshots + Redis publish |
| `src/services/RealtimePublisher.php` | XADD в Redis Stream для Centrifugo |
| `src/services/PriceDeltaCalculator.php` | математика Δ |
| `src/services/VolumeAnomalyDetector.php` | unusual volume |
| `src/services/CoinConverter.php` | обмен A→B |
| `src/services/DashboardBuilder.php` | JSON для UI |
| `src/controllers/ApiController.php` | все `/api/*` |
| `docker-compose.yml` | mysql, redis, centrifugo, php, nginx, worker |
| `tests/unit/MathServicesTest.php` | unit-тесты |

## Демо логин

- username: `demo`
- password: `demo1234`
