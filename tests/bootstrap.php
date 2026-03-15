<?php

// When running as a standalone project, use own vendor autoloader; otherwise use parent project's.
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        break;
    }
}

echo 'INFO: GMP installed: ' . (extension_loaded('gmp') ? 'yes' : 'no') . PHP_EOL;
echo 'INFO: GD installed: ' . (extension_loaded('gd') ? 'yes' : 'no') . PHP_EOL;
