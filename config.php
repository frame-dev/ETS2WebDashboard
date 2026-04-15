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
        'telemetry' => [
            'upstreamUrl' => 'http://127.0.0.1:31377/api/ets2/telemetry',
            'refreshIntervalMs' => 250,
            'requestTimeoutMs' => 4500,
            'jsonPrettyPrint' => true,
            'cacheEnabled' => true,
            'cacheTtlMs' => 10000,
            'cacheFile' => __DIR__ . '/tmp/telemetry-cache.json',
        ],
        'frontend' => [
            'telemetryEndpoint' => 'telemetry.php?format=json',
            'telemetryPolling' => [
                'backoffStepMs' => 1000,
                'maxBackoffMs' => 30000,
                'hiddenIntervalMs' => 12000,
            ],
            'speedRing' => [
                'maxDisplayKph' => 130,
                'overspeedToleranceKph' => 2,
                'trendSensitivityKph' => 0.8,
            ],
            'storageKeys' => [
                'activeTab' => 'ets2-dashboard-active-tab',
                'mapPreferences' => 'ets2-dashboard-map-preferences',
            ],
            'routePlanner' => [
                'averageKph' => 63,
                'realTimeScale' => 17.5,
            ],
            'mapBounds' => [
                'minX' => -94118.3,
                'maxX' => 128280,
                'minZ' => -102857,
                'maxZ' => 57201.3,
            ],
            'mapTiles' => [
                'baseUrlCandidates' => ['tiles', 'maps', 'http://127.0.0.1:8081'],
                'configNames' => ['config.json', 'TileMapInfo.json'],
                'overzoomSteps' => 3,
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

    $config['telemetry']['upstreamUrl'] = dashboard_env_string(
        'ETS2_TELEMETRY_UPSTREAM_URL',
        (string) ($config['telemetry']['upstreamUrl'] ?? 'http://127.0.0.1:31377/api/ets2/telemetry')
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
