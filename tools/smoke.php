<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function smoke_fail(array &$failures, string $message): void
{
    $failures[] = $message;
    fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
}

function smoke_pass(string $message): void
{
    fwrite(STDOUT, "OK: {$message}" . PHP_EOL);
}

$phpFiles = glob($root . DIRECTORY_SEPARATOR . '*.php') ?: [];
sort($phpFiles);

foreach ($phpFiles as $file) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        smoke_fail($failures, 'PHP lint failed for ' . basename($file));
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        continue;
    }

    smoke_pass('PHP lint passed for ' . basename($file));
}

require_once $root . DIRECTORY_SEPARATOR . 'config.php';

$config = dashboard_config();
$requiredPaths = [
    'app.pageTitle',
    'design.accentColor',
    'telemetry.upstreamUrl',
    'telemetry.atsUpstreamUrl',
    'telemetry.cacheFile',
    'telemetry.atsCacheFile',
    'snapshots.directory',
    'snapshots.atsDirectory',
    'snapshots.stateFile',
    'snapshots.atsStateFile',
    'snapshots.atsFilenamePrefix',
    'frontend.telemetryEndpoint',
    'frontend.mapSources',
];

foreach ($requiredPaths as $path) {
    if (dashboard_config_value($path) === null) {
        smoke_fail($failures, "Missing config path {$path}");
        continue;
    }

    smoke_pass("Config path exists: {$path}");
}

$themeVariables = dashboard_design_theme_variables((array) ($config['design'] ?? []));
foreach (['--teal', '--blue', '--amber', '--good', '--bad', '--ui-font-family'] as $cssVariable) {
    if (!array_key_exists($cssVariable, $themeVariables)) {
        smoke_fail($failures, "Missing theme variable {$cssVariable}");
        continue;
    }

    smoke_pass("Theme variable exists: {$cssVariable}");
}

$routerSource = file_get_contents($root . DIRECTORY_SEPARATOR . 'router.php');
if ($routerSource === false) {
    smoke_fail($failures, 'Unable to read router.php');
} else {
    foreach (["'/ats' => 'indexV2Ats.php'", "'/ets2' => 'indexV2.php'"] as $expectedAlias) {
        if (!str_contains($routerSource, $expectedAlias)) {
            smoke_fail($failures, "Missing router alias {$expectedAlias}");
            continue;
        }

        smoke_pass("Router alias exists: {$expectedAlias}");
    }

    foreach (["'config.local.php'", "'.git'", "'tmp'", '$extension !== \'\''] as $expectedGuard) {
        if (!str_contains($routerSource, $expectedGuard)) {
            smoke_fail($failures, "Missing router guard {$expectedGuard}");
            continue;
        }

        smoke_pass("Router guard exists: {$expectedGuard}");
    }
}

if ($failures !== []) {
    fwrite(STDERR, PHP_EOL . count($failures) . ' smoke check(s) failed.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, PHP_EOL . 'Smoke checks passed.' . PHP_EOL);
