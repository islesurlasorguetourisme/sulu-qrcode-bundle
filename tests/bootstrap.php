<?php

declare(strict_types=1);

/*
 * This file is part of the Sulu QR Code Bundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// The bundle is tested either on its own (its own vendor directory) or from a
// host application that installed it through a Composer "path" repository.
$autoloadFiles = [
    \dirname(__DIR__) . '/vendor/autoload.php',
    \dirname(__DIR__, 2) . '/vendor/autoload.php',
];

foreach ($autoloadFiles as $autoloadFile) {
    if (\is_file($autoloadFile)) {
        $loader = require $autoloadFile;
        // A host application does not load the "autoload-dev" section of the bundle.
        $loader->addPsr4('Islesurlasorguetourisme\\SuluQrCodeBundle\\Tests\\', __DIR__);

        return;
    }
}

throw new RuntimeException('Unable to find the Composer autoloader. Run "composer install" first.');
