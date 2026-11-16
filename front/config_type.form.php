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
    $type = (string) ($_POST['type'] ?? '');
    if (!in_array($type, (new PluginBarcodeBarcode())->getCodeTypes(), true)) {
        throw new InvalidArgumentException('Invalid code type.');
    }
    $input = ['type' => $type];
    foreach (['marginTop', 'marginBottom', 'marginLeft', 'marginRight', 'marginHorizontal',
        'marginVertical', 'maxCodeWidth', 'maxCodeHeight', 'txtSize', 'txtSpacing'] as $field) {
        $minimum = in_array($field, ['maxCodeWidth', 'maxCodeHeight', 'txtSize'], true) ? 1 : 0;
        $value = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > 2000) {
            throw new InvalidArgumentException('Dimensions must be whole numbers between 0 and 2000 points; code and font sizes must be positive.');
        }
        $input[$field] = $value;
    }
    $input['size'] = PluginBarcodeBarcode::validatePageSize((string) ($_POST['size'] ?? ''));
    $input['orientation'] = (string) ($_POST['orientation'] ?? '');
    if (!in_array($input['orientation'], ['Portrait', 'Landscape'], true)) {
        throw new InvalidArgumentException('Invalid orientation.');
    }
    $config = new PluginBarcodeConfigType();
    if ($config->getFromDBByCrit(['type' => $type])) {
        $input['id'] = $config->getID();
        $config->update($input);
    }
} catch (InvalidArgumentException $e) {
    Session::addMessageAfterRedirect(PluginBarcodeLabel::escape($e->getMessage()), false, ERROR);
}
Html::back();
