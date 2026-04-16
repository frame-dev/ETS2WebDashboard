<?php

declare(strict_types=1);

return [
    'app' => [
        'pageTitle' => 'ETS2 Dashboard (Local)',
        'heroTitle' => 'Local command center online',
    ],
    'design' => [
        'accentColor' => '#54EFC7',
        'accentSecondaryColor' => '#79C7FF',
        'accentWarmColor' => '#FFBF69',
        'successColor' => '#43D79F',
        'dangerColor' => '#FF7050',
    ],
    'telemetry' => [
        'upstreamUrl' => 'http://127.0.0.1:31377/api/ets2/telemetry',
        'refreshIntervalMs' => 300,
        'requestTimeoutMs' => 5000,
        'jsonPrettyPrint' => true,
        'cacheEnabled' => true,
        'cacheTtlMs' => 10000,
        'cacheFile' => __DIR__ . '/tmp/telemetry-cache.json',
    ],
    'snapshots' => [
        'enabled' => false,
        'intervalMs' => 60000,
        'directory' => __DIR__ . '/snapshots',
        'stateFile' => __DIR__ . '/tmp/snapshot-state.json',
        'prettyPrint' => true,
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
            'baseUrlCandidates' => ['http://10.147.17.64/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081'],
            'configNames' => ['config.json', 'TileMapInfo.json'],
            'overzoomSteps' => 3,
        ],
    ],
];
