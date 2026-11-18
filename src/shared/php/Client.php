<?php
namespace Neocarts;

/** The Shop API (`/v1/shop/*`), server to server, with the shop's `nc_live_…` key. */
final class Client
{
    const DEFAULT_URL = 'https://core.neocarts.com';
    const TIMEOUT = 15;

    private $baseUrl;
    private $key;
    private $locale;
    private $userAgent;
    private $transport;

    /**
     * @param callable|null $transport fn(string $method, string $url, string[] $headers, ?string $body): array{0:int,1:string}
     *                                 — tests pass a fake; null means curl
     */
    public function __construct(string $baseUrl, string $key, string $locale, string $platform, ?callable $transport = null)
    {
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_URL, '/');
        $this->key = $key;
        $this->locale = $locale;
        $this->userAgent = 'neocarts-opencart/' . NEOCARTS_VERSION . ' (' . $platform . '; php ' . PHP_VERSION . ')';
        $this->transport = $transport ?: [self::class, 'curl'];
    }

    public function createOrder(array $items, ?string $redirectUrl): array
    {
        $body = ['locale' => $this->locale, 'items' => $items];
        if ($redirectUrl) {
            $body['redirect_url'] = $redirectUrl;
        }

        return $this->request('POST', '/v1/shop/orders/create', $body);
    }

    public function putItems(string $orderId, array $items): array
    {
        return $this->request('PUT', '/v1/shop/orders/' . rawurlencode($orderId) . '/items', ['items' => $items]);
    }

    public function putProduct(string $externalId, array $card): array
    {
        return $this->request('PUT', '/v1/shop/products/' . rawurlencode($externalId), $card);
    }

    /**
     * An empty order: 422 proves the key works, 401 that it does not. Creates nothing.
     *
     * @return string ok | unauthorized | unreachable | unexpected
     */
    public function check(): string
    {
        try {
            $this->request('POST', '/v1/shop/orders/create', new \stdClass());

            return 'unexpected';
        } catch (ApiError $e) {
            if ($e->status === 422) {
                return 'ok';
            }

            return $e->status === 401 ? 'unauthorized' : ($e->status === 0 ? 'unreachable' : 'unexpected');
        }
    }

    /** @throws ApiError */
    private function request(string $method, string $path, $body): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->key,
            'Accept: application/json',
            'Accept-Language: ' . $this->locale,
            'Content-Type: application/json',
            'User-Agent: ' . $this->userAgent,
        ];
        list($status, $raw) = call_user_func(
            $this->transport, $method, $this->baseUrl . $path, $headers,
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        if ($status === 0) {
            throw new ApiError(0, 'Shop API unreachable: ' . $raw);
        }
        $json = json_decode($raw, true);
        $json = is_array($json) ? $json : [];
        if ($status >= 300) {
            throw new ApiError(
                $status,
                isset($json['message']) ? (string) $json['message'] : 'HTTP ' . $status,
                isset($json['errors']) && is_array($json['errors']) ? $json['errors'] : []
            );
        }

        return $json;
    }

    /** @return array{0:int,1:string} status 0 + curl error on a network failure */
    public static function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch); // a no-op since 8.0, deprecated in 8.5
        }

        return $raw === false ? [0, $error] : [$status, (string) $raw];
    }
}
