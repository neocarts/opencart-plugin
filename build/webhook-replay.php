<?php
// make webhook-replay E=order.paid S=oc3 — signs tests/fixtures/<E>.json with NEOCARTS_WEBHOOK_SECRET
// from .env (fresh delivery id and order id each time) and POSTs it to the shop on the stand.
require __DIR__ . '/../src/shared/php/autoload.php';

list(, $event, $shop) = array_pad($argv, 3, null);
$hosts = ['oc2' => 'http://oc2', 'oc3' => 'http://oc3', 'oc4' => 'http://oc4']; // the compose network
$file = __DIR__ . "/../tests/fixtures/$event.json";
if (!$event || !isset($hosts[$shop]) || !is_file($file)) {
    fwrite(STDERR, "usage: make webhook-replay E=<fixture, e.g. order.paid> S=<oc2|oc3|oc4>\n");
    exit(1);
}

$env = parse_ini_file(__DIR__ . '/../.env') ?: [];
$secret = isset($env['NEOCARTS_WEBHOOK_SECRET']) ? $env['NEOCARTS_WEBHOOK_SECRET'] : '';
if ($secret === '') {
    fwrite(STDERR, "NEOCARTS_WEBHOOK_SECRET is empty in .env — set it and the same secret in the shop's settings\n");
    exit(1);
}

$uuid = function () {
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
};
$envelope = json_decode(file_get_contents($file), true);
$envelope['id'] = $uuid();
if (getenv('KEEP_ORDER') === false && in_array($event, ['order.paid'], true)) {
    $envelope['data']['id'] = $uuid();
    $envelope['data']['number'] = date('ymd') . '-' . random_int(10000, 99999);
}
$body = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$route = $shop === 'oc4' ? 'extension/neocarts/module/neocarts.webhook' : 'extension/module/neocarts/webhook';
$ch = curl_init("{$hosts[$shop]}/index.php?route=$route");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Neocarts-Event: ' . $envelope['event'],
        'X-Neocarts-Delivery: ' . $envelope['id'],
        'X-Neocarts-Signature: ' . \Neocarts\Signature::sign($body, $secret),
    ],
]);
$answer = curl_exec($ch);
echo curl_getinfo($ch, CURLINFO_HTTP_CODE) . ' ' . $answer . "\n";
echo "delivery {$envelope['id']}" . (isset($envelope['data']['number']) ? ", order {$envelope['data']['number']}" : '') . "\n";
