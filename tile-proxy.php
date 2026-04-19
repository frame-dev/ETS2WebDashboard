<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function tile_proxy_status_code(array $headers): ?int
{
    if (!isset($headers[0]) || !is_string($headers[0])) {
        return null;
    }

    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headers[0], $matches) === 1) {
        return (int) $matches[1];
    }

    return null;
}

function tile_proxy_normalize_base_url(string $url): ?array
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }

    $port = isset($parts['port'])
        ? (int) $parts['port']
        : ($scheme === 'https' ? 443 : 80);
    $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
    $path = $path === '' ? '/' : $path;

    return [
        'scheme' => $scheme,
        'host' => $host,
        'port' => $port,
        'path' => $path,
    ];
}

function tile_proxy_url_is_allowed(string $requestUrl, array $allowedBaseUrls): bool
{
    $requestParts = tile_proxy_normalize_base_url($requestUrl);
    if ($requestParts === null) {
        return false;
    }

    foreach ($allowedBaseUrls as $baseUrl) {
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            continue;
        }

        $baseParts = tile_proxy_normalize_base_url($baseUrl);
        if ($baseParts === null) {
            continue;
        }

        if (
            $requestParts['scheme'] !== $baseParts['scheme']
            || $requestParts['host'] !== $baseParts['host']
            || $requestParts['port'] !== $baseParts['port']
        ) {
            continue;
        }

        if (
            $requestParts['path'] === $baseParts['path']
            || str_starts_with($requestParts['path'], rtrim($baseParts['path'], '/') . '/')
        ) {
            return true;
        }
    }

    return false;
}

function tile_proxy_allowed_base_urls(): array
{
    $allowedBaseUrls = dashboard_config_value('frontend.mapTiles.baseUrlCandidates', []);
    $normalized = [];

    if (is_array($allowedBaseUrls)) {
        foreach ($allowedBaseUrls as $baseUrl) {
            if (is_string($baseUrl) && trim($baseUrl) !== '') {
                $normalized[] = trim($baseUrl);
            }
        }
    }

    $mapSources = dashboard_config_value('frontend.mapSources', []);
    if (is_array($mapSources)) {
        foreach ($mapSources as $source) {
            $baseUrlCandidates = is_array($source['baseUrlCandidates'] ?? null) ? $source['baseUrlCandidates'] : [];
            foreach ($baseUrlCandidates as $baseUrl) {
                if (is_string($baseUrl) && trim($baseUrl) !== '') {
                    $normalized[] = trim($baseUrl);
                }
            }
        }
    }

    return array_values(array_unique($normalized));
}

function tile_proxy_forward_header(string $headerLine): void
{
    $allowedHeaders = [
        'content-type',
        'content-length',
        'cache-control',
        'etag',
        'last-modified',
        'expires',
        'accept-ranges',
        'content-range',
    ];

    $separatorPos = strpos($headerLine, ':');
    if ($separatorPos === false) {
        return;
    }

    $name = strtolower(trim(substr($headerLine, 0, $separatorPos)));
    if (!in_array($name, $allowedHeaders, true)) {
        return;
    }

    header($headerLine, true);
}

function tile_proxy_client_expects_json(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json')) {
        return true;
    }

    return (string) ($_GET['format'] ?? '') === 'json';
}

function tile_proxy_safe_header_value(string $value): string
{
    return str_replace(["\r", "\n"], ' ', trim($value));
}

function tile_proxy_respond_error(int $statusCode, string $message, ?string $hint = null): never
{
    http_response_code($statusCode);
    header('X-ETS2-Tile-Proxy-Error: ' . tile_proxy_safe_header_value($message));
    header('X-ETS2-Tile-Proxy-Status: ' . (string) $statusCode);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'HEAD') {
        exit;
    }

    if (tile_proxy_client_expects_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => [
                'status' => $statusCode,
                'message' => $message,
                'hint' => $hint,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $hint !== null && trim($hint) !== ''
        ? $message . ' ' . trim($hint)
        : $message;
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    tile_proxy_respond_error(405, 'Method not allowed.', 'Only GET and HEAD requests are supported by the tile proxy.');
}

$requestUrl = trim((string) ($_GET['url'] ?? ''));
if ($requestUrl === '') {
    tile_proxy_respond_error(400, 'Missing tile URL.', 'Pass the target tile or config URL in the url query parameter.');
}

$allowedBaseUrls = tile_proxy_allowed_base_urls();
if (!is_array($allowedBaseUrls) || !tile_proxy_url_is_allowed($requestUrl, $allowedBaseUrls)) {
    tile_proxy_respond_error(403, 'Tile URL is not allowed.', 'Add the matching base URL to frontend.mapTiles.baseUrlCandidates or frontend.mapSources in your dashboard config.');
}

$timeoutSeconds = max(1.0, ((int) dashboard_config_value('telemetry.requestTimeoutMs', 4500)) / 1000);
$context = stream_context_create([
    'http' => [
        'method' => $method,
        'timeout' => $timeoutSeconds,
        'ignore_errors' => true,
        'header' => "Accept: */*\r\nUser-Agent: ETS2WebDashboard/1.0\r\n",
    ],
]);

$body = @file_get_contents($requestUrl, false, $context);
$responseHeaders = $http_response_header ?? [];
$statusCode = tile_proxy_status_code(is_array($responseHeaders) ? $responseHeaders : []);

if ($statusCode === null) {
    tile_proxy_respond_error(502, 'Failed to reach tile server.', 'Check that the configured map tile server is running and reachable from PHP.');
}

http_response_code($statusCode);

foreach ($responseHeaders as $headerLine) {
    if (!is_string($headerLine) || str_starts_with($headerLine, 'HTTP/')) {
        continue;
    }

    tile_proxy_forward_header($headerLine);
}

if ($method === 'HEAD') {
    exit;
}

if ($body !== false) {
    echo $body;
}
