<?php
use Neocarts\ApiError;
use Neocarts\Client;

function fake_client(array $answer, array &$calls)
{
    return new Client('https://core.test/', 'nc_live_k', 'uk', 'oc 3.0.5.1', function ($m, $u, $h, $b) use ($answer, &$calls) {
        $calls[] = [$m, $u, $h, $b];

        return $answer;
    });
}

function test_client_sends_key_locale_and_body()
{
    $calls = [];
    $order = fake_client([201, '{"id":"o1","number":"N"}'], $calls)->createOrder([['external_id' => '42']], 'https://s/cart?neocarts=checkout');
    eq('o1', $order['id']);
    list($method, $url, $headers, $body) = $calls[0];
    eq('POST', $method);
    eq('https://core.test/v1/shop/orders/create', $url);
    eq(true, in_array('Authorization: Bearer nc_live_k', $headers, true));
    eq(true, in_array('Accept-Language: uk', $headers, true));
    eq(1, preg_match('#^User-Agent: neocarts-opencart/\S+ \(oc 3\.0\.5\.1; php #', implode("\n", preg_grep('/^User-Agent/', $headers))));
    eq('{"locale":"uk","items":[{"external_id":"42"}],"redirect_url":"https://s/cart?neocarts=checkout"}', $body);
}

function test_client_paths()
{
    $calls = [];
    $c = fake_client([200, '{}'], $calls);
    $c->putItems('o 1', []);
    $c->putProduct('42-5.17', ['title' => 't']);
    eq(['PUT', 'https://core.test/v1/shop/orders/o%201/items'], array_slice($calls[0], 0, 2));
    eq('https://core.test/v1/shop/products/42-5.17', $calls[1][1]);
}

function test_client_errors()
{
    $calls = [];
    $e = throws(function () use (&$calls) { fake_client([422, '{"message":"bad","errors":{"items.0.price":["x"]}}'], $calls)->createOrder([], null); }, 'bad');
    eq(422, $e->status);
    eq(['items.0.price' => ['x']], $e->errors);
    $e = throws(function () use (&$calls) { fake_client([0, 'timeout'], $calls)->putItems('o', []); });
    eq(0, $e->status);
    $e = throws(function () use (&$calls) { fake_client([502, '<html>'], $calls)->putItems('o', []); }, 'HTTP 502');
}

function test_client_check()
{
    $calls = [];
    eq('ok', fake_client([422, '{"message":"x"}'], $calls)->check());
    eq('{}', $calls[0][3], 'an empty object, not a list');
    eq('unauthorized', fake_client([401, '{}'], $calls)->check());
    eq('unreachable', fake_client([0, 'dns'], $calls)->check());
    eq('unexpected', fake_client([500, '{}'], $calls)->check());
}
