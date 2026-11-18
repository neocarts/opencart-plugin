<?php
/*
 * Third-party themes on the demo stand. Runs inside one shop container, the script on stdin:
 *   docker compose exec -T oc3 php -- install all        < docker/themes/themes.php
 *   docker compose exec -T oc3 php -- activate ff_frame  < docker/themes/themes.php
 *   docker compose exec -T oc3 php -- status             < docker/themes/themes.php
 * Archives are pinned (URL + sha256, docs/ai/themes.md), downloaded here and only unpacked and
 * copied: nothing from them is run by this script. docs/ai/docker.md «Third-party themes».
 *
 * Installing never changes the active theme. Whatever a theme changes globally stays off
 * until it is activated: its OCMOD rows (status 0), the events its installer added
 * (status 0), and files that would overwrite an existing shop file (kept aside, swapped in on
 * activation, the original put back on deactivation).
 * PHP 7.1 syntax: the 2.3 shop runs it.
 */

const ROOT = '/var/www/html/';
const STATE = ROOT . '.neocarts-themes/';
const MATERIALIZE_URL = 'https://codeload.github.com/trydalcoholic/opencart-materialize/zip/845baaa8bacbce36f3b95408719255173efecae3';
const MATERIALIZE_SHA = '16f53d25876f724824a09e5e4070f347414bc0ff7778890035f7109e58f1187b';

/*
 * name => [branch, url, sha256, root inside the zip ('*' = the zip's single top folder),
 *          code (config_theme / extension code), template directory, ocmod files, sql file,
 *          admin: run OpenCart's own «install theme» action, skip: path prefixes not copied]
 */
$THEMES = [
    2 => [
        'materialize' => [
            'url' => MATERIALIZE_URL, 'sha' => MATERIALIZE_SHA,
            'root' => '*/archive/opencart_2.3.x/upload/', 'code' => 'materialize', 'dir' => 'materialize',
            'ocmod' => ['*/archive/opencart_2.3.x/install/ocmod/materialize_theme.ocmod.xml',
                        '*/archive/opencart_2.3.x/install/ocmod/language/materialize_english.ocmod.xml'],
            'sql' => '*/archive/opencart_2.3.x/install/sql/sql_install_materialize_theme.ocmod.sql',
            'admin' => true,
            // its header controller getimagesize()s the logo by URL, which the container cannot reach
            'quiet' => true,
        ],
        'xezpro' => [
            'url' => 'https://github.com/givanz/xezpro/releases/download/1.0/xezpro.zip',
            'sha' => '7ef6faf81a01896df6e0e7f46c0608591bfa55d40600059096022f80d7cafec3',
            'root' => '', 'code' => 'xezpro', 'dir' => 'xezpro', 'skip' => ['docs/'],
        ],
    ],
    3 => [
        'ff_frame' => [
            'url' => 'https://github.com/stickpro/frame_free/releases/download/1.0.0/framefree_1.0.0.ocmod.zip',
            'sha' => '8ff53db3978cfcb5a0691d0fdc5dbb1294beeae79058e7e23ed0e40db9cf4998',
            'root' => 'upload/', 'code' => 'framefree', 'dir' => 'ff_frame', 'ocmod' => ['install.xml'],
            'admin' => true,
            // PHP 8 warnings from its own controllers (unset array keys in ff_menu/ff_contacts/stickers)
            'quiet' => true,
        ],
        // Materialize 3.0 (source/opencart_3.0.x) is left out: its OCMOD, written for 3.0.2, produces
        // PHP parse errors on 3.0.5.1 (catalog/model/catalog/product.php, module/slideshow.php)
        'luxury' => [
            'url' => 'https://codeload.github.com/DesignCart/theme_luxury/zip/ded47f80c36782bb5ced2a94dbcb5ceb99068475',
            'sha' => '2d46e8139ef856e54cd0b0073c7473b6ae9dc695e804b31df055c1719d113b20',
            'inner' => '*/theme.zip', 'root' => '', 'base' => 'catalog/view/theme/', 'code' => 'luxury', 'dir' => 'luxury',
        ],
    ],
    4 => [
        'dc_toys' => [
            'url' => 'https://codeload.github.com/DesignCart/oc-dc-toys-4/zip/8157fc8e21c64b265a74e441eaf761b3d326bf5b',
            'sha' => '118f270048acd848b062398f727085fe11252730740f7f173c9f0b6a2d21bab9',
            'root' => '*/upload/', 'code' => 'dc_toys', 'name' => 'Design Cart Toys', 'version' => '8157fc8',
            'startup' => ['catalog/extension/dc_toys/startup/dc_toys', 0],
            'images' => 'catalog/view/image/catalog/dc_toys/', // its installer copies these to image/catalog/dc_toys/
        ],
        'befree' => [
            'url' => 'https://github.com/Intense-Finance/BeFree/releases/download/v1.2.5/befree.ocmod.zip',
            'sha' => '9a80bc7664c56fe0fc34711570b1bb7901e5d8f5f17194286220b2fda0626b29',
            'root' => '', 'code' => 'befree', 'name' => 'BeFree OpenCart Theme', 'version' => '1.2.5',
            'startup' => ['catalog/extension/befree/startup/befree', 2],
        ],
    ],
];

preg_match("/define\('VERSION', '([\d.]+)'/", file_get_contents(ROOT . 'index.php'), $m);
$VERSION = $m[1];
$BRANCH = (int) $VERSION[0];
require ROOT . 'config.php';
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int) DB_PORT);
$db->set_charset('utf8mb4');
$db->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'"); // as OpenCart itself
$P = DB_PREFIX;

$args = array_slice($argv, 1);
$cmd = isset($args[0]) ? $args[0] : 'status';
$name = isset($args[1]) ? $args[1] : '';
$themes = $THEMES[$BRANCH];

if ($cmd === 'install') {
    foreach ($name === 'all' || $name === '' ? array_keys($themes) : [$name] as $n) {
        if (!isset($themes[$n])) fail("unknown theme '$n' for $VERSION: " . implode(', ', array_keys($themes)));
        install($n, $themes[$n]);
    }
    status();
} elseif ($cmd === 'activate') {
    activate($name === '' ? 'default' : $name);
} elseif ($cmd === 'status') {
    status();
} else {
    fail("usage: install <theme|all> | activate <theme|default> | status");
}

// ---- install --------------------------------------------------------------------------------

function install($n, array $t)
{
    global $BRANCH, $db, $P;
    $state = STATE . $t['code'] . '/';
    @mkdir($state . 'override', 0755, true);
    @mkdir(STATE . '_core', 0755, true);

    $zip = fetch($t);
    if (!empty($t['inner'])) { // the theme is a zip inside the archive
        $tmp = tempnam(sys_get_temp_dir(), 'nc');
        file_put_contents($tmp, member($zip, $t['inner']));
        $zip->close();
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) fail("bad inner zip {$t['inner']}");
    }
    $files = unpack_files($zip, $t);
    $base = $BRANCH >= 4 ? 'extension/' . $t['code'] . '/' : (isset($t['base']) ? $t['base'] : '');
    $ours = is_file($state . 'files.list') ? array_flip(file($state . 'files.list', FILE_IGNORE_NEW_LINES)) : [];
    $list = [];
    $overrides = 0;
    $twig3 = twig3();
    foreach ($files as $rel => $content) {
        if ($twig3 && substr($rel, -5) === '.twig') $content = twig3_for_if($content);
        // Xezpro builds <base> from HTTP_HOST + the port again («localhost:80828082» on the stand):
        // a theme bug on non-80 ports; the stock $base is right
        if ($t['code'] === 'xezpro' && substr($rel, -17) === 'common/header.tpl') {
            $content = preg_replace('#<base href="<\?php.*?\?>" />#s', '<base href="<?php echo $base; ?>" />', $content);
        }
        $path = $base . $rel;
        $dest = ROOT . $path;
        $list[] = $path;
        $aside = is_file($state . 'override/' . $path);
        if ($aside || ((file_exists($dest) || is_link($dest)) && !isset($ours[$path]))) {
            if (!$aside && !is_link($dest) && file_get_contents($dest) === $content) continue;
            // an existing shop file: keep the theme's copy aside, swap it in on activation only
            put($state . 'override/' . $path, $content);
            if (!file_exists(STATE . '_core/' . $path)) put(STATE . '_core/' . $path, file_get_contents($dest));
            $overrides++;
            continue;
        }
        put($dest, $content);
    }
    file_put_contents($state . 'files.list', implode("\n", $list) . "\n");

    $sql = !empty($t['sql']) && !is_file($state . 'sql.done');
    if ($sql) {
        $text = str_replace('`oc_', '`' . $P, member($zip, $t['sql']));
        foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', preg_replace('#/\*.*?\*/#s', '', $text)))) as $q) {
            $db->query($q); // ponytail: errors ignored, the dump re-adds columns on a rerun; it runs once (sql.done)
        }
        touch($state . 'sql.done');
    }

    $mods = [];
    foreach (isset($t['ocmod']) ? $t['ocmod'] : [] as $xmlPath) {
        $mods[] = add_ocmod(member($zip, $xmlPath));
    }
    file_put_contents($state . 'ocmod.list', implode("\n", $mods) . ($mods ? "\n" : ''));

    if ($BRANCH >= 4) {
        register4($t, $list);
    } else {
        register23($t, $state);
    }
    $zip->close();
    echo sprintf("[themes] %s: %d files%s%s\n", $n, count($list),
        $overrides ? ", $overrides overriding shop files (swapped in on activation)" : '', $sql ? ', sql applied' : '');
}

function register23(array $t, $state)
{
    global $BRANCH, $db, $P;
    $code = $t['code'];
    $prefix = $BRANCH === 2 ? $code : 'theme_' . $code;
    if (!row("SELECT 1 FROM {$P}extension WHERE type = 'theme' AND code = '" . esc($code) . "'")) {
        $before = column("SELECT DISTINCT code FROM {$P}event");
        if (!empty($t['admin'])) {
            // OpenCart's own «Extensions > Themes > Install»: the extension row, permissions, and the
            // theme's install() (Materialize 3.0 creates its tables and events there)
            $admin = admin();
            $admin->get('extension/extension/theme/install', ['extension' => $code]);
        }
        if (!row("SELECT 1 FROM {$P}extension WHERE type = 'theme' AND code = '" . esc($code) . "'")) {
            $db->query("INSERT INTO {$P}extension SET type = 'theme', code = '" . esc($code) . "'");
        }
        $added = array_values(array_diff(column("SELECT DISTINCT code FROM {$P}event"), $before));
        file_put_contents($state . 'events.list', implode("\n", $added) . ($added ? "\n" : ''));
        if ($added) $db->query("UPDATE {$P}event SET status = 0 WHERE code IN ('" . implode("','", array_map('esc', $added)) . "')");
    }
    if (!empty($t['admin']) && !is_file($state . 'form.done')) {
        // press «Save» on the theme's own settings form once: its defaults become settings
        // (Frame free reads its sticker/menu options without a fallback)
        $admin = admin();
        $html = $admin->get('extension/theme/' . $code, ['store_id' => 0]);
        $post = form_fields($html);
        if ($post) $admin->request('extension/theme/' . $code, ['store_id' => 0], $post);
        touch($state . 'form.done');
    }
    $defaultPrefix = 'theme_default';
    $keys = [$prefix . '_status' => '1', $prefix . '_directory' => $t['dir']];
    foreach (rows("SELECT `key`, value FROM {$P}setting WHERE store_id = 0 AND code = 'theme_default'") as $r) {
        $suffix = substr($r['key'], strlen($defaultPrefix));
        if ($suffix !== '_status' && $suffix !== '_directory') $keys[$prefix . $suffix] = $r['value'];
    }
    foreach ($keys as $key => $value) {
        setting_default($prefix, $key, $value);
    }
}

function register4(array $t, array $paths)
{
    global $db, $P;
    $code = esc($t['code']);
    if (!($id = row("SELECT extension_install_id FROM {$P}extension_install WHERE code = '$code'"))) {
        $db->query("INSERT INTO {$P}extension_install SET extension_id = 0, extension_download_id = 0, name = '" . esc($t['name'])
            . "', description = '', code = '$code', version = '" . esc($t['version']) . "', author = '', link = '', status = 1, date_added = NOW()");
        $id = $db->insert_id;
        foreach ($paths as $p) {
            $db->query("INSERT INTO {$P}extension_path SET extension_install_id = $id, path = '" . esc(substr($p, strlen('extension/'))) . "'");
        }
    }
    if (!row("SELECT 1 FROM {$P}extension WHERE type = 'theme' AND code = '$code'")) {
        $db->query("INSERT INTO {$P}extension SET extension = '$code', type = 'theme', code = '$code'");
    }
    // the theme's install(): a startup that registers its events (each checks config_theme itself)
    if (!row("SELECT 1 FROM {$P}startup WHERE code = '$code'")) {
        $db->query("INSERT INTO {$P}startup SET code = '$code', description = '" . esc($t['name']) . "', action = '"
            . esc($t['startup'][0]) . "', status = 1, sort_order = " . (int) $t['startup'][1]);
    }
    foreach (['access', 'modify'] as $type) {
        add_permission($type, 'extension/' . $t['code'] . '/theme/' . $t['code']);
    }
    setting_default('theme_' . $t['code'], 'theme_' . $t['code'] . '_status', '1');
    if (!empty($t['images'])) {
        $src = ROOT . 'extension/' . $t['code'] . '/' . $t['images'];
        foreach (glob($src . '*') ?: [] as $f) {
            $dest = DIR_IMAGE . 'catalog/' . $t['code'] . '/' . basename($f);
            if (is_file($f) && !is_file($dest)) put($dest, file_get_contents($f));
        }
    }
}

// ---- activate -------------------------------------------------------------------------------

function activate($name)
{
    global $BRANCH, $THEMES, $db, $P;
    $themes = $THEMES[$BRANCH];
    if ($name !== 'default' && !isset($themes[$name])) {
        // also accept the extension code (framefree) for the directory (ff_frame)
        foreach ($themes as $n => $t) if ($t['code'] === $name) $name = $n;
        if (!isset($themes[$name])) fail("unknown theme '$name': default, " . implode(', ', array_keys($themes)));
    }
    $target = $name === 'default' ? null : $themes[$name];
    if ($target && !is_file(STATE . $target['code'] . '/files.list')) fail("'$name' is not installed: make themes");

    $mods = [];
    foreach ($themes as $n => $t) {
        if ($target && $t['code'] === $target['code']) continue;
        theme_switch($t, false, $mods);
    }
    if ($target) theme_switch($target, true, $mods);

    // a 'quiet' theme's own warnings would print into the page: hide them (still logged) while it is active
    $display = STATE . 'error_display';
    if (!is_file($display)) file_put_contents($display, (string) row("SELECT value FROM {$P}setting WHERE store_id = 0 AND `key` = 'config_error_display'"));
    $db->query("UPDATE {$P}setting SET value = '" . (!empty($target['quiet']) ? '0' : esc(trim(file_get_contents($display))))
        . "' WHERE store_id = 0 AND code = 'config' AND `key` = 'config_error_display'");

    $code = $target ? $target['code'] : ($BRANCH === 2 ? 'theme_default' : ($BRANCH === 3 ? 'default' : 'basic'));
    $db->query("UPDATE {$P}setting SET value = '" . esc($code) . "' WHERE store_id = 0 AND code = 'config' AND `key` = 'config_theme'");

    $changed = false;
    foreach ($mods as $m => $status) {
        $was = row("SELECT status FROM {$P}modification WHERE code = '" . esc($m) . "'");
        if ((string) $was !== (string) $status) {
            $db->query("UPDATE {$P}modification SET status = $status WHERE code = '" . esc($m) . "'");
            $changed = true;
        }
    }
    if ($changed || ($BRANCH < 4 && $mods && !is_dir(DIR_MODIFICATION . 'catalog'))) {
        admin()->get($BRANCH === 2 ? 'extension/modification/refresh' : 'marketplace/modification/refresh');
    }
    foreach (glob(DIR_CACHE . 'cache.*') ?: [] as $f) @unlink($f);
    echo "[themes] active: " . ($target ? $name . " (config_theme = $code)" : "default ($code)") . "\n";
    echo HTTP_SERVER . "\n";
}

/** Swap a theme's overriding files and its OCMOD/events in or out. */
function theme_switch(array $t, $on, array &$mods)
{
    global $db, $P, $BRANCH;
    $state = STATE . $t['code'] . '/';
    if (!is_dir($state)) return;
    foreach (tree($state . 'override/') as $rel) {
        $from = $on ? $state . 'override/' . $rel : STATE . '_core/' . $rel;
        if (is_file($from)) put(ROOT . $rel, file_get_contents($from));
    }
    if (is_file($state . 'events.list')) {
        $codes = array_filter(file($state . 'events.list', FILE_IGNORE_NEW_LINES));
        if ($codes) $db->query("UPDATE {$P}event SET status = " . ($on ? 1 : 0) . " WHERE code IN ('" . implode("','", array_map('esc', $codes)) . "')");
    }
    if (is_file($state . 'ocmod.list')) {
        foreach (array_filter(file($state . 'ocmod.list', FILE_IGNORE_NEW_LINES)) as $m) {
            if (!$on && isset($mods[$m]) && $mods[$m] === 1) continue; // shared code, another theme wants it
            $mods[$m] = $on ? 1 : 0;
        }
    }
}

// ---- status ---------------------------------------------------------------------------------

function status()
{
    global $BRANCH, $THEMES, $P, $VERSION;
    $active = row("SELECT value FROM {$P}setting WHERE store_id = 0 AND code = 'config' AND `key` = 'config_theme'");
    echo "[themes] OpenCart $VERSION, config_theme = $active\n";
    foreach ($THEMES[$BRANCH] as $n => $t) {
        $installed = is_file(STATE . $t['code'] . '/files.list');
        echo sprintf("  %-12s code %-12s %s%s\n", $n, $t['code'], $installed ? 'installed' : 'not installed',
            $active === $t['code'] ? ', ACTIVE' : '');
    }
}

// ---- helpers --------------------------------------------------------------------------------

function fetch(array $t)
{
    $file = STATE . '_download/' . $t['sha'] . '.zip';
    if (!is_file($file) || hash_file('sha256', $file) !== $t['sha']) {
        @mkdir(dirname($file), 0755, true);
        $ch = curl_init($t['url']);
        $fp = fopen($file . '.part', 'w');
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_FAILONERROR => true, CURLOPT_TIMEOUT => 300]);
        $ok = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok) fail('download failed: ' . $t['url']);
        $sha = hash_file('sha256', $file . '.part');
        if ($sha !== $t['sha']) {
            unlink($file . '.part');
            fail("sha256 mismatch for {$t['url']}: $sha, expected {$t['sha']}");
        }
        rename($file . '.part', $file);
    }
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) fail("bad zip $file");
    return $zip;
}

/** '*' at the start of a zip path = the archive's single top folder (codeload snapshots). */
function zpath(ZipArchive $zip, $path)
{
    if (substr($path, 0, 2) !== '*/') return $path;
    return strtok($zip->getNameIndex(0), '/') . '/' . substr($path, 2);
}

function member(ZipArchive $zip, $path)
{
    $data = $zip->getFromName(zpath($zip, $path));
    if ($data === false) fail("missing in the archive: $path");
    return $data;
}

/** rel path => content of every file under the theme's root, without the skipped prefixes. */
function unpack_files(ZipArchive $zip, array $t)
{
    $root = zpath($zip, $t['root']);
    $skip = isset($t['skip']) ? $t['skip'] : [];
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (substr($name, -1) === '/' || ($root !== '' && strpos($name, $root) !== 0)) continue;
        $rel = substr($name, strlen($root));
        if ($rel === '' || strpos($rel, '..') !== false || $rel[0] === '/') continue; // zip-slip guard
        foreach ($skip as $s) if (strpos($rel, $s) === 0) continue 2;
        if (in_array($rel, ['install.json', 'install.xml', 'README.md', 'LICENSE'], true) && $root === '' && empty($t['startup'])) continue;
        $out[$rel] = $zip->getFromIndex($i);
    }
    if (!$out) fail("nothing under '{$t['root']}' in the archive");
    return $out;
}

function add_ocmod($xml)
{
    global $db, $P, $BRANCH;
    $x = simplexml_load_string($xml);
    if (!$x || !(string) $x->code) fail('bad OCMOD xml');
    $code = (string) $x->code;
    $exists = row("SELECT modification_id FROM {$P}modification WHERE code = '" . esc($code) . "'");
    $fields = "name = '" . esc(mb_substr(trim((string) $x->name), 0, 64)) . "', code = '" . esc($code) . "', author = '"
        . esc(mb_substr(trim(strip_tags((string) $x->author)), 0, 64)) . "', version = '" . esc((string) $x->version)
        . "', link = '" . esc((string) $x->link) . "', xml = '" . esc($xml) . "'";
    if ($exists) {
        $db->query("UPDATE {$P}modification SET $fields WHERE modification_id = " . (int) $exists);
    } else {
        $db->query("INSERT INTO {$P}modification SET $fields, status = 0, date_added = NOW()" . ($BRANCH === 3 ? ', extension_install_id = 0' : ''));
    }
    return $code;
}

/** The fields of the first POST form on the page, with the values a browser would submit. */
function form_fields($html)
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html);
    $xp = new DOMXPath($doc);
    $form = $xp->query('//form[@method="post" or @method="POST"]')->item(0);
    if (!$form) return [];
    $pairs = [];
    foreach ($xp->query('.//input|.//select|.//textarea', $form) as $el) {
        $name = $el->getAttribute('name');
        if ($name === '' || $el->hasAttribute('disabled')) continue;
        $type = strtolower($el->getAttribute('type'));
        if ($el->nodeName === 'input') {
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) continue;
            if (in_array($type, ['file', 'submit', 'button', 'image', 'reset'], true)) continue;
            $value = $el->hasAttribute('value') ? $el->getAttribute('value') : (in_array($type, ['checkbox', 'radio'], true) ? 'on' : '');
        } elseif ($el->nodeName === 'select') {
            $opt = $xp->query('.//option[@selected]', $el)->item(0) ?: $xp->query('.//option', $el)->item(0);
            if (!$opt) continue;
            $value = $opt->hasAttribute('value') ? $opt->getAttribute('value') : $opt->textContent;
        } else {
            $value = $el->textContent;
        }
        $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
    }
    return implode('&', $pairs); // already encoded: names like a[b][] repeat
}

/** OpenCart 3.0.5+ ships Twig 3, which dropped `{% for x in y if cond %}`. */
function twig3()
{
    $f = ROOT . 'system/storage/vendor/twig/twig/src/Environment.php';
    return is_file($f) && preg_match("/VERSION = '([3-9])\./", file_get_contents($f));
}

/** `{% for a in b if c %}…{% endfor %}` → `{% for a in b %}{% if c %}…{% endif %}{% endfor %}` */
function twig3_for_if($src)
{
    if (!preg_match('/\{%-?\s*for\s[^%]*\sif\s/', $src)) return $src;
    $tokens = preg_split('/(\{%-?\s*(?:for\s[^%]*?|endfor)\s*-?%\})/', $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    $stack = [];
    foreach ($tokens as $i => $tok) {
        if (preg_match('/^\{%(-?)\s*for\s+(.+?)\s+in\s+(.+?)\s+if\s+(.+?)\s*(-?)%\}$/s', $tok, $m)) {
            $tokens[$i] = "{%{$m[1]} for {$m[2]} in {$m[3]} {$m[5]}%}{% if {$m[4]} %}";
            $stack[] = true;
        } elseif (preg_match('/^\{%-?\s*for\s/', $tok)) {
            $stack[] = false;
        } elseif (preg_match('/^\{%-?\s*endfor/', $tok)) {
            if (array_pop($stack)) $tokens[$i] = '{% endif %}' . $tok;
        }
    }
    return implode('', $tokens);
}

function setting_default($group, $key, $value)
{
    global $db, $P;
    if (!row("SELECT 1 FROM {$P}setting WHERE store_id = 0 AND `key` = '" . esc($key) . "'")) {
        $db->query("INSERT INTO {$P}setting SET store_id = 0, code = '" . esc($group) . "', `key` = '" . esc($key)
            . "', value = '" . esc($value) . "', serialized = 0");
    }
}

function add_permission($type, $route)
{
    global $db, $P;
    $json = row("SELECT permission FROM {$P}user_group WHERE user_group_id = 1");
    $perm = json_decode($json, true) ?: [];
    if (!in_array($route, isset($perm[$type]) ? $perm[$type] : [], true)) {
        $perm[$type][] = $route;
        $db->query("UPDATE {$P}user_group SET permission = '" . esc(json_encode($perm)) . "' WHERE user_group_id = 1");
    }
}

/** A logged-in admin session over HTTP to this container (OpenCart's own admin actions). */
function admin()
{
    global $BRANCH;
    static $client;
    if ($client) return $client;
    $client = new class {
        public $token = '';
        private $jar;
        public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'nc'); }
        public function request($route, array $query = [], $post = null)
        {
            global $BRANCH;
            if ($this->token) $query[$BRANCH === 2 ? 'token' : 'user_token'] = $this->token;
            $url = 'http://127.0.0.1/admin/index.php?' . http_build_query(['route' => $route] + $query);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar,
                CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 120]);
            if ($post !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
            $out = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($out === false || $code >= 500) fail("admin $route: HTTP $code");
            return $out;
        }
        public function get($route, array $query = []) { return $this->request($route, $query); }
    };
    $out = $client->request('common/login', [], ['username' => 'admin', 'password' => 'admin']);
    if (!preg_match('/[?&](?:user_)?token=([A-Za-z0-9]+)/', $out, $m)) fail('admin login failed (admin/admin)');
    $client->token = $m[1];
    return $client;
}

function put($path, $content)
{
    if (is_link($path)) unlink($path);
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
        exec('chown -R www-data:www-data ' . escapeshellarg(dirname($path)));
    }
    file_put_contents($path, $content);
    @chown($path, 'www-data');
    @chgrp($path, 'www-data');
}

function tree($dir)
{
    if (!is_dir($dir)) return [];
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $out[] = substr($f->getPathname(), strlen($dir));
    return $out;
}

function esc($s) { global $db; return $db->real_escape_string((string) $s); }
function row($q) { global $db; $r = $db->query($q); $a = $r ? $r->fetch_row() : null; return $a ? $a[0] : null; }
function column($q) { global $db; $r = $db->query($q); $o = []; while ($r && ($a = $r->fetch_row())) $o[] = $a[0]; return $o; }
function rows($q) { global $db; $r = $db->query($q); $o = []; while ($r && ($a = $r->fetch_assoc())) $o[] = $a; return $o; }
function fail($msg) { fwrite(STDERR, "[themes] $msg\n"); exit(1); }
