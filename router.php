<?php

declare(strict_types=1);

$docRoot = __DIR__;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

function dashboard_router_path_is_inside(string $path, string $root): bool
{
    $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');

    return $normalizedPath === $normalizedRoot
        || str_starts_with($normalizedPath, $normalizedRoot . '/');
}

$routeAliases = [
    '/' => 'indexV2.php',
    '/ets2' => 'indexV2.php',
    '/ets2/' => 'indexV2.php',
    '/ats' => 'indexV2Ats.php',
    '/ats/' => 'indexV2Ats.php',
];

if (isset($routeAliases[$requestPath])) {
    require $docRoot . DIRECTORY_SEPARATOR . $routeAliases[$requestPath];
    return true;
}

$normalizedPath = ltrim(urldecode($requestPath), '/');
$pathSegments = array_values(array_filter(
    preg_split('#[\\\\/]+#', $normalizedPath) ?: [],
    static fn(string $segment): bool => $segment !== ''
));

foreach ($pathSegments as $segment) {
    if ($segment === '.' || $segment === '..') {
        http_response_code(404);
        return true;
    }
}

$blockedTopLevelPaths = [
    '.git',
    '.runtime',
    'config.local.php',
    'snapshots',
    'tmp',
];

$topLevelPath = $pathSegments[0] ?? '';
if (in_array($topLevelPath, $blockedTopLevelPaths, true)) {
    http_response_code(404);
    return true;
}

$targetPath = $normalizedPath === '' ? $docRoot : $docRoot . DIRECTORY_SEPARATOR . $normalizedPath;
$realTargetPath = realpath($targetPath);

if (
    $realTargetPath !== false
    && dashboard_router_path_is_inside($realTargetPath, $docRoot)
    && is_file($realTargetPath)
) {
    return false;
}

$extension = pathinfo($normalizedPath, PATHINFO_EXTENSION);
if ($extension !== '') {
    http_response_code(404);
    return true;
}

require $docRoot . DIRECTORY_SEPARATOR . 'indexV2.php';
