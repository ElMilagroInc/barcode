# Barcode for GLPI 12

Generate printable barcode and QR labels for GLPI assets. This El Milagro
edition requires **GLPI 12.x and PHP 8.3+**. Keep the previous 2.x version for
GLPI 10/11; 3.0 deliberately uses GLPI 12 class contracts and
Symfony file responses. No GLPI core files are modified.

## Usage

On a saved computer, monitor, printer, phone or other asset with an inventory
number field, use **Generate barcode** or **Generate QR code** beside **Actions**.
The link opens a new tab and generates a PDF with saved defaults, without a
settings prompt. A barcode requires a nonempty inventory number; QR codes can
encode the selected asset fields and its GLPI link. ITIL objects use their ID
for barcodes. Read access to the record and the plugin's generation permission
are required, including entity restrictions.

Bulk generation remains available through the asset list's Actions menu.
With prompts disabled, its dialog only asks you to start generation. The result
contains an **Open labels to print** link that opens the PDF in a new tab.
The bulk workflow uses GLPI's redirect/message mechanism; it does not attempt
to bypass browser popup protection. Free-form barcode printing is also
available under Tools → Barcode.

## Settings

Open Setup → Plugins → Barcode → Configuration. Settings are split into
General, one page per symbology, and Custom label sizes. Custom sizes use GLPI's
pager and the user's page-length preference.

General settings include:

- Independently enable/disable barcodes and QR codes. Disabled types disappear
  from menus and buttons and are rejected by generation endpoints.
- Prompt for settings before generation, or use saved defaults directly.
- Optional automatic print request embedded in generated PDFs. The browser's
  PDF viewer decides whether to honor it; otherwise use its Print button.
- Default barcode symbology and default QR fields to encode/display.
- Plain custom text below every label, including Unicode and up to five lines
  (500 characters total). Text is escaped in settings and drawn with PDF text
  primitives: HTML, JavaScript and template expressions are never evaluated.
- PNG logo above the symbol, with a separate visibility switch. Uploads are
  validated and re-encoded, limited to 2 MiB and 2000 × 2000 pixels. PHP GD is
  required for uploading logos. The logo never overlays the barcode or QR data.
  Its height is reserved separately above the symbol, including short thermal
  barcodes. The renderer bounds it to 60% of the label width and 10 mm tall.

Per-symbology pages retain the existing paper size, orientation, margins,
barcode dimensions, font size and spacing. Dimensions from the legacy plugin
remain in **points**, while custom paper sizes are in **millimetres**. Layouts
that cannot fit the selected page are rejected rather than clipped silently.
The symbol height and text size are honored without forcing taller bars or a
6-point font. For compact labels, start with a 4-point font and zero text spacing,
then verify readability on your printer. The logo adds to the total label height;
if the complete label cannot fit, generation reports an error rather than hiding
the logo. Print at actual size (100%) and scan a sample before a production run.

PDFs open inline rather than forcing a download. Browser preferences may
still choose to download PDFs. Preview tokens are session-bound and expire
after one hour; files are pruned on subsequent generation. Tokens remain
reusable during that hour for PDF viewer reloads and printing.

## Upgrade

1. Deploy this directory as `plugins/barcode` or `marketplace/barcode` on a
   GLPI 12 installation, retaining the existing plugin data directory.
2. Run the Barcode upgrade in Setup → Plugins (version 3.0.3).
3. Review General settings and each symbology's layout, then print and scan a
   sample label. Existing type settings, custom sizes and `logo.png` are retained.

GLPI 12 supplies TCPDF 7 and its font assets. A source checkout of GLPI must
have its dependencies installed and `bash tools/build_fonts.sh` completed;
this is a GLPI build step, not an extra plugin vendor directory. The plugin
avoids TCPDF 7's filename/directory mismatch in its file destination by rendering
to a string and writing a private, exclusively created temporary file.

## Development and checks

With a GLPI 12 checkout available:

```sh
composer install
export BARCODE_TEST_AUTOLOAD=/path/to/glpi/vendor/autoload.php
composer test:glpi
composer test
composer lint
```

The regression suite uses isolated GLPI database/authorization services and
real GLPI 12 TCPDF and Symfony libraries. It checks all seven symbologies,
input limits, permissions, disabling code families, label geometry, Unicode,
plain custom text, logo rendering, optional print actions and token lifetime.
`test:glpi` loads the plugin classes against the real GLPI parent classes.
CI runs these checks on PHP 8.3, 8.4 and 8.5 against both the original
GLPI 12 baseline and a newer core revision. Menu callbacks are executed against
real GLPI classes, and explicit Plugin API calls are checked for availability.

Generation errors use GLPI’s native error controller. Validation messages include
page-fit measurements; internal failures retain their cause in the GLPI log.
CI exercises both successful preview redirects and failed endpoint requests.

Before deployment, verify on a live GLPI 12 test installation: upgrade from
2.8, fresh install, settings persistence, read-only/entity-restricted profiles,
header placement on your asset forms, custom-size pagination and the target
browser/printer's PDF behavior. The isolated tests do not replace those checks.

Original authors and license: see AUTHORS.txt and LICENSE (AGPL-3.0-or-later).

