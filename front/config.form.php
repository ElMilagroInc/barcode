<?php

/**
 * Copyright (C) 2009-2016 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
Session::checkRight('config', UPDATE);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return new Symfony\Component\HttpFoundation\Response('', 405, ['Allow' => 'POST']);
}

try {
    $logoPath = GLPI_PLUGIN_DOC_DIR . '/barcode/logo.png';
    if (isset($_POST['dropLogo'])) {
        if (is_file($logoPath) && !unlink($logoPath)) {
            throw new RuntimeException('Unable to remove the logo.');
        }
    } else {
        $type = (string) ($_POST['type'] ?? 'code128');
        if (!in_array($type, (new PluginBarcodeBarcode())->getCodeTypes(), true)) {
            throw new InvalidArgumentException('Invalid barcode type.');
        }
        $values = [];
        foreach (PluginBarcodeLabel::defaults() as $key => $default) {
            if ($key === 'custom_text') {
                $text = str_replace("\r\n", "\n", trim((string) ($_POST[$key] ?? '')));
                if (mb_strlen($text) > 500 || substr_count($text, "\n") > 4) {
                    throw new InvalidArgumentException('Custom text is limited to 500 characters and five lines.');
                }
                $values[$key] = $text;
            } else {
                $values[$key] = (int) (($_POST[$key] ?? 0) === '1');
            }
        }
        // Validate and re-encode the upload before changing any saved settings.
        $upload = $_FILES['logo'] ?? null;
        if ($upload !== null && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024
                || !is_uploaded_file($upload['tmp_name'])) {
                throw new InvalidArgumentException('Upload a PNG file no larger than 2 MiB.');
            }
            $info = getimagesize($upload['tmp_name']);
            if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 2000 || $info[1] > 2000) {
                throw new InvalidArgumentException('The logo must be a PNG no larger than 2000 × 2000 pixels.');
            }
            if (!function_exists('imagecreatefrompng')) {
                throw new RuntimeException('The PHP GD extension is required to upload logos.');
            }
            $image = imagecreatefrompng($upload['tmp_name']);
            if ($image === false) {
                throw new InvalidArgumentException('Invalid PNG image.');
            }
            imagesavealpha($image, true);
            $temporary = tempnam(dirname($logoPath), 'logo_');
            try {
                if ($temporary === false || !imagepng($image, $temporary) || !rename($temporary, $logoPath)) {
                    throw new RuntimeException('Unable to save the logo.');
                }
            } finally {
                if ($temporary !== false && is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        Config::setConfigurationValues(PluginBarcodeLabel::CONTEXT, $values);
        (new PluginBarcodeConfig())->update(['id' => 1, 'type' => $type]);
    }
    Session::addMessageAfterRedirect(__('Configuration saved.', 'barcode'));
} catch (InvalidArgumentException | RuntimeException $e) {
    Session::addMessageAfterRedirect(PluginBarcodeLabel::escape($e->getMessage()), false, ERROR);
}
Html::back();
