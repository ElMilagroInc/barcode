<?php

/**
 * Copyright (C) 2009-2016 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
    die("Sorry. You can't access directly to this file");
}

class PluginBarcodeConfigType extends CommonDBTM
{
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_barcode_configs_types';
    }
}

// Backward compatibility alias
if (!class_exists('PluginBarcodeConfig_Type', false)) {
    class_alias('PluginBarcodeConfigType', 'PluginBarcodeConfig_Type');
}
