<?php

/** SPDX-License-Identifier: AGPL-3.0-or-later */

// GLPI's LegacyFileLoadController provides the application bootstrap.

Session::checkRight('config', UPDATE);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return new Symfony\Component\HttpFoundation\Response('', 405, ['Allow' => 'POST']);
}

// ─────────────────────────────────────────────────────────────────────────────
// Safety net: ensure the class file is loaded.
// Plugin::init() should have already pulled it in, but in some edge cases
// (e.g. plugin freshly enabled, autoload race) the class can be missing.
// ─────────────────────────────────────────────────────────────────────────────
if (!class_exists('PluginBarcodeCustomSize')) {
    $classPath = __DIR__ . '/../inc/customsize.class.php';
    if (file_exists($classPath)) {
        require_once $classPath;
    }
}

if (!class_exists('PluginBarcodeCustomSize')) {
    Session::addMessageAfterRedirect(
        __('PluginBarcodeCustomSize class is missing — re-deploy the plugin files.', 'barcode'),
        false,
        ERROR
    );
    Html::back();
    return;
}

// ─────────────────────────────────────────────────────────────────────────────
// Safety net: the DB table must exist.
// If it doesn't, plugin_barcode_install() hasn't run since the upgrade —
// tell the admin clearly instead of letting CommonDBTM::add() crash with 500.
// ─────────────────────────────────────────────────────────────────────────────
global $DB;
if (!$DB->tableExists(PluginBarcodeCustomSize::getTable())) {
    Session::addMessageAfterRedirect(
        __('Database table glpi_plugin_barcode_customsizes is missing. Go to Setup → Plugins and click "Upgrade" on the Barcode plugin (or disable and re-enable it) to create it.', 'barcode'),
        false,
        ERROR
    );
    Html::back();
    return;
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($_POST['delete_id'])) {
    $cs = new PluginBarcodeCustomSize();
    $id = (int) $_POST['delete_id'];
    if ($cs->getFromDB($id)) {
        $name = $cs->fields['name'];
        $cs->delete(['id' => $id], true);
        Session::addMessageAfterRedirect(
            sprintf(__('Custom size "%s" deleted.', 'barcode'), $name)
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Custom size not found.', 'barcode'),
            false,
            ERROR
        );
    }
    Html::back();
    return;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADD
// ─────────────────────────────────────────────────────────────────────────────
if (!empty($_POST['add'])) {

    $name    = trim((string) ($_POST['name']      ?? ''));
    $widthS  = trim((string) ($_POST['width_mm']  ?? ''));
    $heightS = trim((string) ($_POST['height_mm'] ?? ''));
    $comment = trim((string) ($_POST['comment']   ?? ''));

    // Accept both "35.5" and "35,5" — European decimal comma is common.
    $width  = (float) str_replace(',', '.', $widthS);
    $height = (float) str_replace(',', '.', $heightS);

    $errors = [];

    if ($name === '') {
        $errors[] = __('Name is required.', 'barcode');
    } elseif (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $name)) {
        $errors[] = __('Name must contain only letters, digits, hyphens and underscores (max 20 characters).', 'barcode');
    } elseif (PluginBarcodeCustomSize::isReservedName($name)) {
        $errors[] = sprintf(
            __('Name "%s" is reserved by a standard page format. Pick a different name.', 'barcode'),
            $name
        );
    } elseif (countElementsInTable(
        PluginBarcodeCustomSize::getTable(),
        ['name' => $name]
    ) > 0) {
        $errors[] = sprintf(
            __('A custom size named "%s" already exists.', 'barcode'),
            $name
        );
    }

    if (!is_numeric(str_replace(',', '.', $widthS)) || !is_finite($width) || $width <= 0 || $width > 9999) {
        $errors[] = __('Width must be a positive number up to 9999 mm.', 'barcode');
    }
    if (!is_numeric(str_replace(',', '.', $heightS)) || !is_finite($height) || $height <= 0 || $height > 9999) {
        $errors[] = __('Height must be a positive number up to 9999 mm.', 'barcode');
    }

    if (!empty($errors)) {
        foreach ($errors as $e) {
            Session::addMessageAfterRedirect($e, false, ERROR);
        }
    } else {
        $cs = new PluginBarcodeCustomSize();
        $newId = $cs->add([
            'name'      => $name,
            'width_mm'  => $width,
            'height_mm' => $height,
            'comment'   => $comment,
        ]);
        if ($newId) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __('Custom size "%s" added (%s x %s mm).', 'barcode'),
                    $name,
                    $width,
                    $height
                )
            );
        } else {
            Session::addMessageAfterRedirect(
                __('Failed to save the custom size — check the server logs (files/_log/php-errors.log).', 'barcode'),
                false,
                ERROR
            );
        }
    }
}

Html::back();
