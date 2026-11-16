<?php

/** SPDX-License-Identifier: AGPL-3.0-or-later */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Manages user-defined page/label dimensions persisted in the database.
 *
 * Thermal-label printers and other specialised hardware commonly use sizes
 * that aren't part of the ISO/ANSI paper standards (e.g. 35 × 20 mm,
 * 66 × 16 mm, A6+5 mm and so on). This class lets administrators add
 * such dimensions through the GLPI configuration UI; values are stored
 * in a dedicated DB table and survive plugin upgrades untouched.
 *
 * Storage: glpi_plugin_barcode_customsizes
 * Consumed by:
 *   - PluginBarcodeBarcode::showSizeSelect()  (dropdown merge)
 *   - PluginBarcodeBarcode::printPDF()        (TCPDF dimension array)
 */
class PluginBarcodeCustomSize extends CommonDBTM
{
    public static string $rightname = 'plugin_barcode_config';

    /**
     * Names that conflict with TCPDF's built-in page formats.
     * Adding a custom size with one of these names is rejected at form time
     * so administrators don't accidentally shadow a standard format and then
     * wonder why A4 prints at 35 mm wide.
     *
     * @var string[]
     */
    private static array $reservedNames = [
        '4A0', '2A0',
        'A0', 'A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10',
        'B0', 'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
        'C0', 'C1', 'C2', 'C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10',
        'RA0', 'RA1', 'RA2', 'RA3', 'RA4',
        'SRA0', 'SRA1', 'SRA2', 'SRA3', 'SRA4',
        'LETTER', 'LEGAL', 'EXECUTIVE', 'FOLIO',
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_barcode_customsizes';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Custom label size', 'Custom label sizes', $nb, 'barcode');
    }

    // -------------------------------------------------------------------------
    // Public API consumed by PluginBarcodeBarcode
    // -------------------------------------------------------------------------

    /**
     * Returns custom sizes formatted for Dropdown::showFromArray():
     *   ['35-20' => '35-20 (35 × 20 mm)', '66-16' => '66-16 (66 × 16 mm)', …]
     *
     * Safely returns an empty array if the table doesn't exist yet — e.g.
     * a partial upgrade where install hasn't run — so the size dropdown
     * still works with only the standard formats.
     *
     * @return array<string,string>
     */
    public static function getDropdownOptions(): array
    {
        global $DB;
        $out = [];
        if (!$DB->tableExists(self::getTable())) {
            return $out;
        }
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'ORDER' => 'name',
        ]);
        foreach ($iterator as $row) {
            $label = $row['name'];
            if (!empty($row['width_mm']) && !empty($row['height_mm'])) {
                $label .= ' (' . self::trimDecimal($row['width_mm'])
                        . ' × '
                        . self::trimDecimal($row['height_mm']) . ' mm)';
            }
            $out[$row['name']] = $label;
        }
        return $out;
    }

    /**
     * Resolve a size name to a [width_mm, height_mm] float array for TCPDF.
     * Returns null when the name doesn't correspond to a custom size — the
     * caller should then pass the name through to TCPDF as a built-in format.
     *
     * @param  string     $name  e.g. "35-20"
     * @return float[]|null      e.g. [35.0, 20.0]
     */
    public static function resolveDimensions(string $name): ?array
    {
        global $DB;
        if (!$DB->tableExists(self::getTable())) {
            return null;
        }
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['name' => $name],
            'LIMIT' => 1,
        ]);
        foreach ($iterator as $row) {
            $w = (float) $row['width_mm'];
            $h = (float) $row['height_mm'];
            if ($w > 0 && $h > 0) {
                return [$w, $h];
            }
        }
        return null;
    }

    /**
     * True when the supplied name collides with a TCPDF built-in format.
     * Comparison is case-insensitive to catch "a4" → "A4".
     */
    public static function isReservedName(string $name): bool
    {
        return in_array(strtoupper($name), self::$reservedNames, true);
    }

    // -------------------------------------------------------------------------
    // Admin UI — rendered at the bottom of the barcode config page
    // -------------------------------------------------------------------------

    /**
     * Render the "Custom label sizes" section: existing rows + add form.
     * Called from PluginBarcodeConfig::showForm().
     */
    public static function showManagementForm(): void
    {
        global $DB;

        echo "<br>";
        echo "<form name='customsize_form' method='post' action='"
           . Plugin::getWebDir('barcode') . "/front/customsize.form.php'>";
        echo "<div align='center'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='5'>"
           . __('Custom label sizes', 'barcode')
           . "</th></tr>";
        echo "<tr><td colspan='5' class='tab_bg_2'"
           . " style='font-weight:normal;font-size:0.9em;text-align:center;padding:6px'>"
           . __('Define non-standard page or label dimensions here. New entries appear in the size dropdown for both single and massive barcode generation.', 'barcode')
           . "</td></tr>";

        // ── Existing rows ─────────────────────────────────────────────────────
        // Buffer into an array so we can both detect emptiness and iterate
        // without re-issuing the query (DBmysqlIterator can only be walked once).
        $rows = [];
        $limit = max(1, (int) ($_SESSION['glpilist_limit'] ?? 20));
        $total = $DB->tableExists(self::getTable()) ? countElementsInTable(self::getTable()) : 0;
        $start = max(0, min((int) ($_GET['start'] ?? 0), max(0, $total - 1)));
        $start = intdiv($start, $limit) * $limit;
        if ($DB->tableExists(self::getTable())) {
            foreach ($DB->request(['FROM' => self::getTable(), 'ORDER' => 'name', 'START' => $start, 'LIMIT' => $limit]) as $row) {
                $rows[] = $row;
            }
        }

        if (!empty($rows)) {
            echo "<tr class='tab_bg_2'>";
            echo "<th>" . __('Name', 'barcode') . "</th>";
            echo "<th>" . __('Width (mm)', 'barcode') . "</th>";
            echo "<th>" . __('Height (mm)', 'barcode') . "</th>";
            echo "<th>" . __('Comment', 'barcode') . "</th>";
            echo "<th>" . __('Delete', 'barcode') . "</th>";
            echo "</tr>";
            foreach ($rows as $row) {
                echo "<tr class='tab_bg_1'>";
                echo "<td align='center'><strong>"
                   . htmlspecialchars($row['name']) . "</strong></td>";
                echo "<td align='center'>"
                   . self::trimDecimal($row['width_mm']) . "</td>";
                echo "<td align='center'>"
                   . self::trimDecimal($row['height_mm']) . "</td>";
                echo "<td>"
                   . htmlspecialchars((string) ($row['comment'] ?? ''))
                   . "</td>";
                echo "<td align='center'>";
                $confirmMsg = addslashes(__('Delete this custom size?', 'barcode'));
                echo "<button type='submit' name='delete_id' value='" . (int) $row['id']
                   . "' class='submit' formnovalidate onclick=\"return confirm('"
                   . $confirmMsg . "');\">"
                   . __('Delete', 'barcode')
                   . "</button>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr class='tab_bg_1'><td colspan='5' align='center'><em>"
               . __('No custom sizes defined yet.', 'barcode')
               . "</em></td></tr>";
        }

        // ── Add new ───────────────────────────────────────────────────────────
        echo "<tr class='tab_bg_2'>";
        echo "<th colspan='5'>" . __('Add a new custom size', 'barcode') . "</th>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td align='center'>"
           . "<input type='text' name='name' size='10' maxlength='20'"
           . " placeholder='35-20' required></td>";
        echo "<td align='center'>"
           . "<input type='text' name='width_mm' size='8' placeholder='35.0' required></td>";
        echo "<td align='center'>"
           . "<input type='text' name='height_mm' size='8' placeholder='20.0' required></td>";
        echo "<td><input type='text' name='comment' size='30' maxlength='255'"
           . " placeholder='" . __('Optional description', 'barcode') . "'></td>";
        echo "<td align='center'>";
        echo "<button type='submit' name='add' value='1' class='submit'>"
           . __('Add', 'barcode')
           . "</button>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        echo "</div>";
        Html::closeForm();
        Html::printPager($start, $total, Plugin::getWebDir('barcode') . '/front/config.php', 'section=sizes');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * "35.00" → "35", "35.50" → "35.5", "35.55" → "35.55"
     * Cosmetic: strip trailing zeros from DECIMAL(7,2) for display only.
     */
    private static function trimDecimal($value): string
    {
        $s = number_format((float) $value, 2, '.', '');
        if (strpos($s, '.') !== false) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        return $s;
    }
}
