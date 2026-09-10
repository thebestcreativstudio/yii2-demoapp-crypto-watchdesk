<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\httpclient\Client;

/**
 * Thin wrapper around CoinGecko HTTP API.
 * Docs: https://docs.coingecko.com/
 */
final class CoinGeckoClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'baseUrl' => rtrim((string)Yii::$app->params['coingeckoBaseUrl'], '/'),
        ]);
    }

    /**
     * Search coins by name/symbol.
     *
     * @return list<array{id:string, symbol:string, name:string}>
     */
    public function search(string $query): array
    {
        $data = $this->get('search', ['query' => $query]);
        $out = [];
        foreach (array_slice($data['coins'] ?? [], 0, 15) as $coin) {
            $out[] = [
                'id' => (string)$coin['id'],
                'symbol' => strtolower((string)($coin['symbol'] ?? '')),
                'name' => (string)($coin['name'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Current markets data for a list of coingecko ids.
     *
     * @param list<string> $ids
     * @return array<string, array{price:float, volume:float, market_cap:float, symbol:string, name:string}>
     */
    public function markets(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $data = $this->get('coins/markets', [
            'vs_currency' => 'usd',
            'ids' => implode(',', $ids),
            'order' => 'market_cap_desc',
            'per_page' => max(count($ids), 1),
            'page' => 1,
            'sparkline' => 'false',
        ]);

        $map = [];
        foreach ($data as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $map[(string)$row['id']] = [
                'price' => (float)($row['current_price'] ?? 0),
                'volume' => (float)($row['total_volume'] ?? 0),
                'market_cap' => (float)($row['market_cap'] ?? 0),
                'symbol' => strtolower((string)($row['symbol'] ?? '')),
                'name' => (string)($row['name'] ?? ''),
            ];
        }
        return $map;
    }

    /**
     * Top coins by market cap — for multi-select "add to tracking".
     *
     * @return list<array{id:string, symbol:string, name:string, price_usd:?float}>
     */
    public function topCatalog(int $limit = 50): array
    {
        $data = $this->get('coins/markets', [
            'vs_currency' => 'usd',
            'order' => 'market_cap_desc',
            'per_page' => min(max($limit, 1), 100),
            'page' => 1,
            'sparkline' => 'false',
        ]);
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $price = $row['current_price'] ?? null;
            $out[] = [
                'id' => (string)$row['id'],
                'symbol' => strtolower((string)($row['symbol'] ?? '')),
                'name' => (string)($row['name'] ?? ''),
                'price_usd' => $price === null ? null : (float)$price,
            ];
        }
        return $out;
    }

    private function get(string $path, array $query): array
    {
        $headers = [
            'Accept' => 'application/json',
            // CoinGecko rejects empty/default UA with HTTP 403
            'User-Agent' => 'crypto-watchdesk/1.0 (portfolio demo; local docker)',
        ];
        $apiKey = (string)(Yii::$app->params['coingeckoApiKey'] ?? '');
        if ($apiKey !== '') {
            // Demo plan key from https://www.coingecko.com/en/api
            $headers['x-cg-demo-api-key'] = $apiKey;
        }

        $response = $this->http->createRequest()
            ->setMethod('GET')
            ->setUrl($path)
            ->setData($query)
            ->addHeaders($headers)
            ->send();

        if (!$response->isOk) {
            throw new \RuntimeException('CoinGecko error HTTP ' . $response->statusCode . ': ' . $response->content);
        }

        $data = $response->data;
        return is_array($data) ? $data : [];
    }
}
