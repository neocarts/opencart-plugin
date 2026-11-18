<?php
use Neocarts\Locale;
use Neocarts\Money;
use Neocarts\Signature;

function test_money_minor_rounds_float_noise()
{
    eq(1999, Money::minor(19.99));
    eq(1999, Money::minor('19.9900'));
    eq(4040, Money::minor(40.4));
    eq(100, Money::minor(1.005 - 0.005));
    eq(249000, Money::minor(2490));
    eq(24.9, Money::major(2490));
}

function test_locale_maps_oc_codes()
{
    eq('uk', Locale::resolve('uk-ua'));
    eq('uk', Locale::resolve('ua-uk'));
    eq('en', Locale::resolve('en-gb'));
    eq('en', Locale::resolve('de-de'));
    eq('uk', Locale::resolve('ru-ru', ['ru-ru' => 'uk']));
    eq('en', Locale::resolve('pl-pl', ['pl-pl' => 'pl']), 'unsupported override falls back');
}

function test_signature()
{
    $body = '{"id":"1","event":"order.paid"}';
    $header = Signature::sign($body, 'whsec_x', 1000);
    eq(true, Signature::verify($header, $body, 'whsec_x', 1100));
    eq(false, Signature::verify($header, $body, 'whsec_y', 1100), 'other secret');
    eq(false, Signature::verify($header, $body . ' ', 'whsec_x', 1100), 'changed body');
    eq(false, Signature::verify($header, $body, 'whsec_x', 1301), 'too old');
    eq(true, Signature::verify($header, $body, 'whsec_x', 1300), 'edge of the window');
    eq(false, Signature::verify('t=1000', $body, 'whsec_x', 1000), 'malformed');
    eq(false, Signature::verify($header, $body, '', 1000), 'no secret');
}
