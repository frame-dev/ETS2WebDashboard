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
        'fontFamily' => '"Space Grotesk", "Aptos", "Segoe UI", sans-serif',
        'fontScale' => 1.0,
        'heroMapPlayerFontSizeRem' => 0.95,
        'panelRadiusPx' => 28,
        'glassBlurPx' => 26,
    ],
    'telemetry' => [
        'upstreamUrl' => 'http://127.0.0.1:31377/api/ets2/telemetry',
        'refreshIntervalMs' => 250,
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
        'filenamePrefix' => 'telemetry-',
        'filenamePattern' => '{prefix}{date}-{ms}Z.{ext}',
        'timestampFormat' => 'Y-m-d\\TH-i-s',
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
            'baseUrlCandidates' => ['http://10.147.17.64/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081'],
            'configNames' => ['config.json', 'TileMapInfo.json'],
            'overzoomSteps' => 3,
            'retryDelayMs' => 8000,
        ],
        'mapSources' => [
            [
                'id' => 'standard',
                'name' => 'Standard',
                'baseUrlCandidates' => ['http://10.147.17.64/tiles/', 'tiles', 'maps', 'http://127.0.0.1:8081'],
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
                'baseUrlCandidates' => ['http://10.147.17.64/tilespromods/'],
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
    ],
];
