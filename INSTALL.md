# Installing Barcode 3.0

Requirements: GLPI 12.x, PHP 8.3+, and the PDF dependencies/font assets supplied
by GLPI. Uploading a company logo additionally requires PHP GD.

1. Place the plugin directory at `plugins/barcode` or `marketplace/barcode`
   beneath the GLPI installation. Do not nest another `barcode` directory.
2. Open Setup → Plugins and install or upgrade Barcode, then enable it.
3. Grant the generation permission to the intended GLPI profiles. Users also
   need read access to each asset they print; entity restrictions still apply.
4. Open the Barcode configuration page. Configure the label format, custom
   text, logo and default QR fields. Print and scan a sample at actual size.

Upgrading from 2.8 retains the existing database tables, custom sizes and
`files/_plugins/barcode/logo.png`. New options use GLPI's plugin configuration
context and have defaults without requiring additional columns. The plugin
version is 3.0.2 so GLPI can run the existing idempotent migration.

This release targets GLPI 12. Keep your previous plugin version while running
GLPI 10 or 11. Do not uninstall to upgrade: uninstall removes plugin tables,
profile rights and saved options.

For a GLPI source checkout, install GLPI's Composer dependencies and run
`bash tools/build_fonts.sh` in the GLPI root. Production distributions should
already contain their generated PDF fonts.

See [README.md](README.md) for workflows, limits, browser printing behavior and
validation instructions. Live GLPI install/upgrade and printer testing are
required before production deployment.

