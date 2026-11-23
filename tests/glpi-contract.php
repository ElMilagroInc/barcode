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

// Loading the classes alone cannot catch removed static methods in menu callbacks.
$CFG_GLPI['root_doc'] = '/glpi';
$menu = PluginBarcodeBarcode::getMenuContent();
if ($menu['page'] !== '/plugins/barcode/front/barcode.form.php'
    || $menu['links']['config'] !== '/plugins/barcode/front/config.php'
    || Html::getPrefixedUrl($menu['page']) !== '/glpi/plugins/barcode/front/barcode.form.php') {
    throw new RuntimeException('Menu URLs must be canonical paths without a duplicated GLPI prefix.');
}
// Check every explicit call to the legacy Plugin class against this real core.
foreach (array_merge(glob(dirname(__DIR__) . '/*.php'), glob(dirname(__DIR__) . '/inc/*.php'), glob(dirname(__DIR__) . '/front/*.php')) as $file) {
    $code = '';
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token)) {
            if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= $token[1];
            }
        } else {
            $code .= $token;
        }
    }
    preg_match_all('/\bPlugin::([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $matches);
    foreach ($matches[1] as $method) {
        if (!is_callable([Plugin::class, $method])) {
            throw new RuntimeException('Unsupported Plugin::' . $method . '() in ' . basename($file));
        }
    }
}
echo "Menu callback and Plugin API calls are compatible.\n";
