<?php

/**
 * Copyright (C) 2009-2024 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * QR code data builder and form renderer.
 *
 * This class is responsible only for:
 *   1. Collecting the structured data (serial, inv. number, URL, etc.) that
 *      will be encoded into a QR code.
 *   2. Rendering the Massive Action form for QR options.
 *
 * The actual QR code rendering is delegated to TCPDF (write2DBarcode) inside
 * PluginBarcodeBarcode::printPDF().  No QR image files are written to disk.
 */
class PluginBarcodeQRcode
{
    // -------------------------------------------------------------------------
    // Data builder
    // -------------------------------------------------------------------------

    /**
     * Build the text content and display labels for a single QR code.
     *
     * Returns an array [ string $qrText, string[] $displayLines ] on success,
     * or false when there is no encodable content.
     *
     * @param  string $itemtype   GLPI itemtype class name.
     * @param  int    $items_id   Record ID.
     * @param  array  $data       Form input (field-visibility toggles).
     * @return array|false
     */
    public function buildQRcodeData(string $itemtype, int $items_id, array $data)
    {
        global $CFG_GLPI;

        /** @var CommonDBTM $item */
        $item = new $itemtype();
        $item->check($items_id, READ);

        $URLById         = 'URL = ' . $CFG_GLPI['url_base'] . $itemtype::getFormURLWithID($items_id, false);
        $URLByInvNumber  = 'URL = ' . Plugin::getWebDir('barcode', true, true)
                           . '/front/checkItemByInv.php?inventoryNumber='
                           . rawurlencode((string) ($item->fields['otherserial'] ?? '')) . '&itemtype=' . rawurlencode($itemtype);

        // $a_content → data encoded inside the QR symbol (machine-readable)
        // $b_content → human-readable labels printed below the QR image
        $a_content   = [];
        $b_content   = [];
        $have_content = false;

        // Serial number
        if (!empty($data['serialnumber'])) {
            $val = $item->fields['serial'] ?? '';
            if ($val !== '') {
                $have_content = true;
            }
            $a_content[] = __('Serial number', 'barcode') . ' = ' . $val;
            if (!empty($data['displayserialnumber'])) {
                $label       = (!empty($data['displaylabels'])) ? __('Serial number', 'barcode') . ': ' : '';
                $b_content[] = $label . $val;
            }
        }

        // Inventory number
        if (!empty($data['inventorynumber'])) {
            $val = $item->fields['otherserial'] ?? '';
            if ($val !== '') {
                $have_content = true;
            }
            $a_content[] = __('Inventory number', 'barcode') . ' = ' . $val;
            if (!empty($data['displayinventorynumber'])) {
                $label       = (!empty($data['displaylabels'])) ? __('Inventory number', 'barcode') . ': ' : '';
                $b_content[] = $label . $val;
            }
        }

        // ID
        if (!empty($data['id'])) {
            $val = (string) ($item->fields['id'] ?? '');
            if ($val !== '') {
                $have_content = true;
            }
            $a_content[] = __('ID', 'barcode') . ' = ' . $val;
            if (!empty($data['displayid'])) {
                $label       = (!empty($data['displaylabels'])) ? __('ID', 'barcode') . ': ' : '';
                $b_content[] = $label . $val;
            }
        }

        // UUID
        if (!empty($data['uuid']) && isset($item->fields['uuid'])) {
            $val = $item->fields['uuid'];
            if ($val !== '') {
                $have_content = true;
            }
            $a_content[] = __('UUID', 'barcode') . ' = ' . $val;
            if (!empty($data['displayuuid'])) {
                $label       = (!empty($data['displaylabels'])) ? __('UUID', 'barcode') . ': ' : '';
                $b_content[] = $label . $val;
            }
        }

        // Name
        if (!empty($data['name'])) {
            $val = $item->fields['name'] ?? '';
            if ($val !== '') {
                $have_content = true;
            }
            $a_content[] = __('Item Name', 'barcode') . ' = ' . $val;
            if (!empty($data['displayname'])) {
                $label       = (!empty($data['displaylabels'])) ? __('Item Name', 'barcode') . ': ' : '';
                $b_content[] = $label . $val;
            }
        }

        // URL
        if (!empty($data['url']) && empty($item->no_form_page)) {
            $url         = !empty($data['inventorynumberURL']) ? $URLByInvNumber : $URLById;
            $a_content[] = $url;
            if (!empty($data['displayurl'])) {
                $label       = (!empty($data['displaylabels'])) ? __('Item URL', 'barcode') . ': ' : '';
                $b_content[] = $label . $url;
            }
        }

        // QR code date stamp
        if (!empty($data['qrcodedate'])) {
            $today       = date('Y-m-d');
            $a_content[] = 'QRcode date = ' . $today;
            if (!empty($data['displayqrcodedate'])) {
                $label       = (!empty($data['displaylabels'])) ? __('Date', 'barcode') . ': ' : '';
                $b_content[] = $label . $today;
            }
        }

        if (empty($a_content)) {
            return false;
        }

        return [implode("\n", $a_content), $b_content];
    }

    /**
     * BC shim: legacy callers pass $rand / $number (used for temp PNG files).
     * Those parameters are no longer needed because TCPDF renders QR codes
     * directly without writing any temporary files.
     *
     * @return array|false  [ string $qrText, string[] $displayLines ]
     */
    public function generateQRcode(string $itemtype, int $items_id, $rand, $number, array $data)
    {
        return $this->buildQRcodeData($itemtype, $items_id, $data);
    }

    /**
     * No-op: temporary PNG files are no longer created, so there is nothing
     * to clean up.  Kept for backward-compatibility with any code that still
     * calls this method.
     */
    public function cleanQRcodefiles($rand, $number): void
    {
        // TCPDF renders QR codes as vector paths — no temp files are written.
    }

    // -------------------------------------------------------------------------
    // Massive Action form
    // -------------------------------------------------------------------------

    public function showFormMassiveAction(MassiveAction $ma): void
    {
        if (PluginBarcodeLabel::showQuickMassiveForm()) {
            return;
        }
        $settings = PluginBarcodeLabel::settings();
        $fields       = [];
        $no_form_page = true;

        $itemtype = $ma->getItemtype(false);
        if (is_a($itemtype, CommonDBTM::class, true)) {
            /** @var CommonDBTM $item */
            $item = new $itemtype();
            $item->getEmpty();
            $fields       = array_keys($item->fields);
            $no_form_page = $item->no_form_page;
        }

        echo '<input type="hidden" name="type" value="QRcode" />';
        echo '<center><table>';

        // Serial number
        if (in_array('serial', $fields)) {
            echo '<tr>';
            echo '<td>' . __('Serial number', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("serialnumber", (int) $settings['serialnumber'], -1, ['width' => '100']);
            echo '</td><td>' . __('Display serial number', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("displayserialnumber", (int) $settings['displayserialnumber'], -1, ['width' => '100']);
            echo '</td></tr>';
        } else {
            echo Html::hidden('serialnumber', ['value' => 0]);
        }

        // Inventory number
        if (in_array('otherserial', $fields)) {
            echo '<tr>';
            echo '<td>' . __('Inventory number', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("inventorynumber", (int) $settings['inventorynumber'], -1, ['width' => '100']);
            echo '</td><td>' . __('Display inventory number', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("displayinventorynumber", (int) $settings['displayinventorynumber'], -1, ['width' => '100']);
            echo '</td></tr>';
        } else {
            echo Html::hidden('inventorynumber', ['value' => 0]);
        }

        // ID
        echo '<tr>';
        echo '<td>' . __('ID', 'barcode') . " : </td><td>";
        Dropdown::showYesNo("id", (int) $settings['id'], -1, ['width' => '100']);
        echo '</td><td>' . __('Display ID', 'barcode') . " : </td><td>";
        Dropdown::showYesNo("displayid", (int) $settings['displayid'], -1, ['width' => '100']);
        echo '</td></tr>';

        // UUID
        if (in_array('uuid', $fields)) {
            echo '<tr>';
            echo '<td>' . __('UUID', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("uuid", (int) $settings['uuid'], -1, ['width' => '100']);
            echo '</td><td>' . __('Display UUID', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("displayuuid", (int) $settings['displayuuid'], -1, ['width' => '100']);
            echo '</td></tr>';
        } else {
            echo Html::hidden('uuid', ['value' => 0]);
        }

        // Name
        if (in_array('name', $fields)) {
            echo '<tr>';
            echo '<td>' . __('Name', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("name", (int) $settings['name'], -1, ['width' => '100']);
            echo '</td><td>' . __('Display name', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("displayname", (int) $settings['displayname'], -1, ['width' => '100']);
            echo '</td></tr>';
        } else {
            echo Html::hidden('name', ['value' => 0]);
        }

        // URL
        if (!$no_form_page) {
            echo '<tr>';
            echo '<td>' . __('Web page of the item', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("url", (int) $settings['url'], -1, ['width' => '100']);
            echo '</td><td>' . __('Display web page of the device', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("displayurl", (int) $settings['displayurl'], -1, ['width' => '100']);
            echo '</td></tr>';
            echo '<tr>';
            echo '<td>' . __('URL by inventory number', 'barcode') . " : </td><td>";
            Dropdown::showYesNo("inventorynumberURL", (int) $settings['inventorynumberURL'], -1, ['width' => '100']);
            echo '</td></tr>';
        } else {
            echo Html::hidden('url', ['value' => 0]);
            echo Html::hidden('inventorynumberURL', ['value' => 0]);
        }

        // Date
        echo '<tr>';
        echo '<td>' . __('Date QRcode', 'barcode') . " (" . date('Y-m-d') . ") : </td><td>";
        Dropdown::showYesNo("qrcodedate", (int) $settings['qrcodedate'], -1, ['width' => '100']);
        echo '</td><td>' . __('Display date QRcode', 'barcode') . " (" . date('Y-m-d') . ") : </td><td>";
        Dropdown::showYesNo("displayqrcodedate", (int) $settings['displayqrcodedate'], -1, ['width' => '100']);
        echo '</td></tr>';

        echo '<tr><td>&nbsp;</td><td>&nbsp;</td>';
        echo '<td colspan="2">' . __('Note: Currently supporting only up to 2 lines.', 'barcode') . '</td></tr>';

        echo '</table><br/>';
        PluginBarcodeBarcode::commonShowMassiveAction();
    }

    // -------------------------------------------------------------------------
    // MassiveAction callbacks
    // -------------------------------------------------------------------------

    public function getSpecificMassiveActions($checkitem = null): array
    {
        return [];
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma): bool
    {
        switch ($ma->getAction()) {
            case 'Generate':
                (new self())->showFormMassiveAction($ma);
                return true;
        }
        return false;
    }

    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids): void
    {
        PluginBarcodeLabel::processMassiveAction($ma, $item, $ids, true);
    }
}
