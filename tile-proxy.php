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

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$requestUrl = trim((string) ($_GET['url'] ?? ''));
if ($requestUrl === '') {
    http_response_code(400);
    echo 'Missing tile URL.';
    exit;
}

$allowedBaseUrls = dashboard_config_value('frontend.mapTiles.baseUrlCandidates', []);
if (!is_array($allowedBaseUrls) || !tile_proxy_url_is_allowed($requestUrl, $allowedBaseUrls)) {
    http_response_code(403);
    echo 'Tile URL is not allowed.';
    exit;
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
    http_response_code(502);
    echo 'Failed to reach tile server.';
    exit;
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
