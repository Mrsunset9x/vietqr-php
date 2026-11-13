<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$count = 0;
foreach (['src', 'tests', 'tools', 'examples'] as $directory) {
    if (!is_dir($root . '/' . $directory)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);
        if ($status !== 0) {
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
            exit($status);
        }
        ++$count;
    }
}
printf("Syntax OK: %d PHP files.\n", $count);
