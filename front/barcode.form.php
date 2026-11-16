<?php

/**
 * Copyright (C) 2009-2024 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
Session::checkRight('plugin_barcode_barcode', CREATE);
$barcode = new PluginBarcodeBarcode();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::header(__('Barcode', 'barcode'), $_SERVER['PHP_SELF'], 'tools', PluginBarcodeBarcode::class);
    echo '<form method="post" target="_blank" action="' . PluginBarcodeLabel::escape(Plugin::getWebDir('barcode') . '/front/barcode.form.php') . '">';
    echo '<label>' . __('Code', 'barcode') . ' <input type="text" name="code" maxlength="2000" required></label>';
    (new PluginBarcodeConfig())->showTypeSelect((new PluginBarcodeConfig())->getConfig(), ['QRcode']);
    echo '<label>' . __('Number of copies', 'barcode') . ' <input type="number" name="nb" value="1" min="1" max="1000"></label>';
    echo Html::submit(__('Generate label', 'barcode'));
    Html::closeForm();
    Html::footer();
    return;
}
try {
    $type = (string) ($_POST['type'] ?? 'code128');
    $options = PluginBarcodeLabel::parameters($type);
    $options['code'] = (string) ($_POST['code'] ?? '');
    $options['nb'] = (int) ($_POST['nb'] ?? 1);
    $path = $barcode->printPDF($options);
    if (!$path) {
        throw new RuntimeException('No label was generated.');
    }
    $token = PluginBarcodeBarcode::registerTmpPdf($path);
    return new Symfony\Component\HttpFoundation\RedirectResponse(Plugin::getWebDir('barcode') . '/front/send.php?token=' . $token);
} catch (InvalidArgumentException | RuntimeException $e) {
    Html::displayErrorAndDie(PluginBarcodeLabel::escape($e->getMessage()), true);
}
