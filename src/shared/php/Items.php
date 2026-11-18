<?php
namespace Neocarts;

/**
 * OpenCart cart lines → Shop API `items`. The browser only says which lines are subscriptions;
 * titles, prices and quantities always come from the shop's own cart. docs/ai/items.md
 *
 * A cart line, as every adapter normalises it:
 *   product_id int, name string, quantity int, unit_price float (shop currency, tax applied),
 *   image_url string (absolute), product_url string (absolute),
 *   option list<{product_option_value_id, name, value}> — OpenCart's own cart option shape,
 *   subscribable bool (optional, default true) — false: switched off for the product or out of stock;
 *   such a line always goes as one_time, whatever the browser says
 */
final class Items
{
    const MAX_LINES = 50;
    const MAX_QUANTITY = 9;
    const MAX_PRICE = 999999999;
    const MAX_INTERVAL = 365;
    const UNITS = ['day', 'week'];

    /**
     * `42` for a product without choice options, `42-17.5` with them: the sorted
     * product_option_value_id of every select/radio/checkbox choice. Reversible — see parse().
     */
    public static function externalId(int $productId, array $options): string
    {
        $ids = [];
        foreach ($options as $option) {
            $id = isset($option['product_option_value_id']) ? (int) $option['product_option_value_id'] : 0;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        sort($ids);

        return $ids ? $productId . '-' . implode('.', $ids) : (string) $productId;
    }

    /** @return array{product_id:int, option_value_ids:int[]}|null */
    public static function parse(string $externalId)
    {
        if (!preg_match('/^(\d+)(?:-(\d+(?:\.\d+)*))?$/', $externalId, $m)) {
            return null;
        }

        return [
            'product_id' => (int) $m[1],
            'option_value_ids' => isset($m[2]) ? array_map('intval', explode('.', $m[2])) : [],
        ];
    }

    /**
     * Is subscription switched off for this product or variant? $off holds the merchant's switches
     * (`neocarts_product.external_id`): `42` = the whole product, `42-17` = any variant of 42 with
     * option value 17. One switch per option value instead of one per combination.
     */
    public static function disabled(string $externalId, array $off): bool
    {
        $ref = self::parse($externalId);
        if (!$ref) {
            return false;
        }
        $off = array_flip($off);
        if (isset($off[(string) $ref['product_id']])) {
            return true;
        }
        foreach ($ref['option_value_ids'] as $id) {
            if (isset($off[$ref['product_id'] . '-' . $id])) {
                return true;
            }
        }

        return false;
    }

    /** "Size: L, Colour: blue" — every option, text ones included; null without options. */
    public static function variant(array $options)
    {
        $parts = [];
        foreach ($options as $option) {
            if (isset($option['value']) && (string) $option['value'] !== '') {
                $parts[] = $option['name'] . ': ' . $option['value'];
            }
        }

        return $parts ? self::cut(implode(', ', $parts)) : null;
    }

    /**
     * @param array $lines     normalised cart lines (see the class docblock)
     * @param array $selection external_id => {mode, interval_value, interval_unit} from getOrderItems()
     * @throws ApiError 422 with a Lang key as the message
     */
    public static function build(array $lines, array $selection): array
    {
        if (!$lines) {
            throw new ApiError(422, 'error_cart_empty');
        }
        if (count($lines) > self::MAX_LINES) {
            throw new ApiError(422, 'error_too_many_lines');
        }

        $items = [];
        $subscriptions = 0;
        foreach ($lines as $line) {
            $id = self::externalId((int) $line['product_id'], isset($line['option']) ? $line['option'] : []);
            $price = Money::minor($line['unit_price']);
            $quantity = (int) $line['quantity'];
            if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                throw new ApiError(422, 'error_quantity', ['name' => $line['name']]);
            }
            if ($price < 1 || $price > self::MAX_PRICE) {
                throw new ApiError(422, 'error_price', ['name' => $line['name']]);
            }

            $item = [
                'external_id' => $id,
                'title' => self::cut($line['name']),
                'variant' => self::variant(isset($line['option']) ? $line['option'] : []),
                'image_url' => $line['image_url'],
                'product_url' => $line['product_url'],
                'price' => $price,
                'quantity' => $quantity,
                'mode' => 'one_time',
            ];

            $subscribable = !isset($line['subscribable']) || $line['subscribable'];
            $chosen = $subscribable && isset($selection[$id]) && is_array($selection[$id]) ? $selection[$id] : [];
            if (isset($chosen['mode']) && $chosen['mode'] === 'subscription') {
                $value = isset($chosen['interval_value']) ? $chosen['interval_value'] : null;
                $unit = isset($chosen['interval_unit']) ? $chosen['interval_unit'] : null;
                if (!is_int($value) || $value < 1 || $value > self::MAX_INTERVAL || !in_array($unit, self::UNITS, true)) {
                    throw new ApiError(422, 'error_interval', ['name' => $line['name']]);
                }
                $item['mode'] = 'subscription';
                $item['interval_value'] = $value;
                $item['interval_unit'] = $unit;
                $subscriptions++;
            }
            $items[] = $item;
        }

        if (!$subscriptions) {
            throw new ApiError(422, 'error_no_subscription');
        }

        return $items;
    }

    /** The product card for PUT /v1/shop/products/{external_id}: no price, ever. */
    public static function card(string $title, $variant, string $imageUrl, string $productUrl): array
    {
        return [
            'title' => self::cut($title),
            'variant' => $variant,
            'image_url' => $imageUrl,
            'product_url' => $productUrl,
        ];
    }

    private static function cut(string $text): string
    {
        $text = trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
        preg_match('/^.{0,255}/su', $text, $m);

        return $m[0];
    }
}
