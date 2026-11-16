<?php

/**
 * Copyright (C) 2009-2016 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
Session::checkLoginUser();
$type = (string) ($_GET['itemtype'] ?? '');
if (!is_subclass_of($type, CommonDBTM::class) || (new ReflectionClass($type))->isAbstract()) {
    return new Symfony\Component\HttpFoundation\Response('', 404);
}
$item = new $type();
if (!$item->getFromDBByCrit(['otherserial' => (string) ($_GET['inventoryNumber'] ?? '')])) {
    return new Symfony\Component\HttpFoundation\Response('', 404);
}
$item->check($item->getID(), READ);
return new Symfony\Component\HttpFoundation\RedirectResponse($item->getFormURLWithID($item->getID()));
