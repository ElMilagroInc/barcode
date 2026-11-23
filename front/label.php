<?php

/** SPDX-License-Identifier: AGPL-3.0-or-later */
Session::checkRight('plugin_barcode_barcode', CREATE);

try {
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $item = PluginBarcodeLabel::loadItem((string) ($input['itemtype'] ?? ''), (int) ($input['items_id'] ?? 0));
    $type = (string) ($input['type'] ?? 'code128');
    if (!PluginBarcodeLabel::isEnabled($type)) {
        throw new InvalidArgumentException('This code type is disabled.');
    }
    $options = PluginBarcodeLabel::parameters($type);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Content and item identity always come from the authorized record.
        $options = array_replace($options, array_intersect_key($_POST, $options));
    }
    if (!empty($options['prompt_settings']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        Html::header(__('Generate label', 'barcode'), $_SERVER['PHP_SELF']);
        echo '<form method="post" action="' . PluginBarcodeLabel::escape(Html::getPrefixedUrl('/plugins/barcode') . '/front/label.php') . '">';
        foreach (['itemtype' => $item->getType(), 'items_id' => $item->getID(), 'type' => $type] as $key => $value) {
            echo Html::hidden($key, ['value' => $value]);
        }
        (new PluginBarcodeBarcode())->showSizeSelect($options['size']);
        (new PluginBarcodeBarcode())->showOrientationSelect($options['orientation']);
        echo Html::submit(__('Generate label', 'barcode'));
        Html::closeForm();
        Html::footer();
        return;
    }
    $path = PluginBarcodeLabel::generate($item, $type, $options);
    if (!$path) {
        throw new RuntimeException('No label was generated. Check the label data and page dimensions.');
    }
    $token = PluginBarcodeBarcode::registerTmpPdf($path);
    return new Symfony\Component\HttpFoundation\RedirectResponse(Html::getPrefixedUrl('/plugins/barcode') . '/front/send.php?token=' . $token);
} catch (Throwable $e) {
    PluginBarcodeLabel::throwGenerationError($e);
}
