<?php

/**
 * Copyright (C) 2009-2016 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

Session::checkRight("config", UPDATE);

// To be available when plugin is not activated
Plugin::load('barcode');

Html::header(__('Barcode', 'barcode'), $_SERVER['PHP_SELF'], "config", "plugins");
$pbConfig = new PluginBarcodeConfig();
$pbConfig->showForm('');

Html::footer();
