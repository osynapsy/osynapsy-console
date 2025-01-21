<?php

/*
 * This file is part of the Osynapsy package.
 *
 * (c) Pietro Celeste <p.celeste@osynapsy.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

chdir(__DIR__);

$vendorDir = realpath(__DIR__.'/../../');
require $vendorDir. '/autoload.php';

try {
    $cron = new \Osynapsy\Console\Cron($vendorDir, $argv);
    echo $cron->run();
} catch (\Exception $e) {
    echo $e->getMessage();
} finally {
    echo PHP_EOL;
}