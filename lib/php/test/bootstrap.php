<?php

use Thrift\ClassLoader\ThriftClassLoader;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Define APCu stub functions if the extension is not loaded
// This allows php-mock to mock these functions in tests
if (!function_exists('apcu_fetch')) {
    function apcu_fetch(string $key, ?bool &$success = null): mixed
    {
        $success = false;
        return false;
    }
}

if (!function_exists('apcu_store')) {
    function apcu_store(string $key, mixed $var, int $ttl = 0): bool
    {
        return false;
    }
}

$loader = new ThriftClassLoader();
$loader->registerNamespace('Basic', __DIR__ . '/Resources/packages/php');
$loader->registerNamespace('Validate', __DIR__ . '/Resources/packages/phpv');
$loader->registerNamespace('ValidateOop', __DIR__ . '/Resources/packages/phpvo');
$loader->registerNamespace('Json', __DIR__ . '/Resources/packages/phpjs');

#do not load this namespace here, it will be loaded in ClassLoaderTest
//$loader->registerNamespace('Server', __DIR__ . '/Resources/packages/phpcm');

$loader->register();
