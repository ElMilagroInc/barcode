# GLPI Barcode plugin — 2.8.0-native + custom label sizes

This is a community build of the GLPI **barcode** plugin that bundles
together:

1. The **2.8.0-native** rework attached to upstream issue #142
   ([pluginsGLPI/barcode#142](https://github.com/pluginsGLPI/barcode/issues/142)),
   which removes the bundled TCPDF and uses GLPI's own vendor copy.
2. **Two regression fixes** on top of that rework so the plugin works on
   GLPI 10 as well as GLPI 11 (the rework alone only worked on GLPI 11).
3. A new feature — **UI-managed custom label sizes** — so admins can
   define non-standard thermal-label dimensions (35 × 20 mm, 66 × 16 mm,
   etc.) through the plugin's configuration page instead of editing
   source files.

It is **not an official release**. The official upstream is still on
2.7.1 and the rework + this build are pending review by the
plugin maintainers. Use at your own risk on production.

## Compatibility

| GLPI    | Status |
|---------|--------|
| 10.0.x  | tested on 10.0.25 (PHP 8.1.2, nginx + PHP-FPM) |
| 11.0.x  | tested on 11.0.7 |

PHP 7.4 or newer is required (same as upstream).

## Installation — fresh install

1. Stop your web server (optional but recommended).
2. Extract the archive into your GLPI plugins directory:
   - GLPI 10 with marketplace: `<glpi_root>/marketplace/barcode/`
   - GLPI 11 with marketplace: `<glpi_root>/marketplace/barcode/`
   - Legacy install:           `<glpi_root>/plugins/barcode/`
3. Make sure the new `barcode/` folder is owned by your web server user
   (typically `www-data`):
   ```bash
   chown -R www-data:www-data <glpi_root>/marketplace/barcode
   ```
4. Restart your web server (if you stopped it).
5. In GLPI: **Setup → Plugins** — find Barcode, click **Install**, then
   **Enable**.

## Upgrading from an earlier version of the plugin

If you currently have any version of the barcode plugin installed
(stock 2.7.1, your own custom 2.7.x with manual patches, or the
2.8.0-native rework on its own):

1. **Back up** the two plugin tables that store your per-barcode-type
   tuning (margins, max code dimensions, text spacing, etc.) — these are
   recreated with default values when you uninstall:
   ```sql
   CREATE TABLE glpi_plugin_barcode_configs_backup       AS SELECT * FROM glpi_plugin_barcode_configs;
   CREATE TABLE glpi_plugin_barcode_configs_types_backup AS SELECT * FROM glpi_plugin_barcode_configs_types;
   ```
2. **Setup → Plugins → Uninstall** the existing Barcode plugin.
3. Replace the plugin folder with the contents of this archive.
4. **Setup → Plugins → Install + Enable**.
5. (Optional) Restore your tuned values from the backup tables if needed:
   ```sql
   TRUNCATE glpi_plugin_barcode_configs_types;
   INSERT INTO glpi_plugin_barcode_configs_types SELECT * FROM glpi_plugin_barcode_configs_types_backup;
   ```

## What's different from upstream 2.7.1

This build inherits all of the 2.8.0-native rework's changes:

- No more bundled TCPDF — uses GLPI's vendor copy
  (`vendor/tecnickcom/tcpdf`)
- Restructured classes (`PluginBarcodeBarcode`, `PluginBarcodeConfig`,
  `PluginBarcodeConfigType`, etc.)
- Tables renamed to plural form
- Modernised PHP (typed properties, return types)
- Fully translatable

Plus this build adds on top:

- **Fix:** GLPI 10 bootstrap restored in all `front/*.php` files
  (was removed by the rework on the assumption only GLPI 11's Symfony
  router would be used).
- **Fix:** duplicate `maxCodeHeight` input in the config form.
- **New:** custom label sizes management UI — see
  `README_CUSTOMSIZES.md`.

## Using custom label sizes

After install, go to **Setup → Plugins → Barcode → Configure**.
Scroll to the bottom — there's a section titled **Custom label sizes**.

Add a size with:

- **Name:** a short identifier (e.g. `35-20`, `thermal-66`). Must be
  unique, 1–20 chars, alphanumeric / dash / underscore. Cannot collide
  with built-in TCPDF formats (A4, LETTER, etc.).
- **Width / Height:** in millimetres. Accepts both `35.5` and `35,5`.
- **Comment:** optional description.

The new size immediately appears in the **Page size** dropdown for
every barcode type. Used in both single-barcode and massive
barcode-generation flows.

Custom sizes are stored in `glpi_plugin_barcode_customsizes` and
survive plugin upgrades. No source-file edits required.

See `README_CUSTOMSIZES.md` for full technical detail.

## Reporting issues

This build is a community contribution sitting in upstream issue #142.
If you find a problem with it, please comment there:

  https://github.com/pluginsGLPI/barcode/issues/142

For issues with the underlying barcode plugin itself, the official
tracker is:

  https://github.com/pluginsGLPI/barcode/issues

## License

AGPL-3.0 (inherited from upstream).
