<?php
/*
 * Sandbox first run: adds uk-ua through OpenCart's own admin form (it copies every description
 * from en-gb to the new language) and makes it the default of the storefront and the admin.
 * Runs inside the web container, the script on stdin (make sandbox). docs/ai/docker.md
 */
const ROOT = '/var/www/html/';
require ROOT . 'config.php';
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int) DB_PORT);
$db->set_charset('utf8mb4');
$P = DB_PREFIX;

if (!$db->query("SELECT 1 FROM {$P}language WHERE code = 'uk-ua'")->num_rows) {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', // cookies in memory
        CURLOPT_USERAGENT => 'sandbox-setup']); // 4.1 stores the login's user agent, null breaks it
    $http = function ($route, $query, $post) use ($ch) {
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/admin/index.php?' . http_build_query(['route' => $route] + $query));
        if ($post === null) curl_setopt($ch, CURLOPT_HTTPGET, true);
        else curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        return (string) curl_exec($ch);
    };
    // 4.1: the login form carries a one-time login_token from the session
    preg_match('/login_token=([A-Za-z0-9]+)/', $http('common/login', [], null), $m);
    $out = $http('common/login.login', ['login_token' => $m[1] ?? ''], ['username' => 'admin', 'password' => 'admin']);
    if (!preg_match('/user_token=([A-Za-z0-9]+)/', $out, $m)) fail("admin login failed");
    $out = $http('localisation/language.save', ['user_token' => $m[1]], [
        'language_id' => 0, 'name' => 'Українська', 'code' => 'uk-ua', 'locale' => 'uk-ua,uk_UA.UTF-8,uk',
        'extension' => '', 'sort_order' => 0, 'status' => 1,
    ]);
    if (strpos($out, '"success"') === false) fail("adding uk-ua failed: " . substr($out, -500));
}
// the store's meta title is per language and the form above does not copy it (empty <title>)
$id = $db->query("SELECT language_id FROM {$P}language WHERE code = 'uk-ua'")->fetch_row()[0];
$desc = json_decode($db->query("SELECT value FROM {$P}setting WHERE store_id = 0 AND `key` = 'config_description'")->fetch_row()[0], true);
if (!isset($desc[$id])) {
    $desc[$id] = reset($desc);
    $db->query("UPDATE {$P}setting SET value = '" . $db->real_escape_string(json_encode($desc, JSON_UNESCAPED_UNICODE))
        . "' WHERE store_id = 0 AND `key` = 'config_description'");
}
foreach (['config_language_catalog', 'config_language_admin'] as $key) {
    $db->query("UPDATE {$P}setting SET value = 'uk-ua' WHERE store_id = 0 AND code = 'config' AND `key` = '$key'");
}
foreach (glob(DIR_CACHE . 'cache.*') ?: [] as $f) @unlink($f);
echo "[sandbox] uk-ua is the default language (storefront and admin), English stays installed\n";

function fail($msg) { fwrite(STDERR, "[sandbox] $msg\n"); exit(1); }
