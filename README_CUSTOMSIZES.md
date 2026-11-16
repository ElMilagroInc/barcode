# Custom label sizes — feature documentation

This change adds UI-managed custom page/label sizes to the Barcode plugin.
Administrators can now define non-standard dimensions (typically thermal
labels: 35 × 20 mm, 66 × 16 mm, etc.) through the configuration page
instead of editing source files.

## Why

The size dropdown rendered by `PluginBarcodeBarcode::showSizeSelect()`
is currently a hardcoded array of ISO/ANSI paper formats. Adding a new
size requires editing that array — a change lost on every plugin upgrade.
Issue #142 and several earlier reports describe users carrying private
patches for the same handful of thermal-label dimensions.

This feature moves the list into a dedicated DB table managed through
the existing plugin config page.

## What

* New table `glpi_plugin_barcode_customsizes` (id, name, width_mm,
  height_mm, comment) — created on install, dropped on uninstall.
* New `CommonDBTM` subclass `PluginBarcodeCustomSize` exposing:
  * `getDropdownOptions(): array` — for merging into existing dropdowns
  * `resolveDimensions(string $name): ?array` — `[width_mm, height_mm]`
    or `null` if the name doesn't correspond to a custom row
  * `showManagementForm(): void` — renders the admin section
* New endpoint `front/customsize.form.php` handling add/delete with
  server-side validation.
* `showSizeSelect()` now merges custom rows into its dropdown array.
* `printPDF()` resolves custom-size names to a `[width, height]` array
  passed straight to TCPDF — no patching of any TCPDF static format
  array required.
* `PluginBarcodeConfig::showForm()` renders the management section at
  the bottom of the existing config page.

## Validation rules

* **Name**: `^[A-Za-z0-9_-]{1,20}$`, must not collide with any of TCPDF's
  built-in page formats (`A0`–`A10`, `B0`–`B10`, `C0`–`C10`, `RA*`,
  `SRA*`, `LETTER`, `LEGAL`, `EXECUTIVE`, `FOLIO`, `4A0`, `2A0`).
* **Width / Height**: positive number, ≤ 9999 mm. Both `35.5` and `35,5`
  are accepted (European decimal comma).
* **Uniqueness**: enforced at the form level (clear error message) and
  at the DB level (`UNIQUE KEY uniq_name`).

## Backward compatibility

* **Additive only.** Without any custom sizes defined, the size dropdown
  and `printPDF()` behave identically to before this change.
* `class_exists('PluginBarcodeCustomSize')` guards in `showSizeSelect()`
  and `printPDF()` ensure the plugin continues to work even if the new
  class file is somehow missing.
* `$DB->tableExists()` guard inside `getDropdownOptions()` /
  `resolveDimensions()` means a partial deploy (new code without
  install having run) still renders the dropdown with built-in formats
  only — no fatal errors.
* Install function uses `if (!$DB->tableExists(...))` so re-running
  `plugin_barcode_install()` on an existing installation is idempotent.

## Compatibility

Tested on GLPI 10.0.x and 11.0.7. No new PHP version requirement
(typed properties were already used by `inc/barcode.class.php` in 2.8.0).

## Files

| File | Status |
|------|--------|
| `inc/customsize.class.php`   | new    |
| `front/customsize.form.php`  | new    |
| `inc/barcode.class.php`      | modified — `showSizeSelect()` merge, `printPDF()` resolution |
| `inc/config.class.php`       | modified — `showForm()` appends management section |
| `hook.php`                   | modified — install/uninstall of the new table |
| `setup.php`                  | modified — class registration for v10/v11 |

No GLPI core files, no vendor files touched.

## i18n

All user-visible strings use `__()` / `_n()` with the existing `'barcode'`
text domain. Maintainers will need to regenerate `locales/barcode.pot`
with the usual `xgettext` / RoboFile target.
