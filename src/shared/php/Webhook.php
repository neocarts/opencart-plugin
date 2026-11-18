<?php
namespace Neocarts;

/**
 * One incoming delivery: verify → claim → dispatch. Returns the HTTP status to answer.
 * Unknown events are answered 2xx (new ones may appear). docs/ai/webhooks.md
 */
final class Webhook
{
    /** @var string|null why the last call was rejected, for the shop's log */
    public static $lastError;

    public static function handle(string $body, string $signature, string $secret, WebhookStore $store, WebhookSink $sink, ?int $now = null): int
    {
        self::$lastError = null;
        if (!Signature::verify($signature, $body, $secret, $now)) {
            self::$lastError = $secret === '' ? 'webhook secret is not set' : 'bad signature';

            return 401;
        }
        $envelope = json_decode($body, true);
        if (!is_array($envelope) || !isset($envelope['id'], $envelope['event']) || !isset($envelope['data']) || !is_array($envelope['data'])) {
            self::$lastError = 'malformed body';

            return 400;
        }

        $id = (string) $envelope['id'];
        $event = (string) $envelope['event'];
        if (!$store->claim($id, $event, $body)) {
            return 200;
        }

        try {
            $handled = self::dispatch($event, $envelope['data'], $sink);
        } catch (\Throwable $e) {
            // the message goes back in the response body: the merchant reads it in the Neocarts
            // panel's webhook log («Shop → Webhooks»)
            self::$lastError = $e->getMessage();
            $store->finish($id, 'failed', $e->getMessage());

            return 500;
        }
        $store->finish($id, $handled ? 'success' : 'ignored');

        return 200;
    }

    private static function dispatch(string $event, array $data, WebhookSink $sink): bool
    {
        switch ($event) {
            case 'order.paid':
                return $sink->orderPaid($data);
            case 'order.cancelled':
                return $sink->orderCancelled($data);
            case 'payment.refunded':
                return $sink->paymentRefunded($data);
        }

        return false;
    }
}
