<?php

/**
 * Copyright (C) 2009-2024 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (!defined('GLPI_ROOT') && !defined('GLPI_DIR')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class to generate barcode PDFs using GLPI's native TCPDF library.
 *
 * No external vendor dependencies — TCPDF ships with GLPI 12 at
 * vendor/tecnickcom/tcpdf/tcpdf.php.  All barcodes and QR codes are rendered
 * as native vector paths inside the PDF (no GD, no temporary PNG files).
 */
class PluginBarcodeBarcode
{
    public static string $rightname = 'plugin_barcode_barcode';

    /**
     * Maps plugin barcode type names to TCPDF write1DBarcode() type strings.
     * @var array<string,string>
     */
    private static array $tcpdfTypeMap = [
        'Code39'  => 'C39',
        'code128' => 'C128',
        'ean13'   => 'EAN13',
        'int25'   => 'I25',
        'postnet' => 'POSTNET',
        'upca'    => 'UPCA',
    ];

    // -------------------------------------------------------------------------
    // GLPI framework integration
    // -------------------------------------------------------------------------

    public static function getTypeName($nb = 0): string
    {
        return __('Barcode', 'barcode');
    }

    public static function getMenuContent(): array
    {
        return [
            'title'              => self::getTypeName(),
            'page'               => '/plugins/barcode/front/barcode.form.php',
            'icon'               => 'fas fa-barcode',
            'links' => [
                'add'    => '/plugins/barcode/front/barcode.form.php',
                'config' => '/plugins/barcode/front/config.php',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Form helpers
    // -------------------------------------------------------------------------

    public function getCodeTypes(): array
    {
        return ['Code39', 'code128', 'ean13', 'int25', 'postnet', 'upca', 'QRcode'];
    }

    public function showSizeSelect($p_size = null): void
    {
        $sizes = [
            '4A0' => __('4A0', 'barcode'), '2A0' => __('2A0', 'barcode'),
            'A0' => __('A0', 'barcode'), 'A1' => __('A1', 'barcode'),
            'A2' => __('A2', 'barcode'), 'A3' => __('A3', 'barcode'),
            'A4' => __('A4', 'barcode'), 'A5' => __('A5', 'barcode'),
            'A6' => __('A6', 'barcode'), 'A7' => __('A7', 'barcode'),
            'A8' => __('A8', 'barcode'), 'A9' => __('A9', 'barcode'),
            'A10' => __('A10', 'barcode'),
            'B0' => __('B0', 'barcode'), 'B1' => __('B1', 'barcode'),
            'B2' => __('B2', 'barcode'), 'B3' => __('B3', 'barcode'),
            'B4' => __('B4', 'barcode'), 'B5' => __('B5', 'barcode'),
            'B6' => __('B6', 'barcode'), 'B7' => __('B7', 'barcode'),
            'B8' => __('B8', 'barcode'), 'B9' => __('B9', 'barcode'),
            'B10' => __('B10', 'barcode'),
            'C0' => __('C0', 'barcode'), 'C1' => __('C1', 'barcode'),
            'C2' => __('C2', 'barcode'), 'C3' => __('C3', 'barcode'),
            'C4' => __('C4', 'barcode'), 'C5' => __('C5', 'barcode'),
            'C6' => __('C6', 'barcode'), 'C7' => __('C7', 'barcode'),
            'C8' => __('C8', 'barcode'), 'C9' => __('C9', 'barcode'),
            'C10' => __('C10', 'barcode'),
            'RA0' => __('RA0', 'barcode'), 'RA1' => __('RA1', 'barcode'),
            'RA2' => __('RA2', 'barcode'), 'RA3' => __('RA3', 'barcode'),
            'RA4' => __('RA4', 'barcode'),
            'SRA0' => __('SRA0', 'barcode'), 'SRA1' => __('SRA1', 'barcode'),
            'SRA2' => __('SRA2', 'barcode'), 'SRA3' => __('SRA3', 'barcode'),
            'SRA4' => __('SRA4', 'barcode'),
            'LETTER'   => __('LETTER', 'barcode'),
            'LEGAL'    => __('LEGAL', 'barcode'),
            'EXECUTIVE' => __('EXECUTIVE', 'barcode'),
            'FOLIO'    => __('FOLIO', 'barcode'),
        ];

        $sizes += PluginBarcodeCustomSize::getDropdownOptions();

        Dropdown::showFromArray(
            "size",
            $sizes,
            is_null($p_size) ? ['width' => '100'] : ['value' => $p_size, 'width' => '100']
        );
    }

    public function showOrientationSelect($p_orientation = null): void
    {
        Dropdown::showFromArray("orientation", [
            'Portrait' => __('Portrait', 'barcode'),
            'Landscape' => __('Landscape', 'barcode'),
        ], is_null($p_orientation) ? ['width' => '100'] : ['value' => $p_orientation, 'width' => '100']);
    }

    public function showForm($p_type, $p_ID): void
    {
        $config = $this->getConfigType();
        $ci = PluginBarcodeLabel::loadItem((string) $p_type, (int) $p_ID);
        $code   = PluginBarcodeLabel::escape((string) ($ci->isField('otherserial') ? $ci->getField('otherserial') : ''));

        echo "<form name='form' method='post' target='_blank' action='" . Html::getPrefixedUrl('/plugins/barcode') . "/front/barcode.form.php'>";
        echo "<div align='center'><table class='tab_cadre'>";
        echo "<tr><th colspan='4'>" . __('Generation', 'barcode') . "</th></tr>";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Code', 'barcode') . "</td><td>";
        echo "<input type='text' size='20' name='code' value='$code'></td>";
        echo "<td>" . __('Type', 'barcode') . "</td><td>";
        (new PluginBarcodeConfig())->showTypeSelect($config['type']);
        echo "</td>";
        echo "</tr><tr class='tab_bg_1'>";
        echo "<td>" . __('Page size', 'barcode') . "</td><td>";
        $this->showSizeSelect($config['size']);
        echo "</td><td>" . __('Orientation', 'barcode') . "</td><td>";
        $this->showOrientationSelect($config['orientation']);
        echo "</td>";
        echo "</tr><tr class='tab_bg_1'>";
        echo "<td>" . __('Number of copies', 'barcode') . "</td>";
        echo "<td><input type='text' size='20' name='nb' value='1'></td>";
        echo "<td colspan='2'></td>";
        echo "</tr>";
        echo "<tr><td class='tab_bg_1' colspan='4' align='center'>
            <input type='submit' value='" . __('Create', 'barcode') . "' class='submit'></td></tr>";
        echo "</table></div>";
        Html::closeForm();
    }

    public function showFormMassiveAction(MassiveAction $ma): void
    {
        if (PluginBarcodeLabel::showQuickMassiveForm()) {
            return;
        }
        $pbConfig = new PluginBarcodeConfig();
        echo '<center><strong>';
        echo __('It will generate only elements have defined field:', 'barcode') . ' ';
        echo (array_key_first($ma->getItems()) == 'Ticket')
           ? __('Ticket number', 'barcode')
           : __('Inventory number', 'barcode');
        echo '</strong>';
        echo '<table><tr><td>';
        $config = $pbConfig->getConfigType();
        echo __('Type', 'barcode') . " : </td><td>";
        $pbConfig->showTypeSelect($config['type'], ['QRcode' => 'QRcode']);
        echo '</td></tr></table><br/>';
        PluginBarcodeBarcode::commonShowMassiveAction();
    }

    public static function commonShowMassiveAction(): void
    {
        $pbBarcode = new PluginBarcodeBarcode();
        $pbConfig  = new PluginBarcodeConfig();
        $config    = $pbConfig->getConfigType();
        $settings = PluginBarcodeLabel::settings();

        echo '<table>';
        echo '<tr>';
        echo '<td><br/>' . __('Page size', 'barcode') . ' : </td><td>';
        $pbBarcode->showSizeSelect($config['size']);
        echo '</td><td>';
        echo __('Not use first xx barcodes', 'barcode') . ' : </td><td>';
        Dropdown::showNumber("eliminate", ['width' => '100']);
        echo '</td>';
        echo '</tr><tr>';
        echo '<td><br/>' . __('Orientation', 'barcode') . ' : </td><td>';
        $pbBarcode->showOrientationSelect($config['orientation']);
        echo '</td><td>';
        echo __('Display border', 'barcode') . ' : </td><td>';
        Dropdown::showYesNo("border", (int) $settings['border'], -1, ['width' => '100']);
        echo '</td>';
        echo '</tr><tr>';
        echo '<td>&nbsp;</td><td>&nbsp;</td><td>';
        echo __('Display labels', 'barcode') . ' : </td><td>';
        Dropdown::showYesNo("displaylabels", (int) $settings['displaylabels'], -1, ['width' => '100']);
        echo '</td>';
        echo '</tr></table></center><br/>';
        echo Html::submit(__('Create', 'barcode'), ['value' => 'create']);
    }

    // -------------------------------------------------------------------------
    // PDF generation — TCPDF native, zero external vendor dependencies
    // -------------------------------------------------------------------------

    /**
     * Build a PDF containing barcodes or QR codes using GLPI's bundled TCPDF.
     *
     * TCPDF's write1DBarcode() and write2DBarcode() render everything as vector
     * paths directly inside the PDF stream — no GD, no temporary image files,
     * no dependency on rospdf, pear/image_barcode or deltalab/phpqrcode.
     *
     * @param  array       $p_params  See inline comments for keys.
     * @return string|int  Absolute path to the saved PDF, or 0 when there is no content.
     */
    public function printPDF(array $p_params): string|int
    {
        Session::checkRight('plugin_barcode_barcode', CREATE);
        $settings = PluginBarcodeLabel::settings();

        $pbConfig = new PluginBarcodeConfig();

        // ── Parameters ──────────────────────────────────────────────────────────
        $type        = (string) ($p_params['type']        ?? 'code128');
        if (!PluginBarcodeLabel::isEnabled($type)) {
            throw new InvalidArgumentException('This code type is disabled.');
        }
        $size = self::validatePageSize((string) ($p_params['size'] ?? 'A4'));
        $isLandscape = (($p_params['orientation'] ?? 'Portrait') === 'Landscape');
        $showBorder  = !empty($p_params['border']);
        $displayDataCollection = $p_params['displayData'] ?? [];

        // ── Build code list ──────────────────────────────────────────────────────
        $codes = [];
        if ($type === 'QRcode') {
            $codes = $p_params['codes'] ?? [];
        } elseif (isset($p_params['code'])) {
            $nb = (int) ($p_params['nb'] ?? 1);
            if ($nb < 1 || $nb > PluginBarcodeLabel::MAX_LABELS) {
                throw new InvalidArgumentException('Print between 1 and 1000 labels at a time.');
            }
            $codes = array_fill(0, $nb, (string) $p_params['code']);
        } elseif (isset($p_params['codes'])) {
            $codes = $p_params['codes'];
        } else {
            return 0;
        }

        if (empty($codes) || !array_filter($codes, static fn ($code) => (string) $code !== '')) {
            return 0;
        }

        if (!is_array($codes) || count($codes) > PluginBarcodeLabel::MAX_LABELS) {
            throw new InvalidArgumentException('Print at most 1000 labels at a time.');
        }
        foreach ($codes as $code) {
            if (!is_scalar($code) || strlen((string) $code) > 2000) {
                throw new InvalidArgumentException('Code content is too long.');
            }
            if ((string) $code !== '') {
                self::validateCode($type, (string) $code);
            }
        }
        // Configurable text is literal PDF text, never passed to an HTML renderer.
        $customLines = trim((string) $settings['custom_text']) === ''
           ? [] : explode("\n", str_replace("\r\n", "\n", (string) $settings['custom_text']));
        foreach ($codes as $index => $code) {
            $displayDataCollection[$index] = array_merge(
                $type === 'QRcode' ? [] : [(string) $code],
                $displayDataCollection[$index] ?? [],
                $customLines
            );
        }

        // ── Layout config from DB ────────────────────────────────────────────────
        // IMPORTANT: all dimensional values in glpi_plugin_barcode_configs_types
        // are stored in PDF POINTS (pt) — that is how the original Cezpdf engine
        // used them. TCPDF works in millimetres. Conversion: 1 pt = 0.352778 mm.
        // txtSize is a font size already in pt; TCPDF's SetFont() also takes pt.
        $pt2mm   = 0.352778;

        $cfg     = $pbConfig->getConfigType($type);
        $codeW   = max(1.0, (float) $cfg['maxCodeWidth']    * $pt2mm);
        $codeH   = max(1.0, (float) $cfg['maxCodeHeight']   * $pt2mm);
        $marginH = max(0.0, (float) $cfg['marginHorizontal'] * $pt2mm);
        $marginV = max(0.0, (float) $cfg['marginVertical']   * $pt2mm);
        $mTop    = max(0.0, (float) $cfg['marginTop']        * $pt2mm);
        $mBottom = max(0.0, (float) $cfg['marginBottom']     * $pt2mm);
        $mLeft   = max(0.0, (float) $cfg['marginLeft']       * $pt2mm);
        $mRight  = max(0.0, (float) $cfg['marginRight']      * $pt2mm);
        $txtPt   = max(4.0, (float) ($cfg['txtSize']    ?? 6));          // pt — no conversion
        $txtSp   = max(0.0, (float) ($cfg['txtSpacing'] ?? 3) * $pt2mm); // pt → mm

        // ── Load TCPDF from GLPI vendor directory ────────────────────────────────
        // GLPI 12 bundles TCPDF at this path.
        if (!class_exists('TCPDF')) {
            $tcpdfPath = (defined('GLPI_ROOT') ? GLPI_ROOT : GLPI_DIR) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
            if (!file_exists($tcpdfPath)) {
                Session::addMessageAfterRedirect(
                    __('TCPDF not found in GLPI vendor directory. Cannot generate PDF.', 'barcode'),
                    false,
                    ERROR
                );
                return 0;
            }
            require_once $tcpdfPath;
        }

        // ── Logo ─────────────────────────────────────────────────────────────────
        $logoPath = GLPI_PLUGIN_DOC_DIR . '/barcode/logo.png';
        $hasLogo  = false;
        $logoW    = 0.0;
        $logoH_mm = 0.0;

        // Minimum height reserved for the barcode symbol itself.
        // Code39 / Code128 / etc. need at least ~8 mm to produce scannable bars.
        // EAN-13 / UPC-A have an ISO minimum of ~15 mm (handled separately below).
        // We reserve 60 % of the cell for the barcode, with an absolute floor of
        // 8 mm, so the logo is scaled down rather than crushing the barcode.
        $minBarcodeH = max(8.0, $codeH * 0.60);
        // Maximum space we can give to the logo (+ 1.5 mm gap below it)
        $logoGap     = 1.5;
        $maxLogoH    = max(0.0, $codeH - $minBarcodeH - $logoGap);

        if (!empty($settings['show_logo']) && $maxLogoH > 1.0 && file_exists($logoPath)) {
            $imgInfo = @getimagesize($logoPath);
            if ($imgInfo && $imgInfo[1] > 0) {
                $ratio    = $imgInfo[0] / $imgInfo[1];
                $logoH_mm = min($imgInfo[1] * 0.264583, $maxLogoH); // px → mm at 96 dpi
                $logoW    = $logoH_mm * $ratio;
                if ($logoW > $codeW) {
                    $logoW    = $codeW;
                    $logoH_mm = $logoW / $ratio;
                }
                $hasLogo = ($logoH_mm > 0.5); // skip if scaled to near-zero
            }
        }

        // Vertical space available for the barcode symbol within the cell.
        // The logo (if any) and its gap are now guaranteed to leave at least
        // $minBarcodeH mm for the actual bars.
        $barcodeAreaH = $codeH - ($hasLogo ? $logoH_mm + $logoGap : 0.0);
        $barcodeAreaH = max($minBarcodeH, $barcodeAreaH);

        // Pre-compute text block height for consistent grid rows
        $lineH    = $txtPt * 0.352778 + 1.0; // pt → mm + 1 mm leading
        // ── Initialise TCPDF document ─────────────────────────────────────────────
        // If the requested size matches a row in glpi_plugin_barcode_customsizes,
        // pass its dimensions to TCPDF as a [width, height] array (in mm). When
        // the name doesn't match a custom row, fall through and let TCPDF resolve
        // it as one of its built-in formats (A4, LETTER, etc.).
        $tcpdfSize = PluginBarcodeCustomSize::resolveDimensions($size) ?? $size;

        $orientation = $isLandscape ? 'L' : 'P';
        $pdf = new TCPDF($orientation, 'mm', $tcpdfSize, true, 'UTF-8', false);
        $pdf->SetCreator('GLPI Barcode plugin v' . PLUGIN_BARCODE_VERSION);
        $pdf->SetAuthor('GLPI');
        $pdf->SetTitle('Barcodes – ' . $type);
        $pdf->SetMargins($mLeft, $mTop, $mRight, true);
        $pdf->SetAutoPageBreak(false, $mBottom);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();

        $pageW = $pdf->GetPageWidth();
        $pageH = $pdf->GetPageHeight();

        $pdf->SetFont('dejavusans', '', max(6.0, $txtPt));
        $textHeights = [];
        foreach ($displayDataCollection as $index => $lines) {
            $textHeights[$index] = 0.0;
            foreach ($lines as $line) {
                $textHeights[$index] += max($lineH, $pdf->getStringHeight($codeW, (string) $line)) + $txtSp;
            }
        }
        $cellH = $codeH + (empty($textHeights) ? 0.0 : max($textHeights)) + 2.0;
        if ($codeW > $pageW - $mLeft - $mRight || $cellH > $pageH - $mTop - $mBottom) {
            throw new InvalidArgumentException('The label does not fit the page. Reduce code size, margins or text, or select a larger page.');
        }
        // ── Render loop ──────────────────────────────────────────────────────
        $x     = $mLeft;
        $y     = $mTop;
        $first = true;

        foreach ($codes as $ia => $code) {
            $code        = (string) $code;
            $displayData = (isset($displayDataCollection[$ia]) && is_array($displayDataCollection[$ia]))
                           ? $displayDataCollection[$ia] : [];
            $numLines    = count($displayData);
            $thisCellH = $cellH;

            // Advance position before drawing (skip on very first item)
            if (!$first) {
                $x += $codeW + $marginH;
                if ($x + $codeW > $pageW - $mRight + 0.01) {
                    $x  = $mLeft;
                    $y += $cellH + $marginV;
                }
            }
            $first = false;

            // New page when cell overflows bottom margin
            if ($y + $thisCellH > $pageH - $mBottom + 0.01) {
                $pdf->AddPage();
                $x = $mLeft;
                $y = $mTop;
            }

            // Empty slot — reserves grid space without printing anything
            if ($code === '') {
                continue;
            }

            $drawY = $y;

            // ── Logo (top of cell, centred) ───────────────────────────────────────
            if ($hasLogo) {
                $lx = $x + ($codeW - $logoW) / 2.0;
                $pdf->Image($logoPath, $lx, $drawY, $logoW, $logoH_mm, 'PNG', '', '', false, 300);
                $drawY += $logoH_mm + 1.5;
            }

            // ── Barcode / QR code (vector, no temp files) ────────────────────────
            if ($type === 'QRcode') {
                $qrSize = min($codeW, $barcodeAreaH);
                $qrX    = $x + ($codeW - $qrSize) / 2.0;
                // 2 mm top quiet zone: shift the QR symbol down inside the cell
                // so it doesn't touch the logo or the cell border.
                $qrTopPad = 2.0;
                $pdf->write2DBarcode(
                    $code,
                    'QRCODE,L',
                    $qrX,
                    $drawY,
                    $qrSize,
                    $qrSize,
                    ['border' => false, 'vpadding' => 'auto', 'hpadding' => 'auto',
                        'fgcolor' => [0, 0, 0], 'bgcolor' => false,
                        'module_width' => 1, 'module_height' => 1],
                    'N'
                );
            } else {
                $tcpdfType = self::$tcpdfTypeMap[$type] ?? 'C128';

                // ── Minimum height for fixed-ratio symbologies ────────────────────
                // EAN-13 and UPC-A have an ISO-mandated minimum bar height.
                // When a logo shrinks $barcodeAreaH below that minimum the bars
                // become malformed. Enforce a 15 mm floor.
                $renderH = $barcodeAreaH;
                if (in_array($tcpdfType, ['EAN13', 'UPCA'], true)) {
                    $renderH = max($renderH, 15.0);
                }

                // ── Per-symbology rendering options ────────────────────────────────
                // POSTNET bars have fixed, proportional widths — they must NOT be
                // stretched to fill the cell; centre them instead.
                // All other types use fitwidth=true so bars fill the available width.
                $isPostnet = ($tcpdfType === 'POSTNET');

                // Quiet zone: 2 mm of white space left and right.
                // Standard requires ≥ 10 narrow-bar widths; 2 mm is a safe minimum.
                // 'padding' accepts [top, right, bottom, left] in mm.
                $quietMm = 2.0;

                // stretchtext=0: human-readable text stays compact under the bars.
                $pdf->write1DBarcode(
                    $code,
                    $tcpdfType,
                    $x,
                    $drawY,
                    $codeW,
                    $renderH,
                    '',
                    [
                        'position'      => '',
                        'align'         => 'C',
                        'stretch'       => false,
                        'fitwidth'      => !$isPostnet,   // POSTNET: fixed-width bars
                        'cellfitalign'  => 'C',           // centre when narrower than cell
                        'border'        => false,
                        // Horizontal quiet zone on both sides; vertical padding = 0
                        'padding'       => [0, $quietMm, 0, $quietMm],
                        'fgcolor'       => [0, 0, 0],
                        'bgcolor'       => false,
                        'text'          => false,
                        'font'          => 'dejavusans',
                        'fontsize'      => max(6.0, $txtPt),
                        // 0 = no stretch — keeps human-readable text compact and readable
                        'stretchtext'   => 0,
                    ],
                    'N'
                );
            }

            // ── Text below symbols, using the same Unicode font for all code types ────
            if ($numLines > 0) {
                $pdf->SetFont('dejavusans', '', max(6.0, $txtPt));
                $textY = $y + $codeH + 1.5;
                foreach ($displayData as $line) {
                    $pdf->SetXY($x, $textY);
                    $height = max($lineH, $pdf->getStringHeight($codeW, (string) $line));
                    $pdf->MultiCell($codeW, $height, (string) $line, 0, 'C', false, 0);
                    $textY += $height + $txtSp;
                }
            }

            // ── Border ───────────────────────────────────────────────────────────
            if ($showBorder) {
                $pdf->Rect($x, $y, $codeW, $thisCellH, 'D');
            }

        } // foreach $codes

        // ── Persist PDF to GLPI tmp dir (pruned after preview token expiry) ──
        // Using GLPI_TMP_DIR instead of the plugin doc dir means the file is
        // transient — it disappears as soon as the browser downloads it.
        // The "Clear cache" button is therefore no longer needed for PDF files.
        $pdfFile = 'barcode_' . (int) $_SESSION['glpiID'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $type) . '_' . bin2hex(random_bytes(16)) . '.pdf';
        $pdfPath = GLPI_TMP_DIR . '/' . $pdfFile;
        if (!empty($settings['auto_print'])) {
            $pdf->IncludeJS('print(true);');
        }
        // TCPDF 7's F destination passes a filename to a directory-only API.
        // Render to a string and write an exclusively created private file instead.
        $contents = $pdf->Output('', 'S');
        $handle = fopen($pdfPath, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to create the label PDF.');
        }
        try {
            chmod($pdfPath, 0600);
            $length = strlen($contents);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($handle, substr($contents, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write the label PDF.');
                }
                $offset += $written;
            }
        } catch (Throwable $e) {
            unlink($pdfPath);
            throw $e;
        } finally {
            fclose($handle);
        }

        return $pdfPath;   // absolute path — served by the session-bound preview endpoint
    }

    // -------------------------------------------------------------------------
    // Tmp PDF registry — token-based path lookup used by send.php
    // -------------------------------------------------------------------------

    /** One-hour, session-bound preview tokens; expired PDF files are pruned on generation. */
    public static function registerTmpPdf(string $absPath): string
    {
        $now = time();
        // Also remove orphaned files left by sessions which have ended.
        foreach (glob(GLPI_TMP_DIR . '/barcode_*.pdf') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $now - 3600) {
                unlink($file);
            }
        }
        foreach ($_SESSION['barcode_pdf_tokens'] ?? [] as $token => $entry) {
            if (!is_array($entry) || $entry['expires'] < $now || !is_file($entry['path'])) {
                unset($_SESSION['barcode_pdf_tokens'][$token]);
            }
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['barcode_pdf_tokens'][$token] = ['path' => $absPath, 'expires' => $now + 3600];
        return $token;
    }

    public static function resolveTmpPdf(string $token): ?string
    {
        $entry = $_SESSION['barcode_pdf_tokens'][$token] ?? null;
        if (!is_array($entry) || $entry['expires'] < time() || !is_file($entry['path'])) {
            unset($_SESSION['barcode_pdf_tokens'][$token]);
            return null;
        }
        return $entry['path'];
    }

    public static function validatePageSize(string $size): string
    {
        if (!PluginBarcodeCustomSize::isReservedName($size)
           && PluginBarcodeCustomSize::resolveDimensions($size) === null) {
            throw new InvalidArgumentException('Unknown page size.');
        }
        return strtoupper($size);
    }

    public static function validateCode(string $type, string $code): void
    {
        $valid = match ($type) {
            'QRcode' => true,
            'Code39' => preg_match('~^[0-9A-Z .$/+%\-]+$~D', $code) === 1,
            'code128' => preg_match('/^[\x20-\x7E]+$/D', $code) === 1,
            'ean13' => preg_match('/^[0-9]{12,13}$/D', $code) === 1,
            'upca' => preg_match('/^[0-9]{11,12}$/D', $code) === 1,
            'int25' => ctype_digit($code) && strlen($code) % 2 === 0,
            'postnet' => ctype_digit($code) && in_array(strlen($code), [5, 9, 11], true),
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('The value cannot be encoded with the selected barcode type.');
        }
    }

    // -------------------------------------------------------------------------
    // Legacy helper (used by showForm / config pages)
    // -------------------------------------------------------------------------

    public function getConfigType(): array
    {
        $pbConfig = new PluginBarcodeConfig();
        return $pbConfig->getConfigType();
    }

    public function getSpecificMassiveActions($checkitem = null): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // MassiveAction callbacks
    // -------------------------------------------------------------------------

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
        PluginBarcodeLabel::processMassiveAction($ma, $item, $ids, false);
    }
}
