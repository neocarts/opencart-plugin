<?php
use Neocarts\Signature;
use Neocarts\Webhook;
use Neocarts\WebhookSink;
use Neocarts\WebhookStore;

class MemoryStore implements WebhookStore
{
    public $rows = [];

    public function claim(string $deliveryId, string $event, string $body): bool
    {
        if (isset($this->rows[$deliveryId]) && $this->rows[$deliveryId] !== 'failed') {
            return false;
        }
        $this->rows[$deliveryId] = 'pending';

        return true;
    }

    public function finish(string $deliveryId, string $status, ?string $error = null)
    {
        $this->rows[$deliveryId] = $status;
    }
}

class RecordingSink implements WebhookSink
{
    public $calls = [];
    public $fail = false;

    public function orderPaid(array $data): bool { return $this->rec('order.paid', $data); }
    public function orderCancelled(array $data): bool { return $this->rec('order.cancelled', $data); }
    public function paymentRefunded(array $data): bool { return $this->rec('payment.refunded', $data); }

    private function rec($event, $data)
    {
        if ($this->fail) {
            throw new RuntimeException('db down');
        }
        $this->calls[] = $event;

        return true;
    }
}

function delivery($id, $event)
{
    return json_encode(['id' => $id, 'event' => $event, 'created_at' => '2026-10-04T09:12:30+00:00', 'shop_id' => 's', 'data' => ['id' => 'x']]);
}

function test_webhook_dispatch_and_idempotency()
{
    $store = new MemoryStore();
    $sink = new RecordingSink();
    $body = delivery('d1', 'order.paid');
    eq(200, Webhook::handle($body, Signature::sign($body, 'whsec'), 'whsec', $store, $sink));
    eq(200, Webhook::handle($body, Signature::sign($body, 'whsec'), 'whsec', $store, $sink), 'a retry');
    eq(['order.paid'], $sink->calls, 'processed once');
    $body = delivery('d2', 'subscription.paused');
    eq(200, Webhook::handle($body, Signature::sign($body, 'whsec'), 'whsec', $store, $sink));
    eq('ignored', $store->rows['d2'], 'subscriptions live in the Neocarts panel');
    $body = delivery('d3', 'something.new');
    eq(200, Webhook::handle($body, Signature::sign($body, 'whsec'), 'whsec', $store, $sink));
    eq('ignored', $store->rows['d3']);
}

function test_webhook_rejects_and_retries()
{
    $store = new MemoryStore();
    $sink = new RecordingSink();
    $body = delivery('d1', 'order.paid');
    eq(401, Webhook::handle($body, Signature::sign($body, 'other'), 'whsec', $store, $sink));
    eq('bad signature', Webhook::$lastError);
    eq(401, Webhook::handle($body, Signature::sign($body, 'whsec'), '', $store, $sink));
    eq(400, Webhook::handle('[]', Signature::sign('[]', 'whsec'), 'whsec', $store, $sink));
    $sink->fail = true;
    eq(500, Webhook::handle($body, Signature::sign($body, 'whsec'), 'whsec', $store, $sink));
    eq('failed', $store->rows['d1']);
    $sink->fail = false;
    eq(200, Webhook::handle($body, Signature::sign($body, 'whsec'), 'whsec', $store, $sink), 'the retry of a failed one runs');
    eq(['order.paid'], $sink->calls);
}
