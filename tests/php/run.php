<?php
// A dependency-free runner (PHPUnit cannot cover PHP 7.1 and 8.5 with one version).
// Every tests/php/*Test.php defines functions named test_*; a test fails by throwing.
require __DIR__ . '/../../src/shared/php/autoload.php';

function eq($expected, $actual, string $what = '')
{
    if ($expected !== $actual) {
        throw new Exception(($what ? "$what: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function throws(callable $fn, ?string $message = null)
{
    try {
        $fn();
    } catch (Exception $e) {
        if ($message !== null && $e->getMessage() !== $message) {
            throw new Exception("expected exception '$message', got '" . $e->getMessage() . "'");
        }

        return $e;
    }
    throw new Exception('expected an exception' . ($message ? " '$message'" : ''));
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$failed = 0;
$passed = 0;
foreach (get_defined_functions()['user'] as $fn) {
    if (strpos($fn, 'test_') !== 0) {
        continue;
    }
    try {
        $fn();
        $passed++;
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL $fn: " . $e->getMessage() . "\n";
    }
}
echo 'PHP ' . PHP_VERSION . ": $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
