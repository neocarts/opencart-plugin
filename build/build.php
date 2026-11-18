<?php
// Builds the three installable archives from src/ (make build → dist/):
//   neocarts-oc2-X.Y.Z.ocmod.zip  — OpenCart 2.3: upload/ + install.xml
//   neocarts-oc3-X.Y.Z.ocmod.zip  — OpenCart 3.0: upload/ + install.xml
//   neocarts.ocmod.zip            — OpenCart 4.x: the extension root (install.json …); 4.x takes the
//                                   extension code from the file name, so it must stay `neocarts`
// The shared core, texts and storefront JS are copied into each. docs/ai/releasing.md
$root = dirname(__DIR__);
$version = trim(file_get_contents("$root/VERSION"));
if (!preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $version)) {
    fwrite(STDERR, "VERSION is not X.Y.Z or X.Y.Z-beta.N: $version\n");
    exit(1);
}

$dist = "$root/dist";
@mkdir($dist, 0777, true);
array_map('unlink', glob("$dist/*.zip"));

function add_dir(ZipArchive $zip, string $dir, string $prefix, array $skip = [])
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($dir) + 1);
        if ($file->isDir() || basename($rel) === '.gitkeep' || basename($rel) === '.DS_Store') {
            continue;
        }
        foreach ($skip as $s) {
            if (strpos($rel, $s) === 0) {
                continue 2;
            }
        }
        $zip->addFile($file->getPathname(), $prefix . $rel);
    }
}

function add_shared(ZipArchive $zip, string $root, string $libPrefix, string $jsPath, string $version)
{
    add_dir($zip, "$root/src/shared/php", $libPrefix . 'php/', ['autoload.php']);
    add_dir($zip, "$root/src/shared/lang", $libPrefix . 'lang/');
    $autoload = str_replace("'0.0.0-dev'", "'$version'", file_get_contents("$root/src/shared/php/autoload.php"));
    if (strpos($autoload, "'$version'") === false) {
        throw new RuntimeException('autoload.php has no version placeholder');
    }
    $zip->addFromString($libPrefix . 'php/autoload.php', $autoload);
    $zip->addFile("$root/src/shared/js/neocarts.js", $jsPath);
}

function open_zip(string $path): ZipArchive
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("cannot write $path");
    }

    return $zip;
}

foreach (['oc2', 'oc3'] as $branch) {
    $path = "$dist/neocarts-$branch-$version.ocmod.zip";
    $zip = open_zip($path);
    add_dir($zip, "$root/src/$branch/upload", 'upload/');
    add_shared($zip, $root, 'upload/system/library/neocarts/', 'upload/catalog/view/javascript/neocarts/neocarts.js', $version);
    $xml = preg_replace('#<version>[^<]*</version>#', "<version>$version</version>", file_get_contents("$root/src/$branch/install.xml"));
    $zip->addFromString('install.xml', $xml);
    $zip->close();
    echo "built $path\n";
}

$path = "$dist/neocarts.ocmod.zip";
$zip = open_zip($path);
add_dir($zip, "$root/src/oc4/neocarts", '', ['install.json', 'system/library/neocarts', 'catalog/view/javascript/neocarts']);
add_shared($zip, $root, 'system/library/neocarts/', 'catalog/view/javascript/neocarts/neocarts.js', $version);
$manifest = json_decode(file_get_contents("$root/src/oc4/neocarts/install.json"), true);
$manifest['version'] = $version;
$zip->addFromString('install.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$zip->close();
echo "built $path (OpenCart 4.x, version $version)\n";
