<?php

/** Verify inheritance against real GLPI 12 classes, without substituting core classes.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
$autoload = getenv('BARCODE_TEST_AUTOLOAD');
if (!$autoload || !is_file($autoload)) {
    throw new RuntimeException('Set BARCODE_TEST_AUTOLOAD to GLPI 12 vendor/autoload.php.');
}
require $autoload;
require dirname(__DIR__) . '/setup.php';
foreach (['config_type', 'customsize', 'config', 'barcode', 'qrcode', 'profile', 'label'] as $class) {
    require dirname(__DIR__) . '/inc/' . $class . '.class.php';
}
if (!version_compare(GLPI_VERSION, PLUGIN_BARCODE_MIN_GLPI, '>=')) {
    throw new RuntimeException('Run this check against GLPI 12.');
}
echo 'GLPI ' . GLPI_VERSION . " class contracts loaded successfully.\n";
