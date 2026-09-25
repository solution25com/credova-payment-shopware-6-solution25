<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

$loaded = false;
foreach ($autoloadCandidates as $autoload) {
    if (file_exists($autoload)) {
        $loader = require $autoload;
        if ($loader instanceof ClassLoader) {
            $loader->addPsr4('Credova\\', __DIR__ . '/../src');
            $loader->addPsr4('Credova\\Tests\\', __DIR__);
        }
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    throw new RuntimeException('Could not find a composer autoloader for the Credova plugin tests.');
}
