<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function settings_format_bool(bool $value): string
{
    return $value ? 'Enabled' : 'Disabled';
}

function settings_format_ms(int $value): string
{
    return $value % 1000 === 0 ? ((string) ($value / 1000)) . 's' : $value . ' ms';
}

function settings_format_datetime_ms(int $timestampMs): string
{
    if ($timestampMs <= 0) {
        return 'Never';
    }

    $date = new DateTimeImmutable('@' . (string) floor($timestampMs / 1000));
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
}

function settings_load_local_config(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $loaded = require $path;
    return is_array($loaded) ? $loaded : [];
}

function settings_export_local_config(array $config): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
}

function settings_read_json_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function settings_upload_error_message(int $errorCode): ?string
{
    return match ($errorCode) {
        UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE => null,
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded config file was too large to import.',
        UPLOAD_ERR_PARTIAL => 'The uploaded config file was only partially received. Try uploading it again.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload directory, so the config file could not be read.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded config file to temporary storage.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the config file upload before it completed.',
        default => 'The config file upload failed before import could begin.',
    };
}

function settings_import_has_managed_sections(array $importedConfig): bool
{
    foreach (['app', 'design', 'telemetry', 'snapshots', 'frontend'] as $key) {
        if (array_key_exists($key, $importedConfig)) {
            return true;
        }
    }

    return false;
}

function settings_checkbox_post(string $name): bool
{
    return ($_POST[$name] ?? '') === '1';
}

function settings_clean_lines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $lines = array_map(static fn (string $line): string => trim($line), $lines);
    return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
}

function settings_normalize_string_array(mixed $value, array $fallback): array
{
    if (is_array($value)) {
        $items = array_map(static fn ($item): string => trim((string) $item), $value);
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
        return $items === [] ? $fallback : $items;
    }

    if (is_string($value)) {
        $items = settings_clean_lines($value);
        return $items === [] ? $fallback : $items;
    }

    return $fallback;
}

function settings_int_value(mixed $value, int $fallback): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value)) {
        return (int) $value;
    }

    if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
        return (int) $value;
    }

    return $fallback;
}

function settings_float_value(mixed $value, float $fallback): float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }

    if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
        return (float) $value;
    }

    return $fallback;
}

function settings_normalize_color(mixed $value, string $fallback): string
{
    if (!is_string($value)) {
        return dashboard_sanitize_hex_color($fallback, $fallback);
    }

    return dashboard_sanitize_hex_color($value, $fallback);
}

function settings_build_theme_style(array $design): string
{
    $variables = dashboard_design_theme_variables($design);

    $declarations = [];
    foreach ($variables as $name => $value) {
        $declarations[] = $name . ': ' . $value;
    }

    return implode('; ', $declarations) . ';';
}

function settings_build_snapshot_filename_preview(array $snapshotConfig): string
{
    $timestampMs = 1760044425432;
    $date = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $timestampMs / 1000), new \DateTimeZone('UTC'));
    $prefix = trim((string) ($snapshotConfig['filenamePrefix'] ?? 'telemetry-'));
    $pattern = trim((string) ($snapshotConfig['filenamePattern'] ?? '{prefix}{date}-{ms}Z.{ext}'));
    $timestampFormat = trim((string) ($snapshotConfig['timestampFormat'] ?? 'Y-m-d\TH-i-s'));

    if (!$date instanceof \DateTimeImmutable) {
        return 'telemetry-2025-10-09T08-20-25-432Z.json';
    }

    if ($pattern === '') {
        $pattern = '{prefix}{date}-{ms}Z.{ext}';
    }

    if ($timestampFormat === '') {
        $timestampFormat = 'Y-m-d\TH-i-s';
    }

    $rendered = strtr($pattern, [
        '{prefix}' => $prefix,
        '{date}' => $date->format($timestampFormat),
        '{ms}' => str_pad((string) ($timestampMs % 1000), 3, '0', STR_PAD_LEFT),
        '{ext}' => 'json',
    ]);
    $sanitized = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/', '-', $rendered);
    $sanitized = is_string($sanitized) ? trim($sanitized, ". \t\n\r\0\x0B") : '';

    return $sanitized !== '' ? $sanitized : 'telemetry-2025-10-09T08-20-25-432Z.json';
}

function settings_build_managed_config(array $formData): array
{
    return [
        'app' => $formData['app'],
        'design' => $formData['design'],
        'telemetry' => $formData['telemetry'],
        'snapshots' => $formData['snapshots'],
        'frontend' => [
            'routePlanner' => $formData['routePlanner'],
            'speedRing' => $formData['speedRing'],
            'telemetryPolling' => $formData['telemetryPolling'],
            'telemetryEndpoint' => $formData['frontend']['telemetryEndpoint'],
            'storageKeys' => $formData['frontend']['storageKeys'],
            'mapDefaults' => $formData['mapDefaults'],
            'mapBounds' => $formData['mapBounds'],
            'mapTiles' => $formData['frontend']['mapTiles'],
        ],
    ];
}

function settings_apply_managed_config(array $localConfig, array $managedConfig): array
{
    $localConfig['app'] = $managedConfig['app'];
    $localConfig['design'] = $managedConfig['design'];
    $localConfig['telemetry'] = $managedConfig['telemetry'];
    $localConfig['snapshots'] = $managedConfig['snapshots'];
    $localConfig['frontend']['routePlanner'] = $managedConfig['frontend']['routePlanner'];
    $localConfig['frontend']['speedRing'] = $managedConfig['frontend']['speedRing'];
    $localConfig['frontend']['telemetryPolling'] = $managedConfig['frontend']['telemetryPolling'];
    $localConfig['frontend']['telemetryEndpoint'] = $managedConfig['frontend']['telemetryEndpoint'];
    $localConfig['frontend']['storageKeys'] = $managedConfig['frontend']['storageKeys'];
    $localConfig['frontend']['mapDefaults'] = $managedConfig['frontend']['mapDefaults'];
    $localConfig['frontend']['mapBounds'] = $managedConfig['frontend']['mapBounds'];
    $localConfig['frontend']['mapTiles'] = $managedConfig['frontend']['mapTiles'];

    return $localConfig;
}

function settings_import_json_to_form_data(array $currentFormData, array $importedConfig): array
{
    $frontend = is_array($importedConfig['frontend'] ?? null) ? $importedConfig['frontend'] : [];

    $currentFormData['app']['pageTitle'] = trim((string) ($importedConfig['app']['pageTitle'] ?? $currentFormData['app']['pageTitle']));
    $currentFormData['app']['metaDescription'] = trim((string) ($importedConfig['app']['metaDescription'] ?? $currentFormData['app']['metaDescription']));
    $currentFormData['app']['heroEyebrow'] = trim((string) ($importedConfig['app']['heroEyebrow'] ?? $currentFormData['app']['heroEyebrow']));
    $currentFormData['app']['heroTitle'] = trim((string) ($importedConfig['app']['heroTitle'] ?? $currentFormData['app']['heroTitle']));
    $currentFormData['app']['heroSummary'] = trim((string) ($importedConfig['app']['heroSummary'] ?? $currentFormData['app']['heroSummary']));

    $currentFormData['design']['accentColor'] = settings_normalize_color($importedConfig['design']['accentColor'] ?? $currentFormData['design']['accentColor'], $currentFormData['design']['accentColor']);
    $currentFormData['design']['accentSecondaryColor'] = settings_normalize_color($importedConfig['design']['accentSecondaryColor'] ?? $currentFormData['design']['accentSecondaryColor'], $currentFormData['design']['accentSecondaryColor']);
    $currentFormData['design']['accentWarmColor'] = settings_normalize_color($importedConfig['design']['accentWarmColor'] ?? $currentFormData['design']['accentWarmColor'], $currentFormData['design']['accentWarmColor']);
    $currentFormData['design']['successColor'] = settings_normalize_color($importedConfig['design']['successColor'] ?? $currentFormData['design']['successColor'], $currentFormData['design']['successColor']);
    $currentFormData['design']['dangerColor'] = settings_normalize_color($importedConfig['design']['dangerColor'] ?? $currentFormData['design']['dangerColor'], $currentFormData['design']['dangerColor']);
    $currentFormData['design']['fontFamily'] = dashboard_sanitize_font_family((string) ($importedConfig['design']['fontFamily'] ?? $currentFormData['design']['fontFamily']), $currentFormData['design']['fontFamily']);
    $currentFormData['design']['fontScale'] = dashboard_clamp_float($importedConfig['design']['fontScale'] ?? $currentFormData['design']['fontScale'], $currentFormData['design']['fontScale'], 0.85, 1.4);
    $currentFormData['design']['panelRadiusPx'] = dashboard_clamp_int($importedConfig['design']['panelRadiusPx'] ?? $currentFormData['design']['panelRadiusPx'], $currentFormData['design']['panelRadiusPx'], 16, 40);
    $currentFormData['design']['glassBlurPx'] = dashboard_clamp_int($importedConfig['design']['glassBlurPx'] ?? $currentFormData['design']['glassBlurPx'], $currentFormData['design']['glassBlurPx'], 0, 40);

    $currentFormData['telemetry']['upstreamUrl'] = trim((string) ($importedConfig['telemetry']['upstreamUrl'] ?? $currentFormData['telemetry']['upstreamUrl']));
    $currentFormData['telemetry']['refreshIntervalMs'] = max(100, settings_int_value($importedConfig['telemetry']['refreshIntervalMs'] ?? null, $currentFormData['telemetry']['refreshIntervalMs']));
    $currentFormData['telemetry']['requestTimeoutMs'] = max(500, settings_int_value($importedConfig['telemetry']['requestTimeoutMs'] ?? null, $currentFormData['telemetry']['requestTimeoutMs']));
    $currentFormData['telemetry']['jsonPrettyPrint'] = (bool) ($importedConfig['telemetry']['jsonPrettyPrint'] ?? $currentFormData['telemetry']['jsonPrettyPrint']);
    $currentFormData['telemetry']['cacheEnabled'] = (bool) ($importedConfig['telemetry']['cacheEnabled'] ?? $currentFormData['telemetry']['cacheEnabled']);
    $currentFormData['telemetry']['cacheTtlMs'] = max(0, settings_int_value($importedConfig['telemetry']['cacheTtlMs'] ?? null, $currentFormData['telemetry']['cacheTtlMs']));
    $currentFormData['telemetry']['cacheFile'] = trim((string) ($importedConfig['telemetry']['cacheFile'] ?? $currentFormData['telemetry']['cacheFile']));

    $currentFormData['snapshots']['enabled'] = (bool) ($importedConfig['snapshots']['enabled'] ?? $currentFormData['snapshots']['enabled']);
    $currentFormData['snapshots']['intervalMs'] = max(1000, settings_int_value($importedConfig['snapshots']['intervalMs'] ?? null, $currentFormData['snapshots']['intervalMs']));
    $currentFormData['snapshots']['directory'] = trim((string) ($importedConfig['snapshots']['directory'] ?? $currentFormData['snapshots']['directory']));
    $currentFormData['snapshots']['stateFile'] = trim((string) ($importedConfig['snapshots']['stateFile'] ?? $currentFormData['snapshots']['stateFile']));
    $currentFormData['snapshots']['prettyPrint'] = (bool) ($importedConfig['snapshots']['prettyPrint'] ?? $currentFormData['snapshots']['prettyPrint']);
    $currentFormData['snapshots']['filenamePrefix'] = trim((string) ($importedConfig['snapshots']['filenamePrefix'] ?? $currentFormData['snapshots']['filenamePrefix']));
    $currentFormData['snapshots']['filenamePattern'] = trim((string) ($importedConfig['snapshots']['filenamePattern'] ?? $currentFormData['snapshots']['filenamePattern']));
    $currentFormData['snapshots']['timestampFormat'] = trim((string) ($importedConfig['snapshots']['timestampFormat'] ?? $currentFormData['snapshots']['timestampFormat']));

    $currentFormData['routePlanner']['averageKph'] = max(1, settings_int_value($frontend['routePlanner']['averageKph'] ?? null, $currentFormData['routePlanner']['averageKph']));
    $currentFormData['routePlanner']['realTimeScale'] = max(0.1, settings_float_value($frontend['routePlanner']['realTimeScale'] ?? null, $currentFormData['routePlanner']['realTimeScale']));
    $currentFormData['speedRing']['maxDisplayKph'] = max(1, settings_int_value($frontend['speedRing']['maxDisplayKph'] ?? null, $currentFormData['speedRing']['maxDisplayKph']));
    $currentFormData['speedRing']['overspeedToleranceKph'] = max(0, settings_float_value($frontend['speedRing']['overspeedToleranceKph'] ?? null, $currentFormData['speedRing']['overspeedToleranceKph']));
    $currentFormData['speedRing']['trendSensitivityKph'] = max(0, settings_float_value($frontend['speedRing']['trendSensitivityKph'] ?? null, $currentFormData['speedRing']['trendSensitivityKph']));
    $currentFormData['telemetryPolling']['backoffStepMs'] = max(0, settings_int_value($frontend['telemetryPolling']['backoffStepMs'] ?? null, $currentFormData['telemetryPolling']['backoffStepMs']));
    $currentFormData['telemetryPolling']['maxBackoffMs'] = max(0, settings_int_value($frontend['telemetryPolling']['maxBackoffMs'] ?? null, $currentFormData['telemetryPolling']['maxBackoffMs']));
    $currentFormData['telemetryPolling']['hiddenIntervalMs'] = max(0, settings_int_value($frontend['telemetryPolling']['hiddenIntervalMs'] ?? null, $currentFormData['telemetryPolling']['hiddenIntervalMs']));
    $currentFormData['telemetryPolling']['minimumIntervalMs'] = max(100, settings_int_value($frontend['telemetryPolling']['minimumIntervalMs'] ?? null, $currentFormData['telemetryPolling']['minimumIntervalMs']));
    $currentFormData['telemetryPolling']['cacheMultiplier'] = max(1, settings_int_value($frontend['telemetryPolling']['cacheMultiplier'] ?? null, $currentFormData['telemetryPolling']['cacheMultiplier']));

    $currentFormData['frontend']['telemetryEndpoint'] = trim((string) (($frontend['telemetryEndpoint'] ?? null) ?? $currentFormData['frontend']['telemetryEndpoint']));
    $currentFormData['frontend']['storageKeys']['activeTab'] = trim((string) (($frontend['storageKeys']['activeTab'] ?? null) ?? $currentFormData['frontend']['storageKeys']['activeTab']));
    $currentFormData['frontend']['storageKeys']['mapPreferences'] = trim((string) (($frontend['storageKeys']['mapPreferences'] ?? null) ?? $currentFormData['frontend']['storageKeys']['mapPreferences']));
    $currentFormData['mapDefaults']['worldZoom'] = max(0, settings_int_value($frontend['mapDefaults']['worldZoom'] ?? null, $currentFormData['mapDefaults']['worldZoom']));
    $currentFormData['mapDefaults']['heroZoom'] = max(0, settings_int_value($frontend['mapDefaults']['heroZoom'] ?? null, $currentFormData['mapDefaults']['heroZoom']));
    $currentFormData['mapDefaults']['worldFollowTruck'] = (bool) ($frontend['mapDefaults']['worldFollowTruck'] ?? $currentFormData['mapDefaults']['worldFollowTruck']);
    $currentFormData['mapDefaults']['heroFollowTruck'] = (bool) ($frontend['mapDefaults']['heroFollowTruck'] ?? $currentFormData['mapDefaults']['heroFollowTruck']);
    $currentFormData['mapBounds']['minX'] = settings_float_value($frontend['mapBounds']['minX'] ?? null, $currentFormData['mapBounds']['minX']);
    $currentFormData['mapBounds']['maxX'] = settings_float_value($frontend['mapBounds']['maxX'] ?? null, $currentFormData['mapBounds']['maxX']);
    $currentFormData['mapBounds']['minZ'] = settings_float_value($frontend['mapBounds']['minZ'] ?? null, $currentFormData['mapBounds']['minZ']);
    $currentFormData['mapBounds']['maxZ'] = settings_float_value($frontend['mapBounds']['maxZ'] ?? null, $currentFormData['mapBounds']['maxZ']);
    $currentFormData['frontend']['mapTiles']['baseUrlCandidates'] = settings_normalize_string_array($frontend['mapTiles']['baseUrlCandidates'] ?? null, $currentFormData['frontend']['mapTiles']['baseUrlCandidates']);
    $currentFormData['frontend']['mapTiles']['configNames'] = settings_normalize_string_array($frontend['mapTiles']['configNames'] ?? null, $currentFormData['frontend']['mapTiles']['configNames']);
    $currentFormData['frontend']['mapTiles']['overzoomSteps'] = max(0, settings_int_value($frontend['mapTiles']['overzoomSteps'] ?? null, $currentFormData['frontend']['mapTiles']['overzoomSteps']));
    $currentFormData['frontend']['mapTiles']['retryDelayMs'] = max(1000, settings_int_value($frontend['mapTiles']['retryDelayMs'] ?? null, $currentFormData['frontend']['mapTiles']['retryDelayMs']));

    return $currentFormData;
}

$appTitle = (string) dashboard_config_value('app.pageTitle', 'ETS2 Command Dashboard');
$configLocalPath = __DIR__ . '/config.local.php';
$appConfig = [
    'pageTitle' => (string) dashboard_config_value('app.pageTitle', 'ETS2 Command Dashboard'),
    'metaDescription' => (string) dashboard_config_value('app.metaDescription', 'Live ETS2 dashboard for telemetry, route status, systems, and map tracking.'),
    'heroEyebrow' => (string) dashboard_config_value('app.heroEyebrow', 'Euro Truck Simulator 2'),
    'heroTitle' => (string) dashboard_config_value('app.heroTitle', 'Command dashboard online'),
    'heroSummary' => (string) dashboard_config_value('app.heroSummary', 'Preparing a live operator view from your local telemetry feed.'),
];
$designConfig = [
    'accentColor' => (string) dashboard_config_value('design.accentColor', '#54EFC7'),
    'accentSecondaryColor' => (string) dashboard_config_value('design.accentSecondaryColor', '#79C7FF'),
    'accentWarmColor' => (string) dashboard_config_value('design.accentWarmColor', '#FFBF69'),
    'successColor' => (string) dashboard_config_value('design.successColor', '#43D79F'),
    'dangerColor' => (string) dashboard_config_value('design.dangerColor', '#FF7050'),
    'fontFamily' => (string) dashboard_config_value('design.fontFamily', '"Space Grotesk", "Aptos", "Segoe UI", sans-serif'),
    'fontScale' => (float) dashboard_config_value('design.fontScale', 1.0),
    'panelRadiusPx' => (int) dashboard_config_value('design.panelRadiusPx', 28),
    'glassBlurPx' => (int) dashboard_config_value('design.glassBlurPx', 26),
];
$telemetryConfig = [
    'upstreamUrl' => (string) dashboard_config_value('telemetry.upstreamUrl', 'http://127.0.0.1:31377/api/ets2/telemetry'),
    'refreshIntervalMs' => (int) dashboard_config_value('telemetry.refreshIntervalMs', 250),
    'requestTimeoutMs' => (int) dashboard_config_value('telemetry.requestTimeoutMs', 4500),
    'jsonPrettyPrint' => (bool) dashboard_config_value('telemetry.jsonPrettyPrint', true),
    'cacheEnabled' => (bool) dashboard_config_value('telemetry.cacheEnabled', true),
    'cacheTtlMs' => (int) dashboard_config_value('telemetry.cacheTtlMs', 10000),
    'cacheFile' => (string) dashboard_config_value('telemetry.cacheFile', __DIR__ . '/tmp/telemetry-cache.json'),
];
$snapshotConfig = [
    'enabled' => (bool) dashboard_config_value('snapshots.enabled', false),
    'intervalMs' => (int) dashboard_config_value('snapshots.intervalMs', 60000),
    'directory' => (string) dashboard_config_value('snapshots.directory', __DIR__ . '/snapshots'),
    'stateFile' => (string) dashboard_config_value('snapshots.stateFile', __DIR__ . '/tmp/snapshot-state.json'),
    'prettyPrint' => (bool) dashboard_config_value('snapshots.prettyPrint', true),
    'filenamePrefix' => (string) dashboard_config_value('snapshots.filenamePrefix', 'telemetry-'),
    'filenamePattern' => (string) dashboard_config_value('snapshots.filenamePattern', '{prefix}{date}-{ms}Z.{ext}'),
    'timestampFormat' => (string) dashboard_config_value('snapshots.timestampFormat', 'Y-m-d\TH-i-s'),
];
$routePlannerConfig = [
    'averageKph' => (int) dashboard_config_value('frontend.routePlanner.averageKph', 63),
    'realTimeScale' => (float) dashboard_config_value('frontend.routePlanner.realTimeScale', 17.5),
];
$speedRingConfig = [
    'maxDisplayKph' => (int) dashboard_config_value('frontend.speedRing.maxDisplayKph', 130),
    'overspeedToleranceKph' => (float) dashboard_config_value('frontend.speedRing.overspeedToleranceKph', 2),
    'trendSensitivityKph' => (float) dashboard_config_value('frontend.speedRing.trendSensitivityKph', 0.8),
];
$telemetryPollingConfig = [
    'backoffStepMs' => (int) dashboard_config_value('frontend.telemetryPolling.backoffStepMs', 1000),
    'maxBackoffMs' => (int) dashboard_config_value('frontend.telemetryPolling.maxBackoffMs', 30000),
    'hiddenIntervalMs' => (int) dashboard_config_value('frontend.telemetryPolling.hiddenIntervalMs', 12000),
    'minimumIntervalMs' => (int) dashboard_config_value('frontend.telemetryPolling.minimumIntervalMs', 250),
    'cacheMultiplier' => (int) dashboard_config_value('frontend.telemetryPolling.cacheMultiplier', 2),
];
$mapDefaultsConfig = [
    'worldZoom' => (int) dashboard_config_value('frontend.mapDefaults.worldZoom', 4),
    'worldFollowTruck' => (bool) dashboard_config_value('frontend.mapDefaults.worldFollowTruck', true),
    'heroZoom' => (int) dashboard_config_value('frontend.mapDefaults.heroZoom', 3),
    'heroFollowTruck' => (bool) dashboard_config_value('frontend.mapDefaults.heroFollowTruck', true),
];
$mapBoundsConfig = [
    'minX' => (float) dashboard_config_value('frontend.mapBounds.minX', -94118.3),
    'maxX' => (float) dashboard_config_value('frontend.mapBounds.maxX', 128280),
    'minZ' => (float) dashboard_config_value('frontend.mapBounds.minZ', -102857),
    'maxZ' => (float) dashboard_config_value('frontend.mapBounds.maxZ', 57201.3),
];
$frontendConfig = [
    'telemetryEndpoint' => (string) dashboard_config_value('frontend.telemetryEndpoint', 'telemetry.php?format=json'),
    'storageKeys' => [
        'activeTab' => (string) dashboard_config_value('frontend.storageKeys.activeTab', 'ets2-dashboard-active-tab'),
        'mapPreferences' => (string) dashboard_config_value('frontend.storageKeys.mapPreferences', 'ets2-dashboard-map-preferences'),
    ],
    'mapTiles' => [
        'baseUrlCandidates' => settings_normalize_string_array(
            dashboard_config_value('frontend.mapTiles.baseUrlCandidates', ['http://10.147.17.64/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081']),
            ['http://10.147.17.64/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081']
        ),
        'configNames' => settings_normalize_string_array(
            dashboard_config_value('frontend.mapTiles.configNames', ['config.json', 'TileMapInfo.json']),
            ['config.json', 'TileMapInfo.json']
        ),
        'overzoomSteps' => (int) dashboard_config_value('frontend.mapTiles.overzoomSteps', 3),
        'retryDelayMs' => (int) dashboard_config_value('frontend.mapTiles.retryDelayMs', 8000),
    ],
];
$formData = [
    'app' => $appConfig,
    'design' => $designConfig,
    'telemetry' => $telemetryConfig,
    'snapshots' => $snapshotConfig,
    'routePlanner' => $routePlannerConfig,
    'speedRing' => $speedRingConfig,
    'telemetryPolling' => $telemetryPollingConfig,
    'mapDefaults' => $mapDefaultsConfig,
    'mapBounds' => $mapBoundsConfig,
    'frontend' => $frontendConfig,
];
$flash = null;
$flashType = null;
$errors = [];
$importJsonInput = '';
$importFeedback = null;
$managedConfig = settings_build_managed_config($formData);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['export'])) {
    $exportType = (string) $_GET['export'];

    if ($exportType === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ets2-dashboard-settings.json"');
        echo json_encode($managedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($exportType === 'php') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="ets2-dashboard-settings.php"');
        echo settings_export_local_config($managedConfig);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $settingsAction = (string) ($_POST['settings_action'] ?? 'save');

    if ($settingsAction === 'import') {
        $importJson = trim((string) ($_POST['import_config_json'] ?? ''));
        $importJsonInput = $importJson;

        if ($importJson === '' && isset($_FILES['import_config_file']) && is_array($_FILES['import_config_file'])) {
            $uploadErrorCode = (int) ($_FILES['import_config_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            $uploadErrorMessage = settings_upload_error_message($uploadErrorCode);
            if ($uploadErrorMessage !== null) {
                $errors[] = $uploadErrorMessage;
            } elseif ($uploadErrorCode === UPLOAD_ERR_OK && is_string($_FILES['import_config_file']['tmp_name'] ?? null)) {
                $uploadContents = @file_get_contents($_FILES['import_config_file']['tmp_name']);
                if (is_string($uploadContents)) {
                    $importJson = trim($uploadContents);
                    $importJsonInput = $importJson;
                } else {
                    $errors[] = 'The uploaded config file could not be read from temporary storage.';
                }
            }
        }

        if ($errors === [] && $importJson === '') {
            $errors[] = 'Provide a JSON config file or paste JSON before importing.';
        } elseif ($errors === []) {
            $decodedImport = json_decode($importJson, true);
            if (!is_array($decodedImport)) {
                $errors[] = 'Imported config must be valid JSON: ' . json_last_error_msg() . '.';
            } elseif (!settings_import_has_managed_sections($decodedImport)) {
                $errors[] = 'Imported config does not contain any managed dashboard settings sections such as app, telemetry, snapshots, or frontend.';
            } else {
                $formData = settings_import_json_to_form_data($formData, $decodedImport);
                $managedConfig = settings_build_managed_config($formData);

                try {
                    $localConfig = settings_load_local_config($configLocalPath);
                    $localConfig = settings_apply_managed_config($localConfig, $managedConfig);
                    $saved = @file_put_contents($configLocalPath, settings_export_local_config($localConfig), LOCK_EX);

                    if ($saved === false) {
                        $errors[] = 'Could not write imported settings to config.local.php.';
                    } else {
                        header('Location: settings.php?imported=1');
                        exit;
                    }
                } catch (Throwable $exception) {
                    $errors[] = 'Could not import config: ' . $exception->getMessage();
                }
            }
        }
    }

    if ($settingsAction === 'save') {
        $formData = [
            'app' => [
                'pageTitle' => trim((string) ($_POST['app_page_title'] ?? $appConfig['pageTitle'])),
                'metaDescription' => trim((string) ($_POST['app_meta_description'] ?? $appConfig['metaDescription'])),
                'heroEyebrow' => trim((string) ($_POST['app_hero_eyebrow'] ?? $appConfig['heroEyebrow'])),
                'heroTitle' => trim((string) ($_POST['app_hero_title'] ?? $appConfig['heroTitle'])),
                'heroSummary' => trim((string) ($_POST['app_hero_summary'] ?? $appConfig['heroSummary'])),
            ],
            'design' => [
                'accentColor' => settings_normalize_color($_POST['design_accent_color'] ?? $designConfig['accentColor'], $designConfig['accentColor']),
                'accentSecondaryColor' => settings_normalize_color($_POST['design_accent_secondary_color'] ?? $designConfig['accentSecondaryColor'], $designConfig['accentSecondaryColor']),
                'accentWarmColor' => settings_normalize_color($_POST['design_accent_warm_color'] ?? $designConfig['accentWarmColor'], $designConfig['accentWarmColor']),
                'successColor' => settings_normalize_color($_POST['design_success_color'] ?? $designConfig['successColor'], $designConfig['successColor']),
                'dangerColor' => settings_normalize_color($_POST['design_danger_color'] ?? $designConfig['dangerColor'], $designConfig['dangerColor']),
                'fontFamily' => dashboard_sanitize_font_family((string) ($_POST['design_font_family'] ?? $designConfig['fontFamily']), $designConfig['fontFamily']),
                'fontScale' => dashboard_clamp_float($_POST['design_font_scale'] ?? $designConfig['fontScale'], $designConfig['fontScale'], 0.85, 1.4),
                'panelRadiusPx' => dashboard_clamp_int($_POST['design_panel_radius_px'] ?? $designConfig['panelRadiusPx'], $designConfig['panelRadiusPx'], 16, 40),
                'glassBlurPx' => dashboard_clamp_int($_POST['design_glass_blur_px'] ?? $designConfig['glassBlurPx'], $designConfig['glassBlurPx'], 0, 40),
            ],
            'telemetry' => [
                'upstreamUrl' => trim((string) ($_POST['telemetry_upstream_url'] ?? $telemetryConfig['upstreamUrl'])),
                'refreshIntervalMs' => max(100, settings_int_value($_POST['telemetry_refresh_interval_ms'] ?? null, $telemetryConfig['refreshIntervalMs'])),
                'requestTimeoutMs' => max(500, settings_int_value($_POST['telemetry_request_timeout_ms'] ?? null, $telemetryConfig['requestTimeoutMs'])),
                'jsonPrettyPrint' => settings_checkbox_post('telemetry_json_pretty_print'),
                'cacheEnabled' => settings_checkbox_post('telemetry_cache_enabled'),
                'cacheTtlMs' => max(0, settings_int_value($_POST['telemetry_cache_ttl_ms'] ?? null, $telemetryConfig['cacheTtlMs'])),
                'cacheFile' => trim((string) ($_POST['telemetry_cache_file'] ?? $telemetryConfig['cacheFile'])),
            ],
            'snapshots' => [
                'enabled' => settings_checkbox_post('snapshot_enabled'),
                'intervalMs' => max(1000, settings_int_value($_POST['snapshot_interval_ms'] ?? null, $snapshotConfig['intervalMs'])),
                'directory' => trim((string) ($_POST['snapshot_directory'] ?? $snapshotConfig['directory'])),
                'stateFile' => trim((string) ($_POST['snapshot_state_file'] ?? $snapshotConfig['stateFile'])),
                'prettyPrint' => settings_checkbox_post('snapshot_pretty_print'),
                'filenamePrefix' => trim((string) ($_POST['snapshot_filename_prefix'] ?? $snapshotConfig['filenamePrefix'])),
                'filenamePattern' => trim((string) ($_POST['snapshot_filename_pattern'] ?? $snapshotConfig['filenamePattern'])),
                'timestampFormat' => trim((string) ($_POST['snapshot_timestamp_format'] ?? $snapshotConfig['timestampFormat'])),
            ],
            'routePlanner' => [
                'averageKph' => max(1, settings_int_value($_POST['route_planner_average_kph'] ?? null, $routePlannerConfig['averageKph'])),
                'realTimeScale' => max(0.1, settings_float_value($_POST['route_planner_real_time_scale'] ?? null, $routePlannerConfig['realTimeScale'])),
            ],
            'speedRing' => [
                'maxDisplayKph' => max(1, settings_int_value($_POST['speed_ring_max_display_kph'] ?? null, $speedRingConfig['maxDisplayKph'])),
                'overspeedToleranceKph' => max(0, settings_float_value($_POST['speed_ring_overspeed_tolerance_kph'] ?? null, $speedRingConfig['overspeedToleranceKph'])),
                'trendSensitivityKph' => max(0, settings_float_value($_POST['speed_ring_trend_sensitivity_kph'] ?? null, $speedRingConfig['trendSensitivityKph'])),
            ],
            'telemetryPolling' => [
                'backoffStepMs' => max(0, settings_int_value($_POST['telemetry_polling_backoff_step_ms'] ?? null, $telemetryPollingConfig['backoffStepMs'])),
                'maxBackoffMs' => max(0, settings_int_value($_POST['telemetry_polling_max_backoff_ms'] ?? null, $telemetryPollingConfig['maxBackoffMs'])),
                'hiddenIntervalMs' => max(0, settings_int_value($_POST['telemetry_polling_hidden_interval_ms'] ?? null, $telemetryPollingConfig['hiddenIntervalMs'])),
                'minimumIntervalMs' => max(100, settings_int_value($_POST['telemetry_polling_minimum_interval_ms'] ?? null, $telemetryPollingConfig['minimumIntervalMs'])),
                'cacheMultiplier' => max(1, settings_int_value($_POST['telemetry_polling_cache_multiplier'] ?? null, $telemetryPollingConfig['cacheMultiplier'])),
            ],
            'mapDefaults' => [
                'worldZoom' => max(0, settings_int_value($_POST['frontend_map_default_world_zoom'] ?? null, $mapDefaultsConfig['worldZoom'])),
                'worldFollowTruck' => settings_checkbox_post('frontend_map_default_world_follow_truck'),
                'heroZoom' => max(0, settings_int_value($_POST['frontend_map_default_hero_zoom'] ?? null, $mapDefaultsConfig['heroZoom'])),
                'heroFollowTruck' => settings_checkbox_post('frontend_map_default_hero_follow_truck'),
            ],
            'mapBounds' => [
                'minX' => settings_float_value($_POST['frontend_map_bounds_min_x'] ?? null, $mapBoundsConfig['minX']),
                'maxX' => settings_float_value($_POST['frontend_map_bounds_max_x'] ?? null, $mapBoundsConfig['maxX']),
                'minZ' => settings_float_value($_POST['frontend_map_bounds_min_z'] ?? null, $mapBoundsConfig['minZ']),
                'maxZ' => settings_float_value($_POST['frontend_map_bounds_max_z'] ?? null, $mapBoundsConfig['maxZ']),
            ],
            'frontend' => [
                'telemetryEndpoint' => trim((string) ($_POST['frontend_telemetry_endpoint'] ?? $frontendConfig['telemetryEndpoint'])),
                'storageKeys' => [
                    'activeTab' => trim((string) ($_POST['frontend_storage_key_active_tab'] ?? $frontendConfig['storageKeys']['activeTab'])),
                    'mapPreferences' => trim((string) ($_POST['frontend_storage_key_map_preferences'] ?? $frontendConfig['storageKeys']['mapPreferences'])),
                ],
                'mapTiles' => [
                    'baseUrlCandidates' => settings_normalize_string_array((string) ($_POST['frontend_map_tiles_base_urls'] ?? ''), $frontendConfig['mapTiles']['baseUrlCandidates']),
                    'configNames' => settings_normalize_string_array((string) ($_POST['frontend_map_tiles_config_names'] ?? ''), $frontendConfig['mapTiles']['configNames']),
                    'overzoomSteps' => max(0, settings_int_value($_POST['frontend_map_tiles_overzoom_steps'] ?? null, $frontendConfig['mapTiles']['overzoomSteps'])),
                    'retryDelayMs' => max(1000, settings_int_value($_POST['frontend_map_tiles_retry_delay_ms'] ?? null, $frontendConfig['mapTiles']['retryDelayMs'])),
                ],
            ],
        ];

        if ($formData['app']['pageTitle'] === '') {
            $errors[] = 'Page title cannot be empty.';
        }

        if ($formData['telemetry']['upstreamUrl'] === '') {
            $errors[] = 'Telemetry upstream URL cannot be empty.';
        }

        if ($formData['telemetry']['cacheFile'] === '') {
            $errors[] = 'Telemetry cache file cannot be empty.';
        }

        if ($formData['frontend']['telemetryEndpoint'] === '') {
            $errors[] = 'Frontend telemetry endpoint cannot be empty.';
        }

        if ($formData['snapshots']['directory'] === '') {
            $errors[] = 'Snapshot directory cannot be empty.';
        }

        if ($formData['snapshots']['stateFile'] === '') {
            $errors[] = 'Snapshot state file cannot be empty.';
        }

        if ($formData['snapshots']['filenamePattern'] === '') {
            $errors[] = 'Snapshot filename pattern cannot be empty.';
        }

        if ($formData['snapshots']['timestampFormat'] === '') {
            $errors[] = 'Snapshot timestamp format cannot be empty.';
        }

        if ($formData['mapBounds']['minX'] >= $formData['mapBounds']['maxX']) {
            $errors[] = 'Map bounds for X must have min smaller than max.';
        }

        if ($formData['mapBounds']['minZ'] >= $formData['mapBounds']['maxZ']) {
            $errors[] = 'Map bounds for Z must have min smaller than max.';
        }

        if ($errors === []) {
            try {
                $localConfig = settings_load_local_config($configLocalPath);
                $localConfig = settings_apply_managed_config($localConfig, settings_build_managed_config($formData));
                $saved = @file_put_contents($configLocalPath, settings_export_local_config($localConfig), LOCK_EX);

                if ($saved === false) {
                    $errors[] = 'Could not write config.local.php.';
                } else {
                    header('Location: settings.php?saved=1');
                    exit;
                }
            } catch (Throwable $exception) {
                $errors[] = 'Could not load config.local.php: ' . $exception->getMessage();
            }
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $flash = 'Settings were saved to config.local.php.';
    $flashType = 'success';
}

if (isset($_GET['imported']) && $_GET['imported'] === '1') {
    $flash = 'Settings were imported into config.local.php.';
    $flashType = 'success';
}

if ($errors !== []) {
    $flash = implode(' ', $errors);
    $flashType = 'error';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST['settings_action'] ?? 'save') === 'import') {
        $importFeedback = $flash;
    }
}

$snapshotState = settings_read_json_file($formData['snapshots']['stateFile']);
$snapshotFiles = [];
if (is_dir($formData['snapshots']['directory'])) {
    $snapshotFiles = glob(rtrim($formData['snapshots']['directory'], '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];
    usort($snapshotFiles, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
}
$snapshotFileCount = count($snapshotFiles);
$recentSnapshotFiles = array_slice($snapshotFiles, 0, 8);
$lastSnapshotAtMs = (int) ($snapshotState['lastSnapshotAtMs'] ?? 0);
$lastSnapshotFile = (string) ($snapshotState['lastSnapshotFile'] ?? '');
$managedConfig = settings_build_managed_config($formData);
$configPreview = settings_export_local_config($managedConfig);
$documentTitle = (string) ($formData['app']['pageTitle'] !== '' ? $formData['app']['pageTitle'] : $appTitle);
$themeStyle = settings_build_theme_style($formData['design']);
$lastSnapshotLabel = $lastSnapshotFile !== '' ? basename($lastSnapshotFile) : 'No snapshots written yet';
$snapshotFilenamePreview = settings_build_snapshot_filename_preview($formData['snapshots']);
$settingsCssVersion = (string) (@filemtime(__DIR__ . '/settings.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="settings.css?v=<?php echo htmlspecialchars($settingsCssVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body style="<?php echo htmlspecialchars($themeStyle, ENT_QUOTES, 'UTF-8'); ?>">
    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">Project Settings</p>
                <h1>Settings</h1>
                <p class="copy">Work through focused tabs instead of one long page. General copy, design colors, telemetry behavior, frontend tuning, map sources, snapshots, and config transfer now live in their own spaces while still saving into <code>config.local.php</code>.</p>
                <span class="hero-badge <?php echo $formData['snapshots']['enabled'] ? 'live' : ''; ?>">
                    <?php echo htmlspecialchars(settings_format_bool($formData['snapshots']['enabled']), ENT_QUOTES, 'UTF-8'); ?> for live snapshot capture
                </span>
            </div>
            <div class="hero-side">
                <a class="back" href="indexV2.php">Back to dashboard</a>
                <div class="stats">
                    <article class="stat">
                        <small>Snapshot Interval</small>
                        <strong><?php echo htmlspecialchars(settings_format_ms((int) $formData['snapshots']['intervalMs']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Minimum time between saved snapshots.</span>
                    </article>
                    <article class="stat">
                        <small>Snapshots</small>
                        <strong><?php echo htmlspecialchars((string) $snapshotFileCount, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo htmlspecialchars($lastSnapshotLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </article>
                    <article class="stat">
                        <small>Last Write</small>
                        <strong><?php echo htmlspecialchars(settings_format_datetime_ms($lastSnapshotAtMs), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Read from the snapshot state file.</span>
                    </article>
                    <article class="stat">
                        <small>Theme Accent</small>
                        <strong><?php echo htmlspecialchars($formData['design']['accentColor'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Shared by the dashboard and this settings page.</span>
                    </article>
                </div>
            </div>
        </section>

        <?php if ($flash !== null): ?>
            <div class="flash <?php echo $flashType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form class="form settings-form" method="post" action="settings.php" enctype="multipart/form-data">
            <section class="panel tabs-shell">
                <div class="head">
                    <div>
                        <p class="eyebrow">Workspace</p>
                        <h2>Settings Tabs</h2>
                    </div>
                    <span class="badge">config.local.php</span>
                </div>
                <div class="settings-tabs" role="tablist" aria-label="Settings sections">
                    <button class="settings-tab is-active" type="button" role="tab" id="settings-tab-general" aria-controls="settings-panel-general" aria-selected="true" data-settings-tab="general">General</button>
                    <button class="settings-tab" type="button" role="tab" id="settings-tab-telemetry" aria-controls="settings-panel-telemetry" aria-selected="false" data-settings-tab="telemetry">Telemetry</button>
                    <button class="settings-tab" type="button" role="tab" id="settings-tab-frontend" aria-controls="settings-panel-frontend" aria-selected="false" data-settings-tab="frontend">Frontend</button>
                    <button class="settings-tab" type="button" role="tab" id="settings-tab-maps" aria-controls="settings-panel-maps" aria-selected="false" data-settings-tab="maps">Maps</button>
                    <button class="settings-tab" type="button" role="tab" id="settings-tab-snapshots" aria-controls="settings-panel-snapshots" aria-selected="false" data-settings-tab="snapshots">Snapshots</button>
                    <button class="settings-tab" type="button" role="tab" id="settings-tab-transfer" aria-controls="settings-panel-transfer" aria-selected="false" data-settings-tab="transfer">Transfer</button>
                </div>
                <p class="sub">Switch tabs without losing edits. Save writes the managed settings sections here, while matching environment variables still override file-based values at runtime.</p>
            </section>

            <section class="settings-tab-panel is-active" id="settings-panel-general" role="tabpanel" aria-labelledby="settings-tab-general" data-settings-panel="general">
                <div class="layout tab-layout">
                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Branding</p>
                                <h2>General Copy</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="app-page-title">Page title</label>
                                <input id="app-page-title" name="app_page_title" type="text" value="<?php echo htmlspecialchars($formData['app']['pageTitle'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Used in the browser tab and dashboard heading context.</span>
                            </div>
                            <div class="field">
                                <label for="app-hero-eyebrow">Hero eyebrow</label>
                                <input id="app-hero-eyebrow" name="app_hero_eyebrow" type="text" value="<?php echo htmlspecialchars($formData['app']['heroEyebrow'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Small label above the main dashboard title.</span>
                            </div>
                            <div class="field">
                                <label for="app-hero-title">Hero title</label>
                                <input id="app-hero-title" name="app_hero_title" type="text" value="<?php echo htmlspecialchars($formData['app']['heroTitle'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">The large dashboard headline shown on the home screen.</span>
                            </div>
                            <div class="field">
                                <label for="app-meta-description">Meta description</label>
                                <input id="app-meta-description" name="app_meta_description" type="text" value="<?php echo htmlspecialchars($formData['app']['metaDescription'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Used by page metadata and browser previews.</span>
                            </div>
                            <div class="field full">
                                <label for="app-hero-summary">Hero summary</label>
                                <textarea id="app-hero-summary" name="app_hero_summary" rows="4"><?php echo htmlspecialchars($formData['app']['heroSummary'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="hint">Intro copy shown beneath the hero title while the dashboard is idle or connecting.</span>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Theme</p>
                                <h2>Design Colors</h2>
                            </div>
                            <span class="badge">Live theme</span>
                        </div>
                        <p class="sub">These colors are shared by the main dashboard and this settings UI, so you can quickly tune the visual feel without editing CSS by hand.</p>
                        <div class="form-grid color-grid">
                            <div class="field color-field">
                                <label for="design-accent-color">Primary accent</label>
                                <div class="color-control">
                                    <input id="design-accent-color" name="design_accent_color" type="color" value="<?php echo htmlspecialchars(strtolower($formData['design']['accentColor']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="color-code"><?php echo htmlspecialchars($formData['design']['accentColor'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="hint">Used for the main glow, focus states, and active accents.</span>
                            </div>
                            <div class="field color-field">
                                <label for="design-accent-secondary-color">Secondary accent</label>
                                <div class="color-control">
                                    <input id="design-accent-secondary-color" name="design_accent_secondary_color" type="color" value="<?php echo htmlspecialchars(strtolower($formData['design']['accentSecondaryColor']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="color-code"><?php echo htmlspecialchars($formData['design']['accentSecondaryColor'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="hint">Used for cool highlights, borders, and secondary emphasis.</span>
                            </div>
                            <div class="field color-field">
                                <label for="design-accent-warm-color">Warm highlight</label>
                                <div class="color-control">
                                    <input id="design-accent-warm-color" name="design_accent_warm_color" type="color" value="<?php echo htmlspecialchars(strtolower($formData['design']['accentWarmColor']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="color-code"><?php echo htmlspecialchars($formData['design']['accentWarmColor'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="hint">Used for warm contrast details and emphasis moments.</span>
                            </div>
                            <div class="field color-field">
                                <label for="design-success-color">Success color</label>
                                <div class="color-control">
                                    <input id="design-success-color" name="design_success_color" type="color" value="<?php echo htmlspecialchars(strtolower($formData['design']['successColor']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="color-code"><?php echo htmlspecialchars($formData['design']['successColor'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="hint">Shown for healthy status states and active capture feedback.</span>
                            </div>
                            <div class="field color-field">
                                <label for="design-danger-color">Danger color</label>
                                <div class="color-control">
                                    <input id="design-danger-color" name="design_danger_color" type="color" value="<?php echo htmlspecialchars(strtolower($formData['design']['dangerColor']), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="color-code"><?php echo htmlspecialchars($formData['design']['dangerColor'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="hint">Used for disconnect, warning, and alert-oriented states.</span>
                            </div>
                        </div>
                        <div class="head visual-tuning-head">
                            <div>
                                <p class="eyebrow">Style</p>
                                <h2>Visual Controls</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field full">
                                <label for="design-font-family">UI font stack</label>
                                <input id="design-font-family" name="design_font_family" type="text" value="<?php echo htmlspecialchars($formData['design']['fontFamily'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">CSS font-family stack used by the dashboard and settings page, for example <code>&quot;Space Grotesk&quot;, &quot;Segoe UI&quot;, sans-serif</code>.</span>
                            </div>
                            <div class="field">
                                <label for="design-font-scale">Font scale</label>
                                <input id="design-font-scale" name="design_font_scale" type="number" min="0.85" max="1.4" step="0.05" value="<?php echo htmlspecialchars((string) $formData['design']['fontScale'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Scales the overall interface typography up or down without editing CSS.</span>
                            </div>
                            <div class="field">
                                <label for="design-panel-radius-px">Panel roundness</label>
                                <input id="design-panel-radius-px" name="design_panel_radius_px" type="number" min="16" max="40" step="1" value="<?php echo htmlspecialchars((string) $formData['design']['panelRadiusPx'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Controls the corner radius used by the main dashboard and settings panels.</span>
                            </div>
                            <div class="field full">
                                <label for="design-glass-blur-px">Glass blur strength</label>
                                <input id="design-glass-blur-px" name="design_glass_blur_px" type="number" min="0" max="40" step="1" value="<?php echo htmlspecialchars((string) $formData['design']['glassBlurPx'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Adjusts the backdrop blur used by the glass-style surfaces. Lower values look crisper; higher values look softer.</span>
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            <section class="settings-tab-panel" id="settings-panel-telemetry" role="tabpanel" aria-labelledby="settings-tab-telemetry" data-settings-panel="telemetry" hidden>
                <div class="layout tab-layout">
                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Backend</p>
                                <h2>Telemetry Pipeline</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field full">
                                <label for="telemetry-upstream-url">Telemetry upstream URL</label>
                                <input id="telemetry-upstream-url" name="telemetry_upstream_url" type="text" value="<?php echo htmlspecialchars($formData['telemetry']['upstreamUrl'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Local ETS2 telemetry endpoint that PHP reads from before the dashboard serves its own JSON.</span>
                            </div>
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <span class="toggle-title">Pretty print telemetry JSON</span>
                                    <span>Formats telemetry responses for easier manual inspection and debug output.</span>
                                </div>
                                <label class="switch" aria-label="Pretty print telemetry JSON">
                                    <input type="checkbox" name="telemetry_json_pretty_print" value="1" <?php echo $formData['telemetry']['jsonPrettyPrint'] ? 'checked' : ''; ?>>
                                    <span class="track"></span>
                                </label>
                            </div>
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <span class="toggle-title">Enable telemetry cache</span>
                                    <span>Keeps a recent JSON payload available when the live upstream endpoint is slow or offline.</span>
                                </div>
                                <label class="switch" aria-label="Enable telemetry cache">
                                    <input type="checkbox" name="telemetry_cache_enabled" value="1" <?php echo $formData['telemetry']['cacheEnabled'] ? 'checked' : ''; ?>>
                                    <span class="track"></span>
                                </label>
                            </div>
                            <div class="field">
                                <label for="telemetry-refresh-interval-ms">Refresh interval</label>
                                <input id="telemetry-refresh-interval-ms" name="telemetry_refresh_interval_ms" type="number" min="100" step="50" value="<?php echo htmlspecialchars((string) $formData['telemetry']['refreshIntervalMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Server-side cadence used by the telemetry bridge.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-request-timeout-ms">Request timeout</label>
                                <input id="telemetry-request-timeout-ms" name="telemetry_request_timeout_ms" type="number" min="500" step="100" value="<?php echo htmlspecialchars((string) $formData['telemetry']['requestTimeoutMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">How long PHP waits for the upstream telemetry endpoint.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-cache-ttl-ms">Cache TTL</label>
                                <input id="telemetry-cache-ttl-ms" name="telemetry_cache_ttl_ms" type="number" min="0" step="100" value="<?php echo htmlspecialchars((string) $formData['telemetry']['cacheTtlMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">How long cached telemetry stays usable in milliseconds.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-cache-file">Cache file path</label>
                                <input id="telemetry-cache-file" name="telemetry_cache_file" type="text" value="<?php echo htmlspecialchars($formData['telemetry']['cacheFile'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">The JSON file written and read by the telemetry cache layer.</span>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Runtime</p>
                                <h2>Live Status</h2>
                            </div>
                            <span class="badge"><?php echo htmlspecialchars(settings_format_ms((int) $formData['telemetry']['refreshIntervalMs']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="runtime">
                            <div class="row"><strong>Page title</strong><span><?php echo htmlspecialchars($formData['app']['pageTitle'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Telemetry endpoint</strong><span><?php echo htmlspecialchars($formData['frontend']['telemetryEndpoint'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Upstream URL</strong><span><?php echo htmlspecialchars($formData['telemetry']['upstreamUrl'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Refresh interval</strong><span><?php echo htmlspecialchars(settings_format_ms((int) $formData['telemetry']['refreshIntervalMs']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Request timeout</strong><span><?php echo htmlspecialchars(settings_format_ms((int) $formData['telemetry']['requestTimeoutMs']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Cache status</strong><span><?php echo htmlspecialchars(settings_format_bool($formData['telemetry']['cacheEnabled']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Cache TTL</strong><span><?php echo htmlspecialchars(settings_format_ms((int) $formData['telemetry']['cacheTtlMs']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Cache file</strong><span><?php echo htmlspecialchars($formData['telemetry']['cacheFile'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>JSON output</strong><span><?php echo htmlspecialchars($formData['telemetry']['jsonPrettyPrint'] ? 'Pretty printed' : 'Compact', ENT_QUOTES, 'UTF-8'); ?></span></div>
                        </div>
                        <div class="note">Environment variables still win over matching file settings, so this page is best for local defaults and day-to-day project tuning.</div>
                    </section>
                </div>
            </section>

            <section class="settings-tab-panel" id="settings-panel-frontend" role="tabpanel" aria-labelledby="settings-tab-frontend" data-settings-panel="frontend" hidden>
                <div class="layout tab-layout">
                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Browser</p>
                                <h2>Frontend Behavior</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field full">
                                <label for="frontend-telemetry-endpoint">Frontend telemetry endpoint</label>
                                <input id="frontend-telemetry-endpoint" name="frontend_telemetry_endpoint" type="text" value="<?php echo htmlspecialchars($formData['frontend']['telemetryEndpoint'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">The endpoint the browser polls for fresh dashboard data.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-storage-key-active-tab">Active tab storage key</label>
                                <input id="frontend-storage-key-active-tab" name="frontend_storage_key_active_tab" type="text" value="<?php echo htmlspecialchars($formData['frontend']['storageKeys']['activeTab'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Local storage key used to remember the selected dashboard tab.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-storage-key-map-preferences">Map preferences key</label>
                                <input id="frontend-storage-key-map-preferences" name="frontend_storage_key_map_preferences" type="text" value="<?php echo htmlspecialchars($formData['frontend']['storageKeys']['mapPreferences'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Local storage key for zoom, follow, and map view preferences.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-polling-backoff-step-ms">Polling backoff step</label>
                                <input id="telemetry-polling-backoff-step-ms" name="telemetry_polling_backoff_step_ms" type="number" min="0" step="100" value="<?php echo htmlspecialchars((string) $formData['telemetryPolling']['backoffStepMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">How much retry delay increases after a failed fetch.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-polling-max-backoff-ms">Polling max backoff</label>
                                <input id="telemetry-polling-max-backoff-ms" name="telemetry_polling_max_backoff_ms" type="number" min="0" step="100" value="<?php echo htmlspecialchars((string) $formData['telemetryPolling']['maxBackoffMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Upper limit for automatic retry delay during connection issues.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-polling-minimum-interval-ms">Polling minimum interval</label>
                                <input id="telemetry-polling-minimum-interval-ms" name="telemetry_polling_minimum_interval_ms" type="number" min="100" step="50" value="<?php echo htmlspecialchars((string) $formData['telemetryPolling']['minimumIntervalMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Lowest allowed browser polling delay after refresh and backoff rules are applied.</span>
                            </div>
                            <div class="field">
                                <label for="telemetry-polling-cache-multiplier">Cache retry multiplier</label>
                                <input id="telemetry-polling-cache-multiplier" name="telemetry_polling_cache_multiplier" type="number" min="1" step="1" value="<?php echo htmlspecialchars((string) $formData['telemetryPolling']['cacheMultiplier'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Extra slowdown applied when the frontend is reading cached telemetry instead of live upstream data.</span>
                            </div>
                            <div class="field full">
                                <label for="telemetry-polling-hidden-interval-ms">Polling while tab is hidden</label>
                                <input id="telemetry-polling-hidden-interval-ms" name="telemetry_polling_hidden_interval_ms" type="number" min="0" step="100" value="<?php echo htmlspecialchars((string) $formData['telemetryPolling']['hiddenIntervalMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Reduced polling cadence when the browser tab is not visible.</span>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Tuning</p>
                                <h2>Driving Panels</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="route-planner-average-kph">Route planner average kph</label>
                                <input id="route-planner-average-kph" name="route_planner_average_kph" type="number" min="1" step="1" value="<?php echo htmlspecialchars((string) $formData['routePlanner']['averageKph'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Used for route time estimation on the dashboard.</span>
                            </div>
                            <div class="field">
                                <label for="route-planner-real-time-scale">Route planner real time scale</label>
                                <input id="route-planner-real-time-scale" name="route_planner_real_time_scale" type="number" min="0.1" step="0.1" value="<?php echo htmlspecialchars((string) $formData['routePlanner']['realTimeScale'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Converts ETS2 in-game travel time into real-world time.</span>
                            </div>
                            <div class="field">
                                <label for="speed-ring-max-display-kph">Speed ring max display</label>
                                <input id="speed-ring-max-display-kph" name="speed_ring_max_display_kph" type="number" min="1" step="1" value="<?php echo htmlspecialchars((string) $formData['speedRing']['maxDisplayKph'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Top of the main speed ring scale on the dashboard.</span>
                            </div>
                            <div class="field">
                                <label for="speed-ring-overspeed-tolerance-kph">Overspeed tolerance</label>
                                <input id="speed-ring-overspeed-tolerance-kph" name="speed_ring_overspeed_tolerance_kph" type="number" min="0" step="0.1" value="<?php echo htmlspecialchars((string) $formData['speedRing']['overspeedToleranceKph'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Extra speed buffer before the dashboard warns about overspeed.</span>
                            </div>
                            <div class="field full">
                                <label for="speed-ring-trend-sensitivity-kph">Trend sensitivity</label>
                                <input id="speed-ring-trend-sensitivity-kph" name="speed_ring_trend_sensitivity_kph" type="number" min="0" step="0.1" value="<?php echo htmlspecialchars((string) $formData['speedRing']['trendSensitivityKph'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">How much speed change is needed before the trend indicator reacts.</span>
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            <section class="settings-tab-panel" id="settings-panel-maps" role="tabpanel" aria-labelledby="settings-tab-maps" data-settings-panel="maps" hidden>
                <div class="layout tab-layout">
                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Defaults</p>
                                <h2>Map Behavior</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="frontend-map-default-world-zoom">World map default zoom</label>
                                <input id="frontend-map-default-world-zoom" name="frontend_map_default_world_zoom" type="number" min="0" step="1" value="<?php echo htmlspecialchars((string) $formData['mapDefaults']['worldZoom'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Starting zoom for the larger route map when no saved browser preference exists yet.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-default-hero-zoom">Hero map default zoom</label>
                                <input id="frontend-map-default-hero-zoom" name="frontend_map_default_hero_zoom" type="number" min="0" step="1" value="<?php echo htmlspecialchars((string) $formData['mapDefaults']['heroZoom'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Starting zoom for the compact live map card before local storage takes over.</span>
                            </div>
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <span class="toggle-title">World map follows truck by default</span>
                                    <span>Keeps the larger map centered on the truck until a viewer manually pans away.</span>
                                </div>
                                <label class="switch" aria-label="World map follows truck by default">
                                    <input type="checkbox" name="frontend_map_default_world_follow_truck" value="1" <?php echo $formData['mapDefaults']['worldFollowTruck'] ? 'checked' : ''; ?>>
                                    <span class="track"></span>
                                </label>
                            </div>
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <span class="toggle-title">Hero map follows truck by default</span>
                                    <span>Keeps the speed-panel map locked to the truck until the viewer switches to free pan.</span>
                                </div>
                                <label class="switch" aria-label="Hero map follows truck by default">
                                    <input type="checkbox" name="frontend_map_default_hero_follow_truck" value="1" <?php echo $formData['mapDefaults']['heroFollowTruck'] ? 'checked' : ''; ?>>
                                    <span class="track"></span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">World Bounds</p>
                                <h2>Map Coordinates</h2>
                            </div>
                        </div>
                        <p class="sub">These limits define the coordinate extents used when converting ETS2 telemetry positions into map locations.</p>
                        <div class="form-grid">
                            <div class="field">
                                <label for="frontend-map-bounds-min-x">Minimum X</label>
                                <input id="frontend-map-bounds-min-x" name="frontend_map_bounds_min_x" type="number" step="0.1" value="<?php echo htmlspecialchars((string) $formData['mapBounds']['minX'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Left-most supported game world coordinate.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-bounds-max-x">Maximum X</label>
                                <input id="frontend-map-bounds-max-x" name="frontend_map_bounds_max_x" type="number" step="0.1" value="<?php echo htmlspecialchars((string) $formData['mapBounds']['maxX'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Right-most supported game world coordinate.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-bounds-min-z">Minimum Z</label>
                                <input id="frontend-map-bounds-min-z" name="frontend_map_bounds_min_z" type="number" step="0.1" value="<?php echo htmlspecialchars((string) $formData['mapBounds']['minZ'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Lowest supported north/south coordinate.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-bounds-max-z">Maximum Z</label>
                                <input id="frontend-map-bounds-max-z" name="frontend_map_bounds_max_z" type="number" step="0.1" value="<?php echo htmlspecialchars((string) $formData['mapBounds']['maxZ'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Highest supported north/south coordinate.</span>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Tiles</p>
                                <h2>Map Sources</h2>
                            </div>
                            <span class="badge"><?php echo htmlspecialchars((string) $formData['frontend']['mapTiles']['overzoomSteps'], ENT_QUOTES, 'UTF-8'); ?> overzoom</span>
                        </div>
                        <div class="form-grid">
                            <div class="field full">
                                <label for="frontend-map-tiles-base-urls">Tile base URL candidates</label>
                                <textarea id="frontend-map-tiles-base-urls" name="frontend_map_tiles_base_urls" rows="5"><?php echo htmlspecialchars(implode(PHP_EOL, $formData['frontend']['mapTiles']['baseUrlCandidates']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="hint">One URL or relative path per line. The dashboard tries these in order when loading tiles. Update the first line here if your tile server changes.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-tiles-config-names">Tile config filenames</label>
                                <textarea id="frontend-map-tiles-config-names" name="frontend_map_tiles_config_names" rows="5"><?php echo htmlspecialchars(implode(PHP_EOL, $formData['frontend']['mapTiles']['configNames']), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="hint">Possible metadata files searched under each tile source.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-tiles-overzoom-steps">Overzoom steps</label>
                                <input id="frontend-map-tiles-overzoom-steps" name="frontend_map_tiles_overzoom_steps" type="number" min="0" step="1" value="<?php echo htmlspecialchars((string) $formData['frontend']['mapTiles']['overzoomSteps'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Extra zoom levels allowed past the tile source native maximum.</span>
                            </div>
                            <div class="field">
                                <label for="frontend-map-tiles-retry-delay-ms">Tile retry delay</label>
                                <input id="frontend-map-tiles-retry-delay-ms" name="frontend_map_tiles_retry_delay_ms" type="number" min="1000" step="500" value="<?php echo htmlspecialchars((string) $formData['frontend']['mapTiles']['retryDelayMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">How long the frontend waits before rechecking tile metadata when no map source is available yet.</span>
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            <section class="settings-tab-panel" id="settings-panel-snapshots" role="tabpanel" aria-labelledby="settings-tab-snapshots" data-settings-panel="snapshots" hidden>
                <div class="layout tab-layout">
                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Capture</p>
                                <h2>Snapshot Settings</h2>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <span class="toggle-title">Enable snapshots</span>
                                    <span>When enabled, the telemetry pipeline writes timestamped files into the configured snapshot directory.</span>
                                </div>
                                <label class="switch" aria-label="Enable snapshots">
                                    <input type="checkbox" name="snapshot_enabled" value="1" <?php echo $formData['snapshots']['enabled'] ? 'checked' : ''; ?>>
                                    <span class="track"></span>
                                </label>
                            </div>
                            <div class="toggle-card">
                                <div class="toggle-copy">
                                    <span class="toggle-title">Pretty print snapshots</span>
                                    <span>Formatted files are easier to inspect; compact files are smaller and faster to diff.</span>
                                </div>
                                <label class="switch" aria-label="Pretty print snapshot JSON">
                                    <input type="checkbox" name="snapshot_pretty_print" value="1" <?php echo $formData['snapshots']['prettyPrint'] ? 'checked' : ''; ?>>
                                    <span class="track"></span>
                                </label>
                            </div>
                            <div class="field">
                                <label for="snapshot-interval-ms">Snapshot interval</label>
                                <input id="snapshot-interval-ms" name="snapshot_interval_ms" type="number" min="1000" step="1000" value="<?php echo htmlspecialchars((string) $formData['snapshots']['intervalMs'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">The pipeline waits at least this long between saved snapshots.</span>
                            </div>
                            <div class="field">
                                <label for="snapshot-state-file">Snapshot state file</label>
                                <input id="snapshot-state-file" name="snapshot_state_file" type="text" value="<?php echo htmlspecialchars($formData['snapshots']['stateFile'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Tracks the last saved snapshot to avoid duplicate writes.</span>
                            </div>
                            <div class="field full">
                                <label for="snapshot-directory">Snapshot directory</label>
                                <input id="snapshot-directory" name="snapshot_directory" type="text" value="<?php echo htmlspecialchars($formData['snapshots']['directory'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Files like <code>telemetry-2026-04-15T21-13-45-432Z.json</code> will be written here.</span>
                            </div>
                            <div class="field">
                                <label for="snapshot-filename-prefix">Snapshot filename prefix</label>
                                <input id="snapshot-filename-prefix" name="snapshot_filename_prefix" type="text" value="<?php echo htmlspecialchars($formData['snapshots']['filenamePrefix'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Inserted wherever <code>{prefix}</code> appears in the naming pattern.</span>
                            </div>
                            <div class="field">
                                <label for="snapshot-timestamp-format">Snapshot timestamp format</label>
                                <input id="snapshot-timestamp-format" name="snapshot_timestamp_format" type="text" value="<?php echo htmlspecialchars($formData['snapshots']['timestampFormat'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">PHP date format used for the <code>{date}</code> token, like <code>Y-m-d\TH-i-s</code>.</span>
                            </div>
                            <div class="field full">
                                <label for="snapshot-filename-pattern">Snapshot filename pattern</label>
                                <input id="snapshot-filename-pattern" name="snapshot_filename_pattern" type="text" value="<?php echo htmlspecialchars($formData['snapshots']['filenamePattern'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="hint">Available tokens: <code>{prefix}</code>, <code>{date}</code>, <code>{ms}</code>, and <code>{ext}</code>. Preview: <code><?php echo htmlspecialchars($snapshotFilenamePreview, ENT_QUOTES, 'UTF-8'); ?></code></span>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Status</p>
                                <h2>Snapshot Runtime</h2>
                            </div>
                            <span class="badge <?php echo $formData['snapshots']['enabled'] ? 'live' : ''; ?>"><?php echo htmlspecialchars(settings_format_bool($formData['snapshots']['enabled']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="runtime">
                            <div class="row"><strong>Snapshot interval</strong><span><?php echo htmlspecialchars(settings_format_ms((int) $formData['snapshots']['intervalMs']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Snapshot dir</strong><span><?php echo htmlspecialchars($formData['snapshots']['directory'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>State file</strong><span><?php echo htmlspecialchars($formData['snapshots']['stateFile'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>JSON format</strong><span><?php echo htmlspecialchars($formData['snapshots']['prettyPrint'] ? 'Pretty printed' : 'Compact', ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>File prefix</strong><span><?php echo htmlspecialchars($formData['snapshots']['filenamePrefix'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Timestamp format</strong><span><code><?php echo htmlspecialchars($formData['snapshots']['timestampFormat'], ENT_QUOTES, 'UTF-8'); ?></code></span></div>
                            <div class="row"><strong>Filename preview</strong><span><?php echo htmlspecialchars($snapshotFilenamePreview, ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Total files</strong><span><?php echo htmlspecialchars((string) $snapshotFileCount, ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Last snapshot</strong><span><?php echo htmlspecialchars(settings_format_datetime_ms($lastSnapshotAtMs), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="row"><strong>Last file</strong><span><?php echo htmlspecialchars($lastSnapshotLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
                        </div>
                        <div class="note">Snapshot creation happens inside the PHP telemetry pipeline, so files appear when telemetry is actually being served.</div>
                    </section>
                </div>

                <section class="panel panel-spaced">
                    <div class="head">
                        <div>
                            <p class="eyebrow">Recent Output</p>
                            <h2>Latest Snapshot Files</h2>
                        </div>
                    </div>
                    <?php if ($recentSnapshotFiles === []): ?>
                        <div class="empty">No snapshot files were found in the configured directory yet. Once snapshots are enabled and telemetry is being served, the latest archive files will appear here.</div>
                    <?php else: ?>
                        <div class="files">
                            <?php foreach ($recentSnapshotFiles as $snapshotFile): ?>
                                <article class="file">
                                    <strong><?php echo htmlspecialchars(basename($snapshotFile), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="meta"><?php echo htmlspecialchars($snapshotFile, ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars((string) filesize($snapshotFile), ENT_QUOTES, 'UTF-8'); ?> bytes • <?php echo htmlspecialchars(date('Y-m-d H:i:s', (int) (filemtime($snapshotFile) ?: time())), ENT_QUOTES, 'UTF-8'); ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="settings-tab-panel" id="settings-panel-transfer" role="tabpanel" aria-labelledby="settings-tab-transfer" data-settings-panel="transfer" hidden>
                <div class="layout tab-layout">
                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Transfer</p>
                                <h2>Import And Export</h2>
                            </div>
                        </div>
                        <div class="actions">
                            <p>Download the current managed settings as JSON for round-trip import, or export a PHP version for manual backup and diffing.</p>
                            <div class="button-cluster">
                                <a class="button-secondary" href="settings.php?export=json">Export JSON</a>
                                <a class="button-secondary" href="settings.php?export=php">Export PHP</a>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field full">
                                <label for="import-config-file">Import config JSON file</label>
                                <input id="import-config-file" name="import_config_file" type="file" accept=".json,application/json">
                                <span class="hint">Upload a previously exported JSON settings file.</span>
                            </div>
                            <div class="field full">
                                <label for="import-config-json">Import config JSON</label>
                                <textarea id="import-config-json" name="import_config_json" rows="8" placeholder="{&#10;  &quot;app&quot;: { ... },&#10;  &quot;design&quot;: { ... },&#10;  &quot;telemetry&quot;: { ... }&#10;}"><?php echo htmlspecialchars($importJsonInput, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <span class="hint">You can also paste exported JSON directly here. Only the managed settings sections are imported.</span>
                            </div>
                        </div>
                        <?php if ($importFeedback !== null): ?>
                            <div class="inline-feedback error"><?php echo htmlspecialchars($importFeedback, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="actions">
                            <p>Import updates the same settings sections this page edits and keeps unrelated local config keys intact.</p>
                            <button class="button-secondary" type="submit" name="settings_action" value="import">Import Config</button>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="head">
                            <div>
                                <p class="eyebrow">Preview</p>
                                <h2>Generated Config Snippet</h2>
                            </div>
                        </div>
                        <pre><?php echo htmlspecialchars($configPreview, ENT_QUOTES, 'UTF-8'); ?></pre>
                        <div class="note">Saving here updates <code>app</code>, <code>design</code>, <code>telemetry</code>, <code>snapshots</code>, <code>frontend.telemetryEndpoint</code>, <code>frontend.storageKeys</code>, <code>frontend.telemetryPolling</code>, <code>frontend.routePlanner</code>, <code>frontend.speedRing</code>, <code>frontend.mapDefaults</code>, <code>frontend.mapBounds</code>, and <code>frontend.mapTiles</code> in <code>config.local.php</code>. Other local config keys are preserved.</div>
                    </section>
                </div>
            </section>

            <section class="panel savebar">
                <div>
                    <p class="eyebrow">Apply Changes</p>
                    <h2>Save The Managed Settings</h2>
                    <p class="sub">Save after editing any tab. The current tab stays remembered locally so you can jump back into the same area on your next visit.</p>
                </div>
                <div class="button-cluster">
                    <a class="button-secondary" href="settings.php?export=json">Quick Export</a>
                    <button class="save" type="submit" name="settings_action" value="save">Save All Settings</button>
                </div>
            </section>
        </form>
    </main>

    <script src="settings.js"></script>
</body>
</html>
