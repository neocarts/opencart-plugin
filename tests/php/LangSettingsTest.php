<?php
use Neocarts\Lang;
use Neocarts\Settings;

function test_every_language_has_every_key()
{
    $base = array_keys(require __DIR__ . '/../../src/shared/lang/en-gb.php');
    sort($base);
    foreach (Lang::available() as $code) {
        $keys = array_keys(require __DIR__ . "/../../src/shared/lang/$code.php");
        sort($keys);
        eq([], array_values(array_diff($base, $keys)), "$code is missing");
        eq([], array_values(array_diff($keys, $base)), "$code has extra");
    }
}

function test_lang_falls_back_to_english()
{
    eq('Neocarts — subscriptions', Lang::load('de-de')['heading_title']);
    eq('Neocarts — підписки', Lang::load('uk-ua')['heading_title']);
}

function test_settings_resolve()
{
    $s = Settings::resolve(3, ['status' => '1', 'card_position' => 'sideways', 'shop_id' => ' x ', 'nonsense' => 1, 'subscriptions_mirror' => 1]);
    eq(1, $s['status']);
    eq('append', $s['card_position'], 'invalid position falls back to the default');
    eq(false, isset($s['subscriptions_mirror']), 'removed settings are dropped');
    eq('x', $s['shop_id']);
    eq(false, isset($s['nonsense']));
    eq('.description', Settings::defaults(4)['card_anchor']);
    eq('.caption', Settings::defaults(3)['card_anchor']);
    eq(false, isset(Settings::storefront($s)['api_key']));
}

function test_cards_on_routes()
{
    $s = Settings::resolve(4, ['card_on_search' => 0, 'card_on_other' => 0]);
    eq(true, Settings::cardsOn($s, 'product/category'));
    eq(false, Settings::cardsOn($s, 'product/search'));
    eq(true, Settings::cardsOn($s, 'product/manufacturer.info'));
    eq(true, Settings::cardsOn($s, 'product/manufacturer|info'));
    eq(false, Settings::cardsOn($s, 'information/information'));
}

function test_schema()
{
    eq(3, count(Neocarts\Schema::migrate('oc_', 0)));
    eq([], Neocarts\Schema::migrate('oc_', Neocarts\Schema::VERSION));
    eq(3, count(Neocarts\Schema::drop('oc_')));
}

function test_theme_presets_fill_only_untouched_defaults()
{
    $d = Settings::defaults(3);
    $s = Settings::resolve(3, ['card_anchor' => $d['card_anchor'], 'card_selector' => '.my-card'], 'ff_frame');
    eq('.product-controls', $s['card_anchor'], 'saved equal to the default: the preset wins');
    eq('.my-card', $s['card_selector'], 'the merchant\'s own value wins');
    eq('#ff_cart', $s['minicart_selector'], 'missing: the preset');
    eq('ff_frame', Settings::preset('framefree'));
    eq(null, Settings::preset('default'));
    eq($d['card_anchor'], Settings::resolve(3, [], 'default')['card_anchor'], 'the default theme has no preset');
    foreach (Settings::PRESETS as $name => $preset) {
        eq([], array_values(array_diff(array_keys($preset), array_keys($d))), "preset $name uses unknown keys");
    }
}
