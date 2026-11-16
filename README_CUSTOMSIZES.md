# Custom label sizes

Custom paper sizes are managed under Barcode Configuration → Custom label
sizes. The list is paginated using GLPI's page-length preference. Existing
sizes remain available in each symbology's paper-size dropdown.

Each size has a unique name, width and height in millimetres, and an optional
comment. Names accept letters, digits, hyphens and underscores (20 characters
maximum), excluding built-in TCPDF formats such as A4 and LETTER. Dimensions
must be positive numbers up to 9999 mm; decimal commas are accepted.

Page dimensions and label dimensions are distinct. The per-symbology code
width/height, margins and spacing retain the legacy plugin's **points** unit.
Reduce these values to fit a small thermal label; otherwise generation returns
a clear layout error. Custom text wraps below the symbol and contributes to
the space required. Print at actual size and scan a sample on the target printer.

The table `glpi_plugin_barcode_customsizes` is retained across upgrades and
created idempotently on installation. `PluginBarcodeCustomSize` supplies
options and resolves names to TCPDF dimension arrays. Administrators can delete
rows without completing the new-size form. Select a replacement paper format
for any symbology referencing a deleted size before generating more labels.

Barcode 3.0 requires GLPI 12 and PHP 8.3+. See [README.md](README.md) and
[INSTALL.md](INSTALL.md) for the current compatibility and upgrade guidance.
