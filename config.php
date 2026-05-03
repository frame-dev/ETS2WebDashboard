<?php

declare(strict_types=1);

function dashboard_config_defaults(): array
{
    return [
        'app' => [
            'pageTitle' => 'ETS2 Command Dashboard',
            'metaDescription' => 'Live ETS2 dashboard for telemetry, route status, systems, and map tracking.',
            'heroEyebrow' => 'Euro Truck Simulator 2',
            'heroTitle' => 'Command dashboard online',
            'heroSummary' => 'Preparing a live operator view from your local telemetry feed.',
        ],
        'design' => [
            'accentColor' => '#54EFC7',
            'accentSecondaryColor' => '#79C7FF',
            'accentWarmColor' => '#FFBF69',
            'successColor' => '#43D79F',
            'dangerColor' => '#FF7050',
            'fontFamily' => '"Space Grotesk", "Aptos", "Segoe UI", sans-serif',
            'fontScale' => 1.0,
            'heroMapPlayerFontSizeRem' => 0.95,
            'panelRadiusPx' => 28,
            'glassBlurPx' => 26,
        ],
        'telemetry' => [
            'upstreamUrl' => 'http://127.0.0.1:31377/api/ets2/telemetry',
            'atsUpstreamUrl' => 'http://127.0.0.1:31377/api/ets2/telemetry',
            'refreshIntervalMs' => 250,
            'requestTimeoutMs' => 4500,
            'jsonPrettyPrint' => true,
            'cacheEnabled' => true,
            'cacheTtlMs' => 10000,
            'cacheFile' => __DIR__ . '/tmp/telemetry-cache.json',
            'atsCacheFile' => __DIR__ . '/tmp/telemetry-ats-cache.json',
        ],
        'snapshots' => [
            'enabled' => false,
            'intervalMs' => 60000,
            'directory' => __DIR__ . '/snapshots',
            'atsDirectory' => __DIR__ . '/snapshots/ats',
            'stateFile' => __DIR__ . '/tmp/snapshot-state.json',
            'atsStateFile' => __DIR__ . '/tmp/snapshot-ats-state.json',
            'prettyPrint' => true,
            'filenamePrefix' => 'telemetry-',
            'atsFilenamePrefix' => 'telemetry-ats-',
            'filenamePattern' => '{prefix}{date}-{ms}Z.{ext}',
            'timestampFormat' => 'Y-m-d\TH-i-s',
        ],
        'frontend' => [
            'telemetryEndpoint' => 'telemetry.php?format=json',
            'remoteTelemetryUrls' => [],
            'playersRefreshMs' => 250,
            'playersRadiusDefault' => 5500,
            'playersServerDefault' => 50,
            'telemetryPolling' => [
                'backoffStepMs' => 0,
                'maxBackoffMs' => 250,
                'hiddenIntervalMs' => 250,
                'minimumIntervalMs' => 250,
                'cacheMultiplier' => 1,
            ],
            'speedRing' => [
                'maxDisplayKph' => 130,
                'overspeedToleranceKph' => 2,
                'trendSensitivityKph' => 0.8,
            ],
            'popupEvents' => [
                'showJobStarted' => true,
                'showJobFinished' => true,
            ],
            'storageKeys' => [
                'activeTab' => 'ets2-dashboard-active-tab',
                'mapPreferences' => 'ets2-dashboard-map-preferences',
            ],
            'routePlanner' => [
                'averageKph' => 63,
                'realTimeScale' => 17.5,
            ],
            'mapDefaults' => [
                'worldZoom' => 4,
                'worldFollowTruck' => true,
                'heroZoom' => 3,
                'heroFollowTruck' => true,
            ],
            'mapBounds' => [
                'minX' => -94118.3,
                'maxX' => 128280,
                'minZ' => -102857,
                'maxZ' => 57201.3,
            ],
            'mapTiles' => [
                'baseUrlCandidates' => ['https://framedev.ch/tilesMaps/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081'],
                'configNames' => ['config.json', 'TileMapInfo.json'],
                'overzoomSteps' => 3,
                'retryDelayMs' => 8000,
            ],
            'atsMapTiles' => [
                'baseUrlCandidates' => ['https://framedev.ch/tilesMaps/atsTiles/'],
                'configNames' => ['TileMapInfo.json'],
                'overzoomSteps' => 3,
                'retryDelayMs' => 8000,
            ],
            'mapSources' => [
                [
                    'id' => 'standard',
                    'name' => 'Standard',
                    'baseUrlCandidates' => ['https://framedev.ch/tilesMaps/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081'],
                    'configNames' => ['TileMapInfo.json', 'config.json'],
                    'mapBounds' => [
                        'minX' => -94621.8047,
                        'maxX' => 79370.13,
                        'minZ' => -80374.9453,
                        'maxZ' => 93616.99,
                    ],
                    'overzoomSteps' => 3,
                    'retryDelayMs' => 8000,
                ],
                [
                    'id' => 'promods',
                    'name' => 'ProMods',
                    'baseUrlCandidates' => ['https://framedev.ch/tilesMaps/tilespromods/'],
                    'configNames' => ['TileMapInfo.json', 'config.json'],
                    'mapBounds' => [
                        'minX' => -135110.156,
                        'maxX' => 168923.75,
                        'minZ' => -190095.016,
                        'maxZ' => 113938.891,
                    ],
                    'overzoomSteps' => 3,
                    'retryDelayMs' => 8000,
                ],
            ],
            'atsMapSources' => [
                [
                    'id' => 'ats',
                    'name' => 'ATS',
                    'baseUrlCandidates' => ['https://framedev.ch/tilesMaps/atsTiles/'],
                    'configNames' => ['TileMapInfo.json'],
                    'mapBounds' => [
                        'minX' => -120098.891,
                        'maxX' => 31036.5781,
                        'minZ' => -81673.9844,
                        'maxZ' => 69461.4844,
                    ],
                    'overzoomSteps' => 3,
                    'retryDelayMs' => 8000,
                ],
            ],
        ],
    ];
}

function dashboard_array_merge_recursive(array $base, array $overrides): array
{
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = dashboard_array_merge_recursive($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function dashboard_env_bool(string $name, bool $default): bool
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function dashboard_env_int(string $name, int $default): int
{
    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        return $default;
    }

    if (!is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function dashboard_env_string(string $name, string $default): string
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    $trimmed = trim((string) $value);
    return $trimmed === '' ? $default : $trimmed;
}

function dashboard_clamp_int(mixed $value, int $default, int $min, int $max): int
{
    if (!is_int($value) && !is_float($value) && !(is_string($value) && trim($value) !== '' && is_numeric($value))) {
        return $default;
    }

    return max($min, min($max, (int) $value));
}

function dashboard_clamp_float(mixed $value, float $default, float $min, float $max): float
{
    if (!is_int($value) && !is_float($value) && !(is_string($value) && trim($value) !== '' && is_numeric($value))) {
        return $default;
    }

    return max($min, min($max, (float) $value));
}

function dashboard_sanitize_hex_color(string $value, string $default): string
{
    $normalized = strtoupper(trim($value));

    if (preg_match('/^#(?:[0-9A-F]{3}|[0-9A-F]{6})$/', $normalized) === 1) {
        return $normalized;
    }

    return strtoupper($default);
}

function dashboard_sanitize_font_family(string $value, string $default): string
{
    $normalized = preg_replace('/[^A-Za-z0-9\s,"\'.-]+/', '', trim($value));
    $normalized = is_string($normalized) ? preg_replace('/\s+/', ' ', $normalized) : null;
    $normalized = is_string($normalized) ? trim($normalized) : '';

    return $normalized !== '' ? $normalized : $default;
}

function dashboard_design_theme_variables(array $design): array
{
    $defaultFontFamily = '"Space Grotesk", "Aptos", "Segoe UI", sans-serif';
    $fontScale = dashboard_clamp_float($design['fontScale'] ?? 1.0, 1.0, 0.85, 1.4);
    $heroMapPlayerFontSizeRem = dashboard_clamp_float($design['heroMapPlayerFontSizeRem'] ?? 0.95, 0.95, 0.6, 1.4);
    $panelRadiusPx = dashboard_clamp_int($design['panelRadiusPx'] ?? 28, 28, 16, 40);
    $glassBlurPx = dashboard_clamp_int($design['glassBlurPx'] ?? 26, 26, 0, 40);

    return [
        '--teal' => dashboard_sanitize_hex_color((string) ($design['accentColor'] ?? '#54EFC7'), '#54EFC7'),
        '--blue' => dashboard_sanitize_hex_color((string) ($design['accentSecondaryColor'] ?? '#79C7FF'), '#79C7FF'),
        '--amber' => dashboard_sanitize_hex_color((string) ($design['accentWarmColor'] ?? '#FFBF69'), '#FFBF69'),
        '--good' => dashboard_sanitize_hex_color((string) ($design['successColor'] ?? '#43D79F'), '#43D79F'),
        '--bad' => dashboard_sanitize_hex_color((string) ($design['dangerColor'] ?? '#FF7050'), '#FF7050'),
        '--red' => dashboard_sanitize_hex_color((string) ($design['dangerColor'] ?? '#FF7050'), '#FF7050'),
        '--ring-color-off' => dashboard_sanitize_hex_color((string) ($design['dangerColor'] ?? '#FF7050'), '#FF7050'),
        '--ui-font-family' => dashboard_sanitize_font_family((string) ($design['fontFamily'] ?? $defaultFontFamily), $defaultFontFamily),
        '--ui-font-scale' => rtrim(rtrim(number_format($fontScale, 2, '.', ''), '0'), '.'),
        '--hero-map-player-font-size' => rtrim(rtrim(number_format($heroMapPlayerFontSizeRem, 2, '.', ''), '0'), '.') . 'rem',
        '--panel-radius-sm' => (string) max(10, $panelRadiusPx - 10) . 'px',
        '--panel-radius-md' => (string) max(14, $panelRadiusPx - 4) . 'px',
        '--panel-radius-base' => (string) $panelRadiusPx . 'px',
        '--panel-radius-lg' => (string) min(44, $panelRadiusPx + 4) . 'px',
        '--glass-blur-strength' => (string) $glassBlurPx . 'px',
    ];
}

function dashboard_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $config = dashboard_config_defaults();

    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $config = dashboard_array_merge_recursive($config, $localConfig);
        }
    }

    $config['design']['accentColor'] = dashboard_sanitize_hex_color(
        (string) ($config['design']['accentColor'] ?? '#54EFC7'),
        '#54EFC7'
    );
    $config['design']['accentSecondaryColor'] = dashboard_sanitize_hex_color(
        (string) ($config['design']['accentSecondaryColor'] ?? '#79C7FF'),
        '#79C7FF'
    );
    $config['design']['accentWarmColor'] = dashboard_sanitize_hex_color(
        (string) ($config['design']['accentWarmColor'] ?? '#FFBF69'),
        '#FFBF69'
    );
    $config['design']['successColor'] = dashboard_sanitize_hex_color(
        (string) ($config['design']['successColor'] ?? '#43D79F'),
        '#43D79F'
    );
    $config['design']['dangerColor'] = dashboard_sanitize_hex_color(
        (string) ($config['design']['dangerColor'] ?? '#FF7050'),
        '#FF7050'
    );
    $config['design']['fontFamily'] = dashboard_sanitize_font_family(
        (string) ($config['design']['fontFamily'] ?? '"Space Grotesk", "Aptos", "Segoe UI", sans-serif'),
        '"Space Grotesk", "Aptos", "Segoe UI", sans-serif'
    );
    $config['design']['fontScale'] = dashboard_clamp_float(
        $config['design']['fontScale'] ?? 1.0,
        1.0,
        0.85,
        1.4
    );
    $config['design']['heroMapPlayerFontSizeRem'] = dashboard_clamp_float(
        $config['design']['heroMapPlayerFontSizeRem'] ?? 0.95,
        0.95,
        0.6,
        1.4
    );
    $config['design']['panelRadiusPx'] = dashboard_clamp_int(
        $config['design']['panelRadiusPx'] ?? 28,
        28,
        16,
        40
    );
    $config['design']['glassBlurPx'] = dashboard_clamp_int(
        $config['design']['glassBlurPx'] ?? 26,
        26,
        0,
        40
    );

    $config['telemetry']['upstreamUrl'] = dashboard_env_string(
        'ETS2_TELEMETRY_UPSTREAM_URL',
        (string) ($config['telemetry']['upstreamUrl'] ?? 'http://127.0.0.1:31377/api/ets2/telemetry')
    );
    $config['telemetry']['atsUpstreamUrl'] = dashboard_env_string(
        'ATS_TELEMETRY_UPSTREAM_URL',
        (string) ($config['telemetry']['atsUpstreamUrl'] ?? ($config['telemetry']['upstreamUrl'] ?? 'http://127.0.0.1:31377/api/ets2/telemetry'))
    );
    $config['telemetry']['refreshIntervalMs'] = dashboard_env_int(
        'ETS2_TELEMETRY_REFRESH_MS',
        (int) ($config['telemetry']['refreshIntervalMs'] ?? 250)
    );
    $config['telemetry']['requestTimeoutMs'] = dashboard_env_int(
        'ETS2_TELEMETRY_TIMEOUT_MS',
        (int) ($config['telemetry']['requestTimeoutMs'] ?? 4500)
    );
    $config['telemetry']['jsonPrettyPrint'] = dashboard_env_bool(
        'ETS2_TELEMETRY_JSON_PRETTY',
        (bool) ($config['telemetry']['jsonPrettyPrint'] ?? true)
    );
    $config['telemetry']['cacheEnabled'] = dashboard_env_bool(
        'ETS2_TELEMETRY_CACHE_ENABLED',
        (bool) ($config['telemetry']['cacheEnabled'] ?? true)
    );
    $config['telemetry']['cacheTtlMs'] = dashboard_env_int(
        'ETS2_TELEMETRY_CACHE_TTL_MS',
        (int) ($config['telemetry']['cacheTtlMs'] ?? 10000)
    );
    $config['telemetry']['cacheFile'] = dashboard_env_string(
        'ETS2_TELEMETRY_CACHE_FILE',
        (string) ($config['telemetry']['cacheFile'] ?? (__DIR__ . '/tmp/telemetry-cache.json'))
    );
    $config['telemetry']['atsCacheFile'] = dashboard_env_string(
        'ATS_TELEMETRY_CACHE_FILE',
        (string) ($config['telemetry']['atsCacheFile'] ?? (__DIR__ . '/tmp/telemetry-ats-cache.json'))
    );
    $config['snapshots']['enabled'] = dashboard_env_bool(
        'ETS2_SNAPSHOTS_ENABLED',
        (bool) ($config['snapshots']['enabled'] ?? false)
    );
    $config['snapshots']['intervalMs'] = dashboard_env_int(
        'ETS2_SNAPSHOTS_INTERVAL_MS',
        (int) ($config['snapshots']['intervalMs'] ?? 60000)
    );
    $config['snapshots']['directory'] = dashboard_env_string(
        'ETS2_SNAPSHOTS_DIRECTORY',
        (string) ($config['snapshots']['directory'] ?? (__DIR__ . '/snapshots'))
    );
    $config['snapshots']['atsDirectory'] = dashboard_env_string(
        'ATS_SNAPSHOTS_DIRECTORY',
        (string) ($config['snapshots']['atsDirectory'] ?? (__DIR__ . '/snapshots/ats'))
    );
    $config['snapshots']['stateFile'] = dashboard_env_string(
        'ETS2_SNAPSHOTS_STATE_FILE',
        (string) ($config['snapshots']['stateFile'] ?? (__DIR__ . '/tmp/snapshot-state.json'))
    );
    $config['snapshots']['atsStateFile'] = dashboard_env_string(
        'ATS_SNAPSHOTS_STATE_FILE',
        (string) ($config['snapshots']['atsStateFile'] ?? (__DIR__ . '/tmp/snapshot-ats-state.json'))
    );
    $config['snapshots']['prettyPrint'] = dashboard_env_bool(
        'ETS2_SNAPSHOTS_PRETTY_PRINT',
        (bool) ($config['snapshots']['prettyPrint'] ?? true)
    );
    $config['snapshots']['atsFilenamePrefix'] = dashboard_env_string(
        'ATS_SNAPSHOTS_FILENAME_PREFIX',
        (string) ($config['snapshots']['atsFilenamePrefix'] ?? 'telemetry-ats-')
    );

    return $config;
}

function dashboard_config_value(string $path, mixed $default = null): mixed
{
    $segments = explode('.', $path);
    $value = dashboard_config();

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}
