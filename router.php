<?php

declare(strict_types=1);

$docRoot = __DIR__;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$normalizedPath = ltrim(urldecode($requestPath), '/');
$targetPath = $normalizedPath === '' ? $docRoot : $docRoot . DIRECTORY_SEPARATOR . $normalizedPath;
$realTargetPath = realpath($targetPath);

if (
    $realTargetPath !== false
    && str_starts_with($realTargetPath, $docRoot)
    && is_file($realTargetPath)
) {
    return false;
}

require $docRoot . DIRECTORY_SEPARATOR . 'index.php';
