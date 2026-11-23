<?php

/** Label workflow and saved defaults. SPDX-License-Identifier: AGPL-3.0-or-later */
final class PluginBarcodeLabel
{
    public const CONTEXT = 'plugin:barcode';
    public const MAX_LABELS = 1000;

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function defaults(): array
    {
        return [
            'enable_barcode' => 1, 'enable_qrcode' => 1,
            'prompt_settings' => 0, 'auto_print' => 0,
            'show_logo' => 1, 'custom_text' => '',
            'serialnumber' => 1, 'displayserialnumber' => 0,
            'inventorynumber' => 1, 'displayinventorynumber' => 1,
            'id' => 1, 'displayid' => 0, 'uuid' => 0, 'displayuuid' => 0,
            'name' => 1, 'displayname' => 1, 'url' => 1, 'displayurl' => 0,
            'inventorynumberURL' => 0, 'qrcodedate' => 0, 'displayqrcodedate' => 0,
            'border' => 0, 'displaylabels' => 0,
        ];
    }

    public static function settings(): array
    {
        return array_replace(self::defaults(), Config::getConfigurationValues(self::CONTEXT));
    }

    public static function isEnabled(string $type): bool
    {
        $settings = self::settings();
        return in_array($type, (new PluginBarcodeBarcode())->getCodeTypes(), true)
            && !empty($settings[$type === 'QRcode' ? 'enable_qrcode' : 'enable_barcode']);
    }

    public static function parameters(string $type): array
    {
        return array_replace((new PluginBarcodeConfig())->getConfigType($type), self::settings(), [
            'type' => $type, 'eliminate' => 0,
        ]);
    }

    public static function showQuickMassiveForm(): bool
    {
        $settings = self::settings();
        if (!empty($settings['prompt_settings'])) {
            return false;
        }
        echo '<p>' . __('Generate labels using the saved defaults.', 'barcode') . '</p>';
        echo Html::submit(__('Generate labels', 'barcode'), ['value' => 'create']);
        return true;
    }

    public static function processMassiveAction(MassiveAction $ma, CommonDBTM $item, array $ids, bool $qr): void
    {
        if ($ma->getAction() !== 'Generate') {
            return;
        }
        Session::checkRight('plugin_barcode_barcode', CREATE);
        $settings = self::settings();
        $type = $qr ? 'QRcode' : (new PluginBarcodeConfig())->getConfig();
        if (!$qr && $type === 'QRcode') {
            $type = 'code128';
        }
        $input = $ma->getInput();
        if (!empty($settings['prompt_settings']) && !$qr) {
            $type = (string) ($input['type'] ?? $type);
            if ($type === 'QRcode') {
                $type = 'code128';
            }
        }
        $options = self::parameters($type);
        if (!empty($settings['prompt_settings'])) {
            $options = array_replace($options, array_intersect_key($input, $options));
        }
        $codes = [];
        $display = [];
        $successful = [];
        $reported = [];
        try {
            if (!self::isEnabled($type)) {
                throw new InvalidArgumentException('This code type is disabled.');
            }
            $skip = (int) ($options['eliminate'] ?? 0);
            if ($skip < 0 || $skip + count($ids) > self::MAX_LABELS) {
                throw new InvalidArgumentException('Print at most 1000 labels at a time.');
            }
            $codes = array_fill(0, $skip, '');
            $display = array_fill(0, $skip, []);
            foreach ($ids as $id) {
                if (!$item->getFromDB($id) || !$item->can($id, READ)) {
                    $reported[] = $id;
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                    continue;
                }
                if ($qr) {
                    $result = (new PluginBarcodeQRcode())->buildQRcodeData($item->getType(), (int) $id, $options);
                    if ($result === false) {
                        $reported[] = $id;
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        continue;
                    }
                    [$code, $lines] = $result;
                } else {
                    $code = $item instanceof CommonITILObject ? (string) $id : (string) ($item->fields['otherserial'] ?? '');
                    $lines = [];
                    if ($code === '') {
                        $reported[] = $id;
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        continue;
                    }
                }
                $successful[] = $id;
                $codes[] = $code;
                $display[] = $lines;
            }
            if ($successful !== []) {
                $path = (new PluginBarcodeBarcode())->printPDF(array_replace($options, [
                    'type' => $type, 'codes' => $codes, 'displayData' => $display,
                ]));
                if (!$path) {
                    throw new RuntimeException('Unable to generate labels.');
                }
                $token = PluginBarcodeBarcode::registerTmpPdf($path);
                $url = Html::getPrefixedUrl('/plugins/barcode') . '/front/send.php?token=' . $token;
                Session::addMessageAfterRedirect('<a target="_blank" rel="noopener noreferrer" href="'
                    . self::escape($url) . '">' . __('Open labels to print', 'barcode') . '</a>');
            }
            foreach ($successful as $id) {
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
            }
        } catch (InvalidArgumentException | RuntimeException $e) {
            foreach ($ids as $id) {
                if (in_array($id, $reported, true)) {
                    continue;
                }
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
            }
            Session::addMessageAfterRedirect(self::escape($e->getMessage()), false, ERROR);
        }
    }

    /** Never instantiate a class supplied by a request before checking its base type. */
    public static function loadItem(string $type, int $id): CommonDBTM
    {
        if ($id <= 0 || !is_subclass_of($type, CommonDBTM::class)) {
            throw new InvalidArgumentException('Invalid item.');
        }
        $reflection = new ReflectionClass($type);
        if ($reflection->isAbstract()) {
            throw new InvalidArgumentException('Invalid item type.');
        }
        $item = new $type();
        $item->check($id, READ);
        return $item;
    }

    public static function generate(CommonDBTM $item, string $type, array $options): string|int
    {
        Session::checkRight('plugin_barcode_barcode', CREATE);
        $item->check($item->getID(), READ);
        if (!self::isEnabled($type)) {
            throw new InvalidArgumentException('This code type is disabled.');
        }
        $options['type'] = $type;
        if ($type === 'QRcode') {
            $result = (new PluginBarcodeQRcode())->buildQRcodeData($item->getType(), $item->getID(), $options);
            if ($result === false) {
                throw new InvalidArgumentException('No QR code content selected.');
            }
            [$code, $lines] = $result;
        } else {
            $code = $item instanceof CommonITILObject ? (string) $item->getID() : (string) ($item->fields['otherserial'] ?? '');
            $lines = [];
            if ($code === '') {
                throw new InvalidArgumentException('Set an inventory number before generating a barcode.');
            }
        }
        return (new PluginBarcodeBarcode())->printPDF(array_replace($options, [
            'codes' => [$code], 'displayData' => [$lines],
        ]));
    }

    /** Render a useful fallback; JavaScript moves it beside the native Actions button. */
    public static function showButtons(array $params): void
    {
        $item = $params['item'] ?? null;
        if (!$item instanceof CommonDBTM || $item->isNewItem()
            || !Session::haveRight('plugin_barcode_barcode', CREATE)
            || !$item->can($item->getID(), READ)
            || (!array_key_exists('otherserial', $item->fields) && !$item instanceof CommonITILObject)) {
            return;
        }
        echo '<div class="plugin-barcode-buttons btn-group mx-2" data-item-id="' . (int) $item->getID() . '">';
        foreach (['barcode' => __('Generate barcode', 'barcode'), 'qrcode' => __('Generate QR code', 'barcode')] as $kind => $label) {
            $type = $kind === 'qrcode' ? 'QRcode' : (new PluginBarcodeConfig())->getConfig();
            if ($kind === 'barcode' && $type === 'QRcode') {
                $type = 'code128';
            }
            if (!self::isEnabled($type)) {
                continue;
            }
            $url = Html::getPrefixedUrl('/plugins/barcode') . '/front/label.php?' . http_build_query([
                'itemtype' => $item->getType(), 'items_id' => $item->getID(), 'type' => $type,
            ]);
            echo '<a class="btn btn-outline-secondary" target="_blank" rel="noopener noreferrer" href="'
                . self::escape($url) . '"><i class="ti ti-' . ($kind === 'qrcode' ? 'qrcode' : 'barcode')
                . '" aria-hidden="true"></i> ' . self::escape($label) . '</a>';
        }
        echo '</div>';
    }
}
