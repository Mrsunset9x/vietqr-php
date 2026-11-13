<?php

declare(strict_types=1);

// Installs this package into a fresh consumer, outside the working tree.
// Pass --with-renderer to verify the optional integration as well.
$root = dirname(__DIR__);
$withRenderer = in_array('--with-renderer', $argv, true);
$directory = sys_get_temp_dir() . '/vietqr-consumer-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0777, true)) {
    throw new RuntimeException('Cannot create consumer directory.');
}
// Test the distributable, not a path mirror of a developer tree containing vendor/cache.
$archiveDirectory = $directory . '/dist';
mkdir($archiveDirectory);
passthru('composer archive --format=zip --file=package --no-interaction --dir=' . escapeshellarg($archiveDirectory)
    . ' --working-dir=' . escapeshellarg($root), $status);
if ($status !== 0) {
    exit($status);
}
$zip = new ZipArchive();
if ($zip->open($archiveDirectory . '/package.zip') !== true) {
    throw new RuntimeException('Cannot open package archive.');
}
for ($i = 0; $i < $zip->numFiles; ++$i) {
    $entry = $zip->getNameIndex($i);
    if ($entry === false || preg_match('~(^|/)(vendor|build|\.git|\.phpstan\.cache|\.phpunit\.cache)(/|$)~', $entry)) {
        throw new RuntimeException('Development artifacts leaked into the distribution.');
    }
}
$source = $directory . '/package';
if (!$zip->extractTo($source)) {
    throw new RuntimeException('Cannot extract package archive.');
}
$zip->close();
$requirements = ['mrsunset9x/vietqr-php' => 'dev-main'];
if ($withRenderer) {
    $requirements['bacon/bacon-qr-code'] = '^3.0';
}
$manifest = [
    'name' => 'vietqr-test/consumer',
    'repositories' => [[
        'type' => 'path', 'url' => str_replace('\\', '/', $source),
        'options' => ['symlink' => false, 'versions' => ['mrsunset9x/vietqr-php' => 'dev-main']],
    ]],
    'require' => $requirements,
    'config' => ['allow-plugins' => false],
];
file_put_contents($directory . '/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
// Composer may be a .bat on Windows. Only trusted, shell-escaped paths are passed.
passthru('composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist --working-dir=' . escapeshellarg($directory), $status);
if ($status !== 0) {
    exit($status);
}
$lock = json_decode(file_get_contents($directory . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$expected = $withRenderer ? ['bacon/bacon-qr-code', 'dasprid/enum', 'mrsunset9x/vietqr-php'] : ['mrsunset9x/vietqr-php'];
$installed = array_column($lock['packages'], 'name');
sort($installed);
if ($installed !== $expected || $lock['packages-dev'] !== []) {
    throw new RuntimeException('Unexpected production dependencies: ' . implode(', ', $installed));
}
if (is_dir($directory . '/vendor/mrsunset9x/vietqr-php/vendor')) {
    throw new RuntimeException('Nested development vendor directory leaked into consumer.');
}
$smoke = file_get_contents($root . '/tools/core-smoke.php');
$smoke = str_replace("dirname(__DIR__) . '/vendor/autoload.php'", "__DIR__ . '/vendor/autoload.php'", $smoke);
file_put_contents($directory . '/smoke.php', $smoke);
passthru(escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg($directory . '/smoke.php'), $status);
if ($status !== 0) {
    exit($status);
}
if ($withRenderer) {
    $script = <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$payment = new VietQr\Payment('970403', '0011012345678', 180000);
$renderer = new VietQr\Rendering\BaconQrRenderer();
if (!str_contains($renderer->svg($payment), '<svg') || !str_starts_with($renderer->png($payment), "\x89PNG")) {
    throw new RuntimeException('Consumer rendering failed.');
}
echo "PASS consumer SVG and PNG\n";
PHP;
    file_put_contents($directory . '/render.php', $script);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($directory . '/render.php'), $status);
    if ($status !== 0) {
        exit($status);
    }
}
echo 'PASS fresh ', $withRenderer ? 'renderer' : 'core', ' install: ', $directory, PHP_EOL;
