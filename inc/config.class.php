<?php

/**
 * Copyright (C) 2009-2016 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
    die("Sorry. You can't access directly to this file");
}

class PluginBarcodeConfig extends CommonDBTM
{
    public static string $rightname = 'plugin_barcode_config';

    public function showForm($ID, array $options = [])
    {
        $types = (new PluginBarcodeBarcode())->getCodeTypes();
        $sections = ['general' => __('General configuration', 'barcode')]
            + array_combine($types, $types)
            + ['sizes' => __('Custom label sizes', 'barcode')];
        $section = (string) ($_GET['section'] ?? 'general');
        if (!isset($sections[$section])) {
            $section = 'general';
        }
        echo '<nav class="nav nav-tabs mb-3" aria-label="' . PluginBarcodeLabel::escape(__('Barcode plugin configuration', 'barcode')) . '">';
        foreach ($sections as $key => $label) {
            echo '<a class="nav-link' . ($key === $section ? ' active' : '') . '" href="'
                . PluginBarcodeLabel::escape(Html::getPrefixedUrl('/plugins/barcode') . '/front/config.php?section=' . urlencode($key))
                . '"' . ($key === $section ? ' aria-current="page"' : '') . '>' . PluginBarcodeLabel::escape($label) . '</a>';
        }
        echo '</nav>';
        if ($section === 'sizes') {
            PluginBarcodeCustomSize::showManagementForm();
        } elseif ($section !== 'general') {
            $this->showFormConfigType($section);
        } else {
            $this->showGeneralForm();
        }
    }

    private function showGeneralForm(): void
    {
        $settings = PluginBarcodeLabel::settings();
        echo '<form method="post" enctype="multipart/form-data" action="'
            . PluginBarcodeLabel::escape(Html::getPrefixedUrl('/plugins/barcode') . '/front/config.form.php') . '">';
        echo '<table class="tab_cadre_fixe"><tr><th colspan="2">'
            . __('Barcode plugin configuration', 'barcode') . '</th></tr>';
        echo '<tr><td>' . __('Default barcode type', 'barcode') . '</td><td>';
        // Show all types here, even when disabled, so administrators can re-enable them.
        $this->showTypeSelect($this->getConfig(), [], true);
        echo '</td></tr>';
        $labels = [
            'enable_barcode' => __('Enable barcodes', 'barcode'),
            'enable_qrcode' => __('Enable QR codes', 'barcode'),
            'prompt_settings' => __('Prompt for settings before generation', 'barcode'),
            'auto_print' => __('Request the print dialog when opening a PDF', 'barcode'),
            'show_logo' => __('Show company logo above codes', 'barcode'),
            'border' => __('Display border', 'barcode'),
            'displaylabels' => __('Display field labels', 'barcode'),
        ];
        foreach ($labels as $key => $label) {
            echo '<tr><td>' . PluginBarcodeLabel::escape($label) . '</td><td>';
            Dropdown::showYesNo($key, (int) $settings[$key]);
            echo '</td></tr>';
        }
        echo '<tr><td>' . __('Custom text below codes (plain text)', 'barcode') . '</td><td><textarea name="custom_text" class="form-control" maxlength="500" rows="3">'
            . PluginBarcodeLabel::escape((string) $settings['custom_text']) . '</textarea></td></tr>';
        echo '<tr><td colspan="2">' . __('Automatic printing depends on the browser PDF viewer. Text is printed literally; HTML and template expressions are not evaluated.', 'barcode') . '</td></tr>';
        echo '<tr><th colspan="2">' . __('QR code defaults: encode / display', 'barcode') . '</th></tr>';
        foreach ([
            'serialnumber' => __('Serial number'), 'inventorynumber' => __('Inventory number'),
            'id' => __('ID'), 'uuid' => __('UUID'), 'name' => __('Name'),
            'url' => __('URL'), 'qrcodedate' => __('Date'),
        ] as $key => $label) {
            echo '<tr><td>' . PluginBarcodeLabel::escape($label) . '</td><td>';
            Dropdown::showYesNo($key, (int) $settings[$key]);
            Dropdown::showYesNo('display' . $key, (int) $settings['display' . $key]);
            echo '</td></tr>';
        }
        echo '<tr><td>' . __('URL by inventory number', 'barcode') . '</td><td>';
        Dropdown::showYesNo('inventorynumberURL', (int) $settings['inventorynumberURL']);
        echo '</td></tr><tr><th colspan="2">' . __('Company logo', 'barcode') . '</th></tr>';
        if (is_file(GLPI_PLUGIN_DOC_DIR . '/barcode/logo.png')) {
            echo '<tr><td colspan="2"><img style="max-width:300px;max-height:100px" alt="'
                . PluginBarcodeLabel::escape(__('Company logo', 'barcode')) . '" src="'
                . PluginBarcodeLabel::escape(Html::getPrefixedUrl('/plugins/barcode') . '/front/document.send.php?file=barcode/logo.png')
                . '"><button type="submit" name="dropLogo" value="1" class="btn btn-outline-secondary">'
                . __('Delete the logo', 'barcode') . '</button></td></tr>';
        }
        echo '<tr><td colspan="2"><input type="file" name="logo" accept="image/png"> PNG, 2 MiB, 2000 × 2000 px maximum</td></tr>';
        echo '<tr><td colspan="2">' . Html::submit(__('Save'), ['name' => 'save']) . '</td></tr></table>';
        Html::closeForm();
    }

    public function getConfig(): string
    {
        $pbconf = new PluginBarcodeConfig();
        if ($pbconf->getFromDB(1)) {
            $type = $pbconf->fields['type'];
        } else {
            $type = 'code128';
        }
        return $type;
    }

    public function showFormConfigType($p_type = null): void
    {
        $pbBarcode = new PluginBarcodeBarcode();

        if (is_null($p_type)) {
            $type = $this->getConfig();
        } else {
            $type = $p_type;
        }

        $config = $this->getConfigType($type);
        echo "<form name='form' method='post'
            action='" . Html::getPrefixedUrl('/plugins/barcode') . "/front/config_type.form.php'>";
        echo "<input type='hidden' name='type' value='" . PluginBarcodeLabel::escape((string) $type) . "'>";
        echo "<div align='center'>";
        echo "<table class='tab_cadre_fixe' >";

        echo "<tr><th colspan='4'>" . PluginBarcodeLabel::escape((string) $type) . "</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Page size', 'barcode') . "</td><td>";
        $pbBarcode->showSizeSelect($config['size']);
        echo "</td>";
        echo "<td>" . __('Orientation', 'barcode') . "</td><td>";
        $pbBarcode->showOrientationSelect($config['orientation']);
        echo "</td>";
        echo "</tr>";
        echo "<tr><th colspan='4'>" . __('Margins', 'barcode') . "</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Top', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='marginTop' value='" . PluginBarcodeLabel::escape((string) $config['marginTop']) . "'>";
        echo "</td>";
        echo "<td>" . __('Bottom', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='marginBottom' value='" . PluginBarcodeLabel::escape((string) $config['marginBottom']) . "'>";
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Left', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='marginLeft' value='" . PluginBarcodeLabel::escape((string) $config['marginLeft']) . "'>";
        echo "</td>";
        echo "<td>" . __('Right', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='marginRight' value='" . PluginBarcodeLabel::escape((string) $config['marginRight']) . "'>";
        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Inner horizontal', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='marginHorizontal' value='" . PluginBarcodeLabel::escape((string) $config['marginHorizontal']) . "'>";
        echo "</td>";
        echo "<td>" . __('Inner vertical', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='marginVertical' value='" . PluginBarcodeLabel::escape((string) $config['marginVertical']) . "'>";
        echo "</td>";
        echo "</tr>";
        echo "<tr><th colspan='4'>" . __('Barcodes sizes', 'barcode') . "</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Maximum width', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='maxCodeWidth' value='" . PluginBarcodeLabel::escape((string) $config['maxCodeWidth']) . "'>";
        echo "</td>";
        echo "<td>" . __('Maximum height', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='maxCodeHeight' value='" . PluginBarcodeLabel::escape((string) $config['maxCodeHeight']) . "'>";
        echo "</td>";
        echo "</tr>";
        echo "<tr><th colspan='4'>" . __('Text display options', 'barcode') . "</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Text size', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='txtSize' value='" . PluginBarcodeLabel::escape((string) $config['txtSize']) . "'>";
        echo "</td>";
        echo "<td>" . __('Text spacing between lines', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='txtSpacing' value='" . PluginBarcodeLabel::escape((string) $config['txtSpacing']) . "'>";
        echo "</td>";
        echo "</tr>";

        echo "<tr><td class='tab_bg_1' colspan='4' align='center'><input type='submit' value='" . __('Save') . "' class='submit'></td></tr>";
        echo "</table>";
        echo "</div>";
        Html::closeForm();
    }

    public function getConfigType($p_type = null): array
    {
        $type = $p_type ?? $this->getConfig();
        $defaults = [
            'type' => $type, 'size' => 'A4', 'orientation' => 'Portrait',
            'marginTop' => 30, 'marginBottom' => 30, 'marginLeft' => 30, 'marginRight' => 30,
            'marginHorizontal' => 25, 'marginVertical' => 30,
            'maxCodeWidth' => 110, 'maxCodeHeight' => $type === 'QRcode' ? 100 : 70,
            'txtSize' => 8, 'txtSpacing' => 3,
        ];
        $config = new PluginBarcodeConfigType();
        if ($config->getFromDBByCrit(['type' => $type])) {
            return array_replace($defaults, array_intersect_key($config->fields, $defaults));
        }
        return $defaults;
    }

    public function showTypeSelect($p_type = null, $used = [], bool $includeDisabled = false): void
    {
        $types = (new PluginBarcodeBarcode())->getCodeTypes();
        if (!$includeDisabled) {
            $types = array_values(array_filter($types, [PluginBarcodeLabel::class, 'isEnabled']));
        }
        Dropdown::showFromArray('type', array_combine($types, $types), [
            'width' => '100%', 'used' => $used, 'value' => $p_type,
        ]);
    }
}
