<?php

/**
 * Copyright (C) 2009-2024 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
Session::checkLoginUser();
$path = GLPI_PLUGIN_DOC_DIR . '/barcode/logo.png';
if (($_GET['file'] ?? '') !== 'barcode/logo.png' || !is_file($path)) {
    return new Symfony\Component\HttpFoundation\Response('', 404);
}
$response = new Symfony\Component\HttpFoundation\BinaryFileResponse($path);
$response->headers->set('Content-Type', 'image/png');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Cache-Control', 'private, no-store');
return $response;
