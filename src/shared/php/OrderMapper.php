<?php
namespace Neocarts;

/**
 * order.paid snapshot → a neutral description of an ordinary OpenCart order. Adapters turn it into
 * their version's addOrder() array (field names differ between 2.3/3.0 and 4.x). docs/ai/webhooks.md
 *
 * Amounts are in the shop's currency units (minor / 100): nothing is converted.
 */
final class OrderMapper
{
    const ADMIN_URL = Links::ADMIN_URL;

    /** @param array $t Lang strings (see src/shared/lang) */
    public static function map(array $order, array $t): array
    {
        $contact = isset($order['contact']) && is_array($order['contact']) ? $order['contact'] : [];
        $delivery = isset($order['delivery']) && is_array($order['delivery']) ? $order['delivery'] : [];
        $recipient = isset($delivery['recipient']) && is_array($delivery['recipient']) ? $delivery['recipient'] : $contact;
        $payment = isset($order['payment']) && is_array($order['payment']) ? $order['payment'] : null;
        $renewal = isset($order['source']) && $order['source'] === 'subscription';

        $products = [];
        foreach (isset($order['items']) ? $order['items'] : [] as $item) {
            $ref = Items::parse((string) $item['external_id']);
            $quantity = max(1, (int) $item['quantity']);
            $total = isset($item['total']) ? (int) $item['total'] : (int) $item['price'] * $quantity;
            $products[] = [
                'product_id' => $ref ? $ref['product_id'] : 0,
                'option_value_ids' => $ref ? $ref['option_value_ids'] : [],
                'external_id' => (string) $item['external_id'],
                'name' => (string) $item['title'],
                'variant' => isset($item['variant']) ? (string) $item['variant'] : '',
                'quantity' => $quantity,
                // the line after the subscriber discount: OpenCart shows what was actually charged
                'price' => Money::major((int) round($total / $quantity)),
                'total' => Money::major($total),
                'subscription' => isset($item['mode']) && $item['mode'] === 'subscription',
            ];
        }

        $totals = isset($order['totals']) ? $order['totals'] : [];
        $itemsTotal = isset($totals['items_total']) ? (int) $totals['items_total'] : 0;
        $discount = isset($totals['discount_total']) ? (int) $totals['discount_total'] : 0;
        $total = isset($totals['total']) ? (int) $totals['total'] : $itemsTotal - $discount;

        $method = 'Neocarts';
        if ($payment) {
            $method .= ' (' . $payment['provider'] . (!empty($payment['card_mask']) ? ', ' . $payment['card_mask'] : '') . ')';
        }

        $comment = sprintf(
            $t['order_comment'],
            $order['number'],
            $renewal ? $t['order_source_renewal'] : $t['order_source_widget'],
            self::ADMIN_URL
        );
        if (!empty($order['confirmed_manually'])) {
            $comment .= "\n" . $t['order_confirmed_manually'];
        }

        return [
            'neocarts_order_id' => (string) $order['id'],
            'number' => (string) $order['number'],
            'source' => $renewal ? 'subscription' : 'widget',
            'confirmed_manually' => !empty($order['confirmed_manually']),
            'subscription_ids' => isset($order['subscription_ids']) ? array_values($order['subscription_ids']) : [],
            'email' => isset($contact['email']) ? (string) $contact['email'] : '',
            'firstname' => isset($contact['first_name']) ? (string) $contact['first_name'] : '',
            'lastname' => isset($contact['last_name']) ? (string) $contact['last_name'] : '',
            'telephone' => isset($contact['phone']) ? (string) $contact['phone'] : '',
            'shipping' => self::address($delivery, $recipient, $t),
            'shipping_method' => self::shippingMethod($delivery, $t),
            'payment_method' => $method,
            'products' => $products,
            'sub_total' => Money::major($itemsTotal),
            'discount' => Money::major($discount),
            'total' => Money::major($total),
            'comment' => $comment,
        ];
    }

    private static function address(array $delivery, array $recipient, array $t): array
    {
        $data = isset($delivery['data']) && is_array($delivery['data']) ? $delivery['data'] : [];
        $name = function ($key) use ($data) {
            return isset($data[$key]['name']) ? (string) $data[$key]['name'] : (isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : '');
        };

        $line1 = $name('branch') ?: $name('parcel_locker');
        $line2 = '';
        if ($name('street') !== '') {
            $line1 = trim($name('street') . ' ' . $name('house_number'));
            $parts = [];
            if ($name('apartment_number') !== '') {
                $parts[] = sprintf($t['address_apartment'], $name('apartment_number'));
            }
            if ($name('floor_number') !== '') {
                $parts[] = sprintf($t['address_floor'], $name('floor_number'));
            }
            $line2 = implode(', ', $parts);
        }

        return [
            'firstname' => isset($recipient['first_name']) ? (string) $recipient['first_name'] : '',
            'lastname' => isset($recipient['last_name']) ? (string) $recipient['last_name'] : '',
            'telephone' => isset($recipient['phone']) ? (string) $recipient['phone'] : '',
            'city' => $name('city'),
            'address_1' => $line1,
            'address_2' => $line2,
            'postcode' => $name('zip_code'),
        ];
    }

    private static function shippingMethod(array $delivery, array $t): string
    {
        $provider = isset($delivery['provider']) ? (string) $delivery['provider'] : '';
        $type = isset($delivery['type']) ? (string) $delivery['type'] : '';
        $key = 'delivery_' . str_replace('-', '_', $provider);
        $label = isset($t[$key]) ? $t[$key] : $provider;
        $typeKey = 'delivery_type_' . str_replace('-', '_', $type);

        return trim($label . (isset($t[$typeKey]) ? ' — ' . $t[$typeKey] : '')) . ' (Neocarts)';
    }
}
