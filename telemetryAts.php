<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

define(
    'AMERICAN_TRUCK_SIMULATOR_TELEMETRY',
    (string) dashboard_config_value(
        'telemetry.atsUpstreamUrl',
        (string) dashboard_config_value('telemetry.upstreamUrl', 'http://127.0.0.1:31377/api/ets2/telemetry')
    )
);
define(
    'AMERICAN_TRUCK_SIMULATOR_TELEMETRY_REFRESH_INTERVAL_MS',
    (int) dashboard_config_value('telemetry.refreshIntervalMs', 250)
);

function ats_telemetry_http_status_code(array $headers): ?int
{
    if (!isset($headers[0]) || !is_string($headers[0])) {
        return null;
    }

    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headers[0], $matches) === 1) {
        return (int) $matches[1];
    }

    return null;
}

function ats_telemetry_cache_read(string $cachePath, int $cacheTtlMs): ?array
{
    if (!is_file($cachePath)) {
        return null;
    }

    $raw = @file_get_contents($cachePath);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $cachedAtMs = (int) ($decoded['cachedAtMs'] ?? 0);
    $nowMs = (int) floor(microtime(true) * 1000);
    if ($cachedAtMs <= 0 || ($nowMs - $cachedAtMs) > max(0, $cacheTtlMs)) {
        return null;
    }

    $data = $decoded['data'] ?? null;
    if (!is_array($data)) {
        return null;
    }

    return [
        'cachedAtMs' => $cachedAtMs,
        'data' => $data,
    ];
}

function ats_telemetry_cache_write(string $cachePath, array $data, ?int $cachedAtMs = null): int
{
    $cacheDir = dirname($cachePath);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
        return (int) ($cachedAtMs ?? floor(microtime(true) * 1000));
    }

    $effectiveCachedAtMs = (int) ($cachedAtMs ?? floor(microtime(true) * 1000));
    $payload = [
        'cachedAtMs' => $effectiveCachedAtMs,
        'data' => $data,
    ];

    @file_put_contents($cachePath, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $effectiveCachedAtMs;
}

function ats_telemetry_snapshot_read_state(string $statePath): array
{
    if (!is_file($statePath)) {
        return [];
    }

    $raw = @file_get_contents($statePath);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ats_telemetry_snapshot_write_state(string $statePath, array $state): void
{
    $stateDir = dirname($statePath);
    if (!is_dir($stateDir)) {
        @mkdir($stateDir, 0777, true);
    }

    if (!is_dir($stateDir) || !is_writable($stateDir)) {
        return;
    }

    @file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function ats_telemetry_snapshot_build_filename(int $timestampMs): string
{
    $seconds = sprintf('%.6F', $timestampMs / 1000);
    $date = \DateTimeImmutable::createFromFormat('U.u', $seconds, new \DateTimeZone('UTC'));
    if (!$date instanceof \DateTimeImmutable) {
        return 'telemetry-ats-' . gmdate('Y-m-d\TH-i-s\Z') . '.json';
    }

    $prefix = trim((string) dashboard_config_value('snapshots.atsFilenamePrefix', 'telemetry-ats-'));
    $pattern = trim((string) dashboard_config_value('snapshots.filenamePattern', '{prefix}{date}-{ms}Z.{ext}'));
    $timestampFormat = trim((string) dashboard_config_value('snapshots.timestampFormat', 'Y-m-d\TH-i-s'));

    if ($pattern === '') {
        $pattern = '{prefix}{date}-{ms}Z.{ext}';
    }

    if ($timestampFormat === '') {
        $timestampFormat = 'Y-m-d\TH-i-s';
    }

    $filename = strtr($pattern, [
        '{prefix}' => $prefix,
        '{date}' => $date->format($timestampFormat),
        '{ms}' => str_pad((string) ($timestampMs % 1000), 3, '0', STR_PAD_LEFT),
        '{ext}' => 'json',
    ]);
    $sanitized = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/', '-', $filename);
    $sanitized = is_string($sanitized) ? trim($sanitized, ". \t\n\r\0\x0B") : '';

    return $sanitized !== '' ? $sanitized : sprintf(
        'telemetry-ats-%s-%03dZ.json',
        $date->format('Y-m-d\TH-i-s'),
        $timestampMs % 1000
    );
}

function ats_telemetry_snapshot_capture_if_due(array $data, array $source): void
{
    if (!(bool) dashboard_config_value('snapshots.enabled', false)) {
        return;
    }

    if (!in_array((string) ($source['type'] ?? 'none'), ['upstream', 'cache'], true)) {
        return;
    }

    $intervalMs = max(1000, (int) dashboard_config_value('snapshots.intervalMs', 60000));
    $snapshotDir = (string) dashboard_config_value('snapshots.atsDirectory', __DIR__ . '/snapshots/ats');
    $statePath = (string) dashboard_config_value('snapshots.atsStateFile', __DIR__ . '/tmp/snapshot-ats-state.json');
    $prettyPrint = (bool) dashboard_config_value('snapshots.prettyPrint', true);
    $sourceCachedAtMs = (int) ($source['cachedAtMs'] ?? 0);
    $nowMs = (int) floor(microtime(true) * 1000);
    $state = ats_telemetry_snapshot_read_state($statePath);
    $lastSnapshotAtMs = (int) ($state['lastSnapshotAtMs'] ?? 0);
    $lastSourceCachedAtMs = (int) ($state['lastSourceCachedAtMs'] ?? 0);

    if ($sourceCachedAtMs > 0 && $sourceCachedAtMs === $lastSourceCachedAtMs) {
        return;
    }

    if ($lastSnapshotAtMs > 0 && ($nowMs - $lastSnapshotAtMs) < $intervalMs) {
        return;
    }

    if (!is_dir($snapshotDir)) {
        @mkdir($snapshotDir, 0777, true);
    }

    if (!is_dir($snapshotDir) || !is_writable($snapshotDir)) {
        return;
    }

    $snapshotTakenAtMs = $nowMs;
    $snapshotPayload = [
        'snapshotTakenAt' => gmdate('c', (int) floor($snapshotTakenAtMs / 1000)),
        'snapshotTakenAtMs' => $snapshotTakenAtMs,
        'source' => [
            'type' => (string) ($source['type'] ?? 'none'),
            'statusCode' => $source['statusCode'] ?? null,
            'cachedAtMs' => $sourceCachedAtMs > 0 ? $sourceCachedAtMs : null,
            'error' => $source['error'] ?? null,
        ],
        'data' => $data,
    ];
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if ($prettyPrint) {
        $flags |= JSON_PRETTY_PRINT;
    }

    $snapshotPath = rtrim($snapshotDir, '/\\') . DIRECTORY_SEPARATOR . ats_telemetry_snapshot_build_filename($snapshotTakenAtMs);
    $encodedPayload = json_encode($snapshotPayload, $flags);
    if ($encodedPayload === false) {
        return;
    }

    if (@file_put_contents($snapshotPath, $encodedPayload . PHP_EOL, LOCK_EX) === false) {
        return;
    }

    ats_telemetry_snapshot_write_state($statePath, [
        'lastSnapshotAtMs' => $snapshotTakenAtMs,
        'lastSourceCachedAtMs' => $sourceCachedAtMs,
        'lastSnapshotFile' => basename($snapshotPath),
    ]);
}

function get_ats_telemetry_refresh_interval_ms(): int
{
    return AMERICAN_TRUCK_SIMULATOR_TELEMETRY_REFRESH_INTERVAL_MS;
}

function fetch_ats_telemetry_data($telemetry_url = AMERICAN_TRUCK_SIMULATOR_TELEMETRY, &$source = null): array
{
    $source = [
        'type' => 'none',
        'statusCode' => null,
        'error' => null,
        'cachedAtMs' => null,
    ];

    $timeoutMs = (int) dashboard_config_value('telemetry.requestTimeoutMs', 4500);
    $timeoutSeconds = max(1.0, $timeoutMs / 1000);
    $cacheEnabled = (bool) dashboard_config_value('telemetry.cacheEnabled', true);
    $cacheTtlMs = (int) dashboard_config_value('telemetry.cacheTtlMs', 10000);
    $cachePath = (string) dashboard_config_value('telemetry.atsCacheFile', __DIR__ . '/tmp/telemetry-ats-cache.json');
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeoutSeconds,
            'header' => "Accept: application/json\r\nUser-Agent: ETS2WebDashboard/1.0\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $telemetryData = @file_get_contents($telemetry_url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = ats_telemetry_http_status_code(is_array($responseHeaders) ? $responseHeaders : []);
    $source['statusCode'] = $statusCode;

    if ($telemetryData !== false && $statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
        $jsonData = json_decode($telemetryData, true);
        if (is_array($jsonData)) {
            $cachedAtMs = (int) floor(microtime(true) * 1000);
            if ($cacheEnabled) {
                $cachedAtMs = ats_telemetry_cache_write($cachePath, $jsonData, $cachedAtMs);
            }

            $source['type'] = 'upstream';
            $source['cachedAtMs'] = $cachedAtMs;
            ats_telemetry_snapshot_capture_if_due($jsonData, $source);
            return $jsonData;
        }

        $source['error'] = 'Invalid JSON in upstream response';
    } else {
        $source['error'] = $telemetryData === false
            ? 'Unable to reach telemetry upstream'
            : 'Telemetry upstream returned non-success status';
    }

    if ($cacheEnabled) {
        $cachedRecord = ats_telemetry_cache_read($cachePath, $cacheTtlMs);
        if (is_array($cachedRecord) && is_array($cachedRecord['data'] ?? null)) {
            $source['type'] = 'cache';
            $source['cachedAtMs'] = (int) ($cachedRecord['cachedAtMs'] ?? 0);
            ats_telemetry_snapshot_capture_if_due($cachedRecord['data'], $source);
            return $cachedRecord['data'];
        }
    }

    $source['type'] = 'none';
    return [];
}

$telemetry_source = null;
$json_data = fetch_ats_telemetry_data(AMERICAN_TRUCK_SIMULATOR_TELEMETRY, $telemetry_source);

if (
    basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '') &&
    (($_GET['format'] ?? '') === 'json')
) {
    $jsonFlags = JSON_UNESCAPED_SLASHES;
    if ((bool) dashboard_config_value('telemetry.jsonPrettyPrint', true)) {
        $jsonFlags |= JSON_PRETTY_PRINT;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode([
        'refreshIntervalMs' => get_ats_telemetry_refresh_interval_ms(),
        'fetchedAt' => gmdate('c'),
        'source' => $telemetry_source,
        'data' => $json_data,
    ], $jsonFlags);
    exit;
}
