<?php
use Neocarts\ApiError;
use Neocarts\Items;

function line(array $over = [])
{
    return array_merge([
        'product_id' => 42, 'name' => 'Brit Care &amp; Co', 'quantity' => 1, 'unit_price' => 2490.0,
        'image_url' => 'http://s/i.jpg', 'product_url' => 'http://s/p', 'option' => [],
    ], $over);
}

function test_external_id_is_stable_and_reversible()
{
    $a = [['product_option_value_id' => 17, 'name' => 'Size', 'value' => 'L'], ['product_option_value_id' => 5, 'name' => 'Colour', 'value' => 'Blue']];
    $b = array_reverse($a);
    $a[] = ['product_option_value_id' => '', 'name' => 'Engraving', 'value' => 'Hi'];
    eq('42-5.17', Items::externalId(42, $a));
    eq('42-5.17', Items::externalId(42, $b));
    eq('42', Items::externalId(42, []));
    eq(['product_id' => 42, 'option_value_ids' => [5, 17]], Items::parse('42-5.17'));
    eq(['product_id' => 42, 'option_value_ids' => []], Items::parse('42'));
    eq(null, Items::parse('SKU-1'));
    eq('Size: L, Colour: Blue, Engraving: Hi', Items::variant($a));
    eq(null, Items::variant([]));
}

function test_build_takes_prices_from_the_cart_and_modes_from_the_selection()
{
    $items = Items::build(
        [line(), line(['product_id' => 7, 'unit_price' => 19.99, 'quantity' => 2])],
        ['42' => ['mode' => 'subscription', 'interval_value' => 4, 'interval_unit' => 'week', 'price' => 1]]
    );
    eq('Brit Care & Co', $items[0]['title']);
    eq(249000, $items[0]['price'], 'the browser price is ignored');
    eq('subscription', $items[0]['mode']);
    eq(4, $items[0]['interval_value']);
    eq('one_time', $items[1]['mode']);
    eq(1999, $items[1]['price']);
    eq(false, isset($items[1]['interval_value']));
}

function test_disabled_by_product_or_option_value()
{
    eq(true, Items::disabled('42', ['42']));
    eq(true, Items::disabled('42-1.5.8', ['42']), 'the product switch covers every variant');
    eq(true, Items::disabled('42-1.5.8', ['42-5']), 'one option value switch');
    eq(false, Items::disabled('42-1.6.8', ['42-5']));
    eq(false, Items::disabled('42', ['42-5']), 'the base product stays on');
    eq(false, Items::disabled('420', ['42']));
    eq(false, Items::disabled('SKU-1', ['SKU-1']), 'foreign ids are never ours');
}

function test_a_line_that_cannot_be_a_subscription_goes_one_time()
{
    $sub = ['mode' => 'subscription', 'interval_value' => 4, 'interval_unit' => 'week'];
    $items = Items::build([line(), line(['product_id' => 7, 'subscribable' => false])], ['42' => $sub, '7' => $sub]);
    eq('subscription', $items[0]['mode']);
    eq('one_time', $items[1]['mode']);
    throws(function () use ($sub) { Items::build([line(['subscribable' => false])], ['42' => $sub]); }, 'error_no_subscription');
}

function test_build_rejects_what_the_shop_api_would()
{
    $sub = ['42' => ['mode' => 'subscription', 'interval_value' => 4, 'interval_unit' => 'week']];
    throws(function () { Items::build([], []); }, 'error_cart_empty');
    throws(function () { Items::build([line()], []); }, 'error_no_subscription');
    throws(function () use ($sub) { Items::build([line(['quantity' => 10])], $sub); }, 'error_quantity');
    throws(function () use ($sub) { Items::build([line(['unit_price' => 0])], $sub); }, 'error_price');
    throws(function () { Items::build([line()], ['42' => ['mode' => 'subscription', 'interval_value' => '4', 'interval_unit' => 'week']]); }, 'error_interval');
    throws(function () { Items::build([line()], ['42' => ['mode' => 'subscription', 'interval_value' => 4, 'interval_unit' => 'month']]); }, 'error_interval');
    throws(function () use ($sub) { Items::build(array_fill(0, 51, line()), $sub); }, 'error_too_many_lines');
    $e = throws(function () use ($sub) { Items::build([line(['quantity' => 10])], $sub); });
    eq(422, $e->status);
    eq(['name' => 'Brit Care &amp; Co'], $e->errors);
}
