<?php

/**
 * Copyright (C) 2009-2024 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
Session::checkRight('plugin_barcode_barcode', CREATE);
$token = (string) ($_GET['token'] ?? '');
$path = PluginBarcodeBarcode::resolveTmpPdf($token);
if ($path === null) {
    return new Symfony\Component\HttpFoundation\Response('This label has expired. Generate it again.', 404);
}
$realPath = realpath($path);
$realTmp = realpath(GLPI_TMP_DIR);
if ($realPath === false || $realTmp === false || !str_starts_with($realPath, $realTmp . DIRECTORY_SEPARATOR)) {
    return new Symfony\Component\HttpFoundation\Response('', 403);
}
// Keep the file until token expiry: browser viewers may reload or request ranges.
$response = new Symfony\Component\HttpFoundation\BinaryFileResponse($realPath);
$response->headers->set('Content-Type', 'application/pdf');
$response->headers->set('Cache-Control', 'private, no-store');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->setContentDisposition('inline', 'barcodes.pdf');
return $response;
