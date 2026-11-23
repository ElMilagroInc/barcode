<?php

/**
 * Copyright (C) 2009-2024 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

define("PLUGIN_BARCODE_VERSION", "3.0.5");

// Minimal GLPI version, inclusive
define('PLUGIN_BARCODE_MIN_GLPI', '12.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_BARCODE_MAX_GLPI', '13.0.0');

// Init the hooks of the plugins - Needed
function plugin_init_barcode()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['barcode'] = true;

    foreach (['config_type', 'customsize', 'config', 'barcode', 'qrcode', 'profile', 'label'] as $class) {
        require_once __DIR__ . '/inc/' . $class . '.class.php';
    }
    Plugin::registerClass(PluginBarcodeProfile::class, ['addtabon' => [Profile::class]]);
    $PLUGIN_HOOKS['post_item_form']['barcode'] = [PluginBarcodeLabel::class, 'showButtons'];
    $PLUGIN_HOOKS['add_javascript']['barcode'] = ['js/label.js'];

    // Display a menu entry?
    if (Session::haveRight('plugin_barcode_barcode', CREATE)
            || Session::haveRight('plugin_barcode_config', UPDATE)) {

        // Massive Action definition
        $PLUGIN_HOOKS['use_massive_action']['barcode'] = 1;

        $PLUGIN_HOOKS['menu_toadd']['barcode'] = ['tools' => PluginBarcodeBarcode::class];
    }

    // Config page
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['barcode'] = 'front/config.php';
    }

    // Redirect code: /glpi/index.php?redirect=plugin_barcode_2 goes to form ID 2
    $PLUGIN_HOOKS['redirect_page']['barcode'] = 'barcode.form.php';
}

function plugin_version_barcode()
{

    return [
        'name'           => 'Barcode',
        'shortname'      => 'barcode',
        'version'        => PLUGIN_BARCODE_VERSION,
        'license'        => 'AGPLv3+',
        'author'         => 'David DURIEUX, Jean Marc GRISARD, Vincent MAZZONI',
        'homepage'       => 'https://github.com/ElMilagroInc/barcode',
        'requirements'   => [
            'php' => ['min' => '8.3'],
            'glpi' => [
                'min' => PLUGIN_BARCODE_MIN_GLPI,
                'max' => PLUGIN_BARCODE_MAX_GLPI,
            ]
        ]
    ];
}
