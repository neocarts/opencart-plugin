<?php
namespace Neocarts;

/** The `neocarts_webhook` table of each adapter: idempotency by delivery id + the admin log. */
interface WebhookStore
{
    /** false when this delivery id was already processed successfully (a retry or a resend). */
    public function claim(string $deliveryId, string $event, string $body): bool;

    /** @param string $status success | ignored | failed */
    public function finish(string $deliveryId, string $status, ?string $error = null);
}
