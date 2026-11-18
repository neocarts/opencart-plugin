<?php
namespace Neocarts;

/**
 * What a shop does with each event; every adapter implements it on top of OpenCart's own models.
 * Only what the shop itself must know: the order to fulfil and its status. Subscriptions, failed
 * charges and the rest live in the Neocarts panel (docs/ai/decisions.md «Minimal admin»), so those
 * events are acknowledged and logged as `ignored`.
 * A method that throws makes the delivery fail with 500, so Neocarts retries it.
 * Return false when the setting for that event is off (logged as `ignored`).
 */
interface WebhookSink
{
    /** order.paid — data is the order snapshot + payment + confirmed_manually */
    public function orderPaid(array $data): bool;

    public function orderCancelled(array $data): bool;

    /** payment.refunded — data is the payment snapshot */
    public function paymentRefunded(array $data): bool;
}
