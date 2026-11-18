<?php
// The shared core: no OpenCart dependencies, PHP 7.1 syntax. Each build copies this directory to
// system/library/neocarts/ (2.3, 3.0) or extension/neocarts/system/library/neocarts/ (4.x).
if (!defined('NEOCARTS_VERSION')) {
    define('NEOCARTS_VERSION', '0.0.0-dev'); // build/build.php writes VERSION here
}

spl_autoload_register(function ($class) {
    if (strpos($class, 'Neocarts\\') === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
