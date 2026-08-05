<?php

declare(strict_types=1);

final class WooCommerceClient
{
    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $apiVersion = 'wc/v3'
    ) {
    }

    public static function fromIntegration(array $integration, array $credentials): self
    {
        $config = IntegrationService::config($integration);
        $url = rtrim((string) ($config['store_url'] ?? ''), '/');
        $version = (string) ($config['api_version'] ?? 'wc/v3');
        $key = (string) ($credentials['consumer_key'] ?? '');
        $secret = (string) ($credentials['consumer_secret'] ?? '');
        if ($url === '' || $key === '' || $secret === '') {
            throw new RuntimeException('Faltan URL o credenciales de WooCommerce.');
        }
        return new self($url, $key, $secret, $version);
    }

    /** @return array{ok:bool,message:string,store_name?:string} */
    public function testConnection(): array
    {
        try {
            $data = $this->get('products', ['per_page' => 1]);
            $store = parse_url($this->storeUrl, PHP_URL_HOST) ?: $this->storeUrl;
            return [
                'ok' => true,
                'message' => 'Conexión OK con ' . $store,
                'store_name' => (string) $store,
                'sample_count' => is_array($data) ? count($data) : 0,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return list<array<string,mixed>> */
    public function fetchAllProducts(int $maxPages = 20): array
    {
        return $this->paginate('products', ['status' => 'publish'], $maxPages);
    }

    /** @return list<array<string,mixed>> */
    public function fetchOrdersSince(?string $isoDate, int $maxPages = 10): array
    {
        $params = [
            'status' => 'any',
            'orderby' => 'date',
            'order' => 'desc',
        ];
        if ($isoDate) {
            $params['after'] = $isoDate;
        }
        return $this->paginate('orders', $params, $maxPages);
    }

    /** @return list<array<string,mixed>> */
    public function fetchProductVariations(int $productId, int $maxPages = 10): array
    {
        if ($productId <= 0) {
            return [];
        }
        return $this->paginate('products/' . $productId . '/variations', [], $maxPages);
    }

    /** @param array<string, scalar> $query @return list<array<string,mixed>> */
    private function paginate(string $resource, array $query, int $maxPages): array
    {
        $all = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $chunk = $this->get($resource, $query + ['page' => $page, 'per_page' => 100]);
            if (!is_array($chunk) || $chunk === []) {
                break;
            }
            foreach ($chunk as $item) {
                if (is_array($item)) {
                    $all[] = $item;
                }
            }
            if (count($chunk) < 100) {
                break;
            }
        }
        return $all;
    }

    /** @param array<string, scalar> $query @return mixed */
    public function get(string $resource, array $query = []): mixed
    {
        $base = $this->storeUrl . '/wp-json/' . trim($this->apiVersion, '/') . '/' . ltrim($resource, '/');
        $query['consumer_key'] = $this->consumerKey;
        $query['consumer_secret'] = $this->consumerSecret;
        $url = $base . '?' . http_build_query($query);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar cURL.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'PatriumHub/1.0',
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('Error de red WooCommerce: ' . $error);
        }
        if ($body === false || $body === '') {
            throw new RuntimeException('Respuesta vacía de WooCommerce (HTTP ' . $code . ').');
        }
        $decoded = json_decode($body, true);
        if ($code >= 400) {
            $msg = is_array($decoded) ? (string) ($decoded['message'] ?? $body) : $body;
            if ($code === 401 || $code === 403) {
                throw new RuntimeException('Auth WooCommerce fallida (HTTP ' . $code . '): ' . $msg);
            }
            throw new RuntimeException('WooCommerce HTTP ' . $code . ': ' . $msg);
        }
        return $decoded;
    }
}
