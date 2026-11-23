<?php

/** Focused regression tests with GLPI service doubles and the real PDF/HTTP libraries.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
$autoload = getenv('BARCODE_TEST_AUTOLOAD');
if (!$autoload || !is_file($autoload)) {
    fwrite(STDERR, "Set BARCODE_TEST_AUTOLOAD to a GLPI 12 vendor/autoload.php path.\n");
    exit(1);
}
// The database and authorization services are isolated; TCPDF and Symfony are real.
class CommonGLPI
{
    public static string $rightname = '';
}
class CommonDBTM extends CommonGLPI
{
    public array $fields = [];
    public static array $rows = [];
    public static function getTable($classname = null)
    {
        return 'test';
    }
    public function find(array $criteria)
    {
        return [1 => []];
    }
    public function getFromDB($id)
    {
        $this->fields = self::$rows;
        return true;
    }
    public function getFromDBByCrit($criteria)
    {
        return $this->getFromDB(1);
    }
    public function getEmpty()
    {
        $this->fields = ['id' => 0, 'otherserial' => ''];
    }
    public function getID()
    {
        return $this->fields['id'] ?? 1;
    }
    public function getType()
    {
        return static::class;
    }
    public function check($id, $right)
    {
        if ($id === 99) {
            throw new RuntimeException('Access denied');
        }
        $this->fields = ['id' => $id, 'otherserial' => 'TAG123', 'name' => 'Test & <asset>'];
    }
    public function can($id, $right)
    {
        return $id !== 99;
    }
    public function isNewItem()
    {
        return false;
    }
    public static function getFormURLWithID($id, $full = true)
    {
        return '/front/computer.form.php?id=' . $id;
    }
}
class Profile extends CommonDBTM
{
}
class Computer extends CommonDBTM
{
    public function getFromDB($id)
    {
        $this->fields = ['id' => $id, 'otherserial' => $id === 2 ? '' : 'TAG' . $id, 'name' => 'Asset ' . $id];
        return true;
    }
}
class CommonITILObject extends CommonDBTM
{
}
class MassiveAction
{
    public const CLASS_ACTION_SEPARATOR = ':';
    public const ACTION_OK = 1;
    public const ACTION_KO = 0;
    public const ACTION_NORIGHT = -1;
    public array $done = [];
    public function getAction()
    {
        return 'Generate';
    }
    public function getInput()
    {
        return [];
    }
    public function itemDone($type, $id, $status)
    {
        $this->done[$id] = $status;
    }
}
class Config
{
    public static array $settings = [];
    public static function getConfigurationValues($context)
    {
        return self::$settings;
    }
}
class Session
{
    public static bool $allowed = true;
    public static array $messages = [];
    public static function checkRight($name, $right)
    {
        if (!self::$allowed) {
            throw new RuntimeException('Forbidden');
        }
    }
    public static function haveRight($name, $right)
    {
        return self::$allowed;
    }
    public static function addMessageAfterRedirect($message, ...$args)
    {
        self::$messages[] = $message;
    }
}
class TestDB
{
    public function tableExists($table)
    {
        return false;
    }
}
$DB = new TestDB();
require $autoload;
define('GLPI_TMP_DIR', sys_get_temp_dir() . '/barcode-tests-' . bin2hex(random_bytes(4)));
define('GLPI_PLUGIN_DOC_DIR', GLPI_TMP_DIR);
mkdir(GLPI_TMP_DIR . '/barcode', 0700, true);
require dirname(__DIR__) . '/setup.php';
foreach (['config_type', 'customsize', 'config', 'barcode', 'qrcode', 'profile', 'label'] as $class) {
    require dirname(__DIR__) . '/inc/' . $class . '.class.php';
}
require dirname(__DIR__) . '/hook.php';
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) {
        throw new ErrorException($message, 0, $level, $file, $line);
    }
});
$_SESSION = ['glpiID' => 1];
$CFG_GLPI['url_base'] = 'https://glpi.example.test';
$CFG_GLPI['root_doc'] = '';
CommonDBTM::$rows = [
    'id' => 1, 'type' => 'code128', 'size' => 'A4', 'orientation' => 'Portrait',
    'marginTop' => 30, 'marginBottom' => 30, 'marginLeft' => 30, 'marginRight' => 30,
    'marginHorizontal' => 25, 'marginVertical' => 30, 'maxCodeWidth' => 110,
    'maxCodeHeight' => 70, 'txtSize' => 8, 'txtSpacing' => 3,
];
$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $count++;
}
function rejects(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (InvalidArgumentException | RuntimeException $e) {
        check(true, $message);
        return;
    }
    throw new RuntimeException($message);
}
try {
    $menu = PluginBarcodeBarcode::getMenuContent();
    check($menu['page'] === '/plugins/barcode/front/barcode.form.php', 'Menu uses canonical plugin route');
    check($menu['links']['config'] === '/plugins/barcode/front/config.php', 'Configuration menu route');
    $CFG_GLPI['root_doc'] = '/glpi';
    check(Html::getPrefixedUrl('/plugins/barcode/front/label.php') === '/glpi/plugins/barcode/front/label.php', 'Direct links retain GLPI subdirectory');
    $CFG_GLPI['url_base'] = 'https://glpi.example.test/glpi/';
    $qr = (new PluginBarcodeQRcode())->buildQRcodeData(Computer::class, 1, ['url' => 1, 'inventorynumberURL' => 1]);
    check(str_contains($qr[0], 'https://glpi.example.test/glpi/plugins/barcode/front/checkItemByInv.php?inventoryNumber=TAG123&itemtype=Computer'), 'QR inventory URL uses absolute canonical route');
    $CFG_GLPI['url_base'] = 'https://glpi.example.test';
    $CFG_GLPI['root_doc'] = '';
    $barcode = new PluginBarcodeBarcode();
    foreach (['Code39' => 'TAG123', 'code128' => 'TAG123', 'ean13' => '123456789012',
        'int25' => '123456', 'postnet' => '12345', 'upca' => '12345678901', 'QRcode' => 'https://glpi.example.test'] as $type => $code) {
        $path = $barcode->printPDF(['type' => $type, 'codes' => [$code]]);
        check(is_file($path) && str_starts_with(file_get_contents($path), '%PDF-'), 'Real PDF: ' . $type);
        if (getenv('BARCODE_TEST_SAMPLES_DIR')) {
            copy($path, getenv('BARCODE_TEST_SAMPLES_DIR') . '/' . $type . '.pdf');
        }
    }
    foreach (['Code39' => 'lowercase', 'code128' => "☃", 'ean13' => '123', 'postnet' => '123456', 'upca' => 'x', 'int25' => '123'] as $type => $code) {
        rejects(fn () => PluginBarcodeBarcode::validateCode($type, $code), 'Reject invalid ' . $type);
    }
    rejects(fn () => $barcode->printPDF(['code' => 'TAG', 'nb' => 1001]), 'Copy limit');
    rejects(fn () => $barcode->printPDF(['codes' => array_fill(0, 1001, 'TAG')]), 'Batch limit');
    rejects(fn () => PluginBarcodeBarcode::validatePageSize('../../a4'), 'Page size allowlist');
    Config::$settings = ['enable_qrcode' => 0];
    check(!PluginBarcodeLabel::isEnabled('QRcode'), 'Disabled QR codes');
    rejects(fn () => $barcode->printPDF(['type' => 'QRcode', 'codes' => ['x']]), 'Enforce disabled QR');
    check(count(plugin_barcode_MassiveActions(Computer::class)) === 1, 'Hide disabled QR action');
    Config::$settings = ['enable_barcode' => 0];
    check(count(plugin_barcode_MassiveActions(Computer::class)) === 1, 'Hide disabled barcode action');
    rejects(fn () => $barcode->printPDF(['code' => 'TAG']), 'Enforce disabled barcode');
    Config::$settings = ['enable_barcode' => 0, 'enable_qrcode' => 0];
    check(plugin_barcode_MassiveActions(Computer::class) === [], 'Disable both');
    Config::$settings = [];
    $ma = new MassiveAction();
    PluginBarcodeLabel::processMassiveAction($ma, new Computer(), [1, 2, 99], false);
    check($ma->done === [2 => MassiveAction::ACTION_KO, 99 => MassiveAction::ACTION_NORIGHT, 1 => MassiveAction::ACTION_OK], 'Bulk reports missing inventory and denied records independently');
    Config::$settings = ['enable_barcode' => 0];
    $ma = new MassiveAction();
    PluginBarcodeLabel::processMassiveAction($ma, new Computer(), [1, 2], false);
    check($ma->done === [1 => MassiveAction::ACTION_KO, 2 => MassiveAction::ACTION_KO], 'Disabled bulk request fails every selected item');
    Config::$settings = [];
    check(PluginBarcodeLabel::settings()['prompt_settings'] === 0, 'One click by default');
    $item = PluginBarcodeLabel::loadItem(Computer::class, 1);
    check(is_file(PluginBarcodeLabel::generate($item, 'code128', PluginBarcodeLabel::parameters('code128'))), 'One click asset PDF');
    rejects(fn () => PluginBarcodeLabel::loadItem(stdClass::class, 1), 'Reject arbitrary classes');
    rejects(fn () => PluginBarcodeLabel::loadItem(Computer::class, 99), 'Respect record access');
    Session::$allowed = false;
    rejects(fn () => $barcode->printPDF(['code' => 'TAG']), 'Generation requires permission');
    check(plugin_barcode_MassiveActions(Computer::class) === [], 'Actions require permission');
    Session::$allowed = true;
    $raw = '<script>alert("x")</script> & El Milagro';
    check(PluginBarcodeLabel::escape($raw) === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; El Milagro', 'Escape configured text');
    Config::$settings = ['custom_text' => $raw . "\nPropiedad de El Milagro – devolución", 'auto_print' => 1];
    $path = $barcode->printPDF(['code' => 'TAG123']);
    check(is_file($path), 'Plain Unicode text and print action');
    if (getenv('BARCODE_TEST_SAMPLE')) {
        copy($path, getenv('BARCODE_TEST_SAMPLE'));
    }
    $pdfText = file_get_contents($path);
    check(str_contains($pdfText, '/JavaScript'), 'PDF contains optional print request');
    $token = PluginBarcodeBarcode::registerTmpPdf($path);
    check(PluginBarcodeBarcode::resolveTmpPdf($token) === $path, 'Token resolves');
    check(PluginBarcodeBarcode::resolveTmpPdf($token) === $path, 'PDF can reload for printing');
    $_GET['token'] = $token;
    $response = require dirname(__DIR__) . '/front/send.php';
    check($response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse, 'Native binary response');
    check(str_starts_with($response->headers->get('Content-Disposition'), 'inline;'), 'Preview instead of download');
    check($response->headers->get('Content-Type') === 'application/pdf', 'PDF MIME type');
    $tokens = $_SESSION['barcode_pdf_tokens'];
    $_SESSION['barcode_pdf_tokens'] = [];
    $response = require dirname(__DIR__) . '/front/send.php';
    check($response->getStatusCode() === 404, 'Another session cannot read preview');
    $_SESSION['barcode_pdf_tokens'] = $tokens;

    $_SESSION['barcode_pdf_tokens'][$token]['expires'] = time() - 1;
    check(PluginBarcodeBarcode::resolveTmpPdf($token) === null, 'Expired token rejected');
    check(PluginBarcodeBarcode::resolveTmpPdf('other-session') === null, 'Unknown token rejected');
    $image = imagecreatetruecolor(200, 60);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 199, 59, $white);
    $red = imagecolorallocate($image, 190, 20, 40);
    imagestring($image, 5, 45, 20, 'TEST LOGO', $red);
    imagepng($image, GLPI_PLUGIN_DOC_DIR . '/barcode/logo.png');
    Config::$settings = ['show_logo' => 1];
    check(is_file($barcode->printPDF(['code' => 'TAG123'])), 'PNG logo above barcode');
    // The old renderer silently omitted the logo when the symbol was shorter
    // than its arbitrary 8 mm minimum. Check the PDF image resource, not merely
    // that some PDF file was produced.
    CommonDBTM::$rows['maxCodeHeight'] = 10;
    CommonDBTM::$rows['txtSize'] = 4;
    CommonDBTM::$rows['txtSpacing'] = 0;
    Config::$settings = ['show_logo' => 1, 'custom_text' => 'support.el-milagro.net'];
    $shortLabel = $barcode->printPDF(['code' => 'EMI-0597']);
    check(preg_match('~/Subtype\s*/Image\b~', file_get_contents($shortLabel)) === 1, 'Short thermal label actually embeds the logo');
    if (getenv('BARCODE_TEST_LOGO_SAMPLE')) {
        copy($shortLabel, getenv('BARCODE_TEST_LOGO_SAMPLE'));
    }
    Config::$settings['show_logo'] = 0;
    $withoutLogo = $barcode->printPDF(['code' => 'EMI-0597']);
    check(preg_match('~/Subtype\s*/Image\b~', file_get_contents($withoutLogo)) === 0, 'Logo switch omits the image resource');
    CommonDBTM::$rows['maxCodeHeight'] = 70;
    CommonDBTM::$rows['txtSize'] = 8;
    CommonDBTM::$rows['txtSpacing'] = 3;
    CommonDBTM::$rows['maxCodeWidth'] = 2000;
    rejects(fn () => $barcode->printPDF(['code' => 'TAG123']), 'Oversized label rejected');
    CommonDBTM::$rows['maxCodeWidth'] = 110;
    Config::$settings = [];
    $cause = new InvalidArgumentException('Inventory <tag> is invalid.');
    try {
        PluginBarcodeLabel::throwGenerationError($cause);
    } catch (\Glpi\Exception\Http\HttpException $e) {
        check($e->getStatusCode() === 400, 'Validation errors use HTTP 400');
        check($e->getPrevious() === $cause, 'Original generation error remains in exception chain');
        check($e->getMessageToDisplay() === $cause->getMessage(), 'Validation message reaches GLPI for Twig escaping');
    }
    $cause = new RuntimeException('Internal file path must only reach the log.');
    try {
        PluginBarcodeLabel::throwGenerationError($cause);
    } catch (\Glpi\Exception\Http\HttpException $e) {
        check($e->getStatusCode() === 500 && $e->getPrevious() === $cause, 'Server failures preserve their cause');
        check(!str_contains($e->getMessageToDisplay(), 'Internal file path'), 'Internal failures have a safe user message');
    }
    $denied = new \Glpi\Exception\Http\AccessDeniedHttpException('Denied by record permissions.');
    try {
        PluginBarcodeLabel::throwGenerationError($denied);
    } catch (\Glpi\Exception\Http\AccessDeniedHttpException $e) {
        check($e === $denied && $e->getStatusCode() === 403, 'Existing authorization status is preserved');
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['itemtype' => stdClass::class, 'items_id' => 1, 'type' => 'code128'];
    try {
        require dirname(__DIR__) . '/front/label.php';
        throw new RuntimeException('Invalid item did not fail.');
    } catch (\Glpi\Exception\Http\HttpException $e) {
        check($e->getStatusCode() === 400 && $e->getMessageToDisplay() === 'Invalid item.', 'Asset endpoint exposes original validation error without calling removed Html method');
    }
    $_GET = ['itemtype' => Computer::class, 'items_id' => 1, 'type' => 'code128'];
    $response = require dirname(__DIR__) . '/front/label.php';
    check($response instanceof Symfony\Component\HttpFoundation\RedirectResponse, 'Asset endpoint still opens a successful preview');
    CommonDBTM::$rows['maxCodeWidth'] = 2000;
    try {
        require dirname(__DIR__) . '/front/label.php';
        throw new RuntimeException('Oversized label did not fail.');
    } catch (\Glpi\Exception\Http\HttpException $e) {
        check($e->getStatusCode() === 400 && str_contains($e->getMessageToDisplay(), 'available after margins'), 'Asset endpoint reports measured page-fit failure');
    }
    CommonDBTM::$rows['maxCodeWidth'] = 110;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['type' => 'Code39', 'code' => 'lowercase', 'nb' => 1];
    try {
        require dirname(__DIR__) . '/front/barcode.form.php';
        throw new RuntimeException('Invalid barcode did not fail.');
    } catch (\Glpi\Exception\Http\HttpException $e) {
        check($e->getStatusCode() === 400 && str_contains($e->getMessageToDisplay(), 'cannot be encoded'), 'Manual printing endpoint exposes original validation error');
    }
    echo "Passed $count regression checks with real TCPDF.\n";
} finally {
    foreach (glob(GLPI_TMP_DIR . '/*.pdf') ?: [] as $file) {
        unlink($file);
    }
    foreach (glob(GLPI_TMP_DIR . '/barcode/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir(GLPI_TMP_DIR . '/barcode');
    rmdir(GLPI_TMP_DIR);
}
