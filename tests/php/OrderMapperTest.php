<?php
use Neocarts\Lang;
use Neocarts\OrderMapper;

function paid_order()
{
    $json = file_get_contents(__DIR__ . '/../fixtures/order.paid.json');

    return json_decode($json, true)['data'];
}

function test_order_mapper()
{
    $o = OrderMapper::map(paid_order(), Lang::load('uk-ua'));
    eq('261004-48213', $o['number']);
    eq('widget', $o['source']);
    eq('olena@example.com', $o['email']);
    eq('Київ', $o['shipping']['city']);
    eq('Відділення №1: вул. Пирогівський шлях, 135', $o['shipping']['address_1']);
    eq('Нова Пошта — відділення (Neocarts)', $o['shipping_method']);
    eq('Neocarts (plata-by-mono, **** 4242)', $o['payment_method']);
    eq(42, $o['products'][0]['product_id']);
    eq([1, 5, 8], $o["products"][0]["option_value_ids"]);
    eq(2315.7, $o['products'][0]['price'], 'the discounted unit price');
    eq(2490.0, $o['sub_total']);
    eq(174.3, $o['discount']);
    eq(2315.7, $o['total']);
    eq('Neocarts №261004-48213 · оформлення підписки · https://shop.neocarts.com/', $o['comment']);
}

function test_order_mapper_renewal_and_courier()
{
    $order = paid_order();
    $order['source'] = 'subscription';
    $order['payment'] = null;
    $order['confirmed_manually'] = true;
    $order['delivery'] = ['provider' => 'nova-poshta', 'type' => 'address', 'data' => [
        'city' => ['name' => 'Львів'], 'street' => ['name' => 'вул. Городоцька'], 'house_number' => '12', 'apartment_number' => '5',
    ], 'recipient' => ['first_name' => 'І', 'last_name' => 'П', 'phone' => '+380']];
    $o = OrderMapper::map($order, Lang::load('en-gb'));
    eq('subscription', $o['source']);
    eq('Neocarts', $o['payment_method']);
    eq('вул. Городоцька 12', $o['shipping']['address_1']);
    eq('apt. 5', $o['shipping']['address_2']);
    eq('Nova Poshta — courier (Neocarts)', $o['shipping_method']);
    eq(true, strpos($o['comment'], 'subscription renewal') !== false);
    eq(true, strpos($o['comment'], 'confirmed by hand') !== false);
}
