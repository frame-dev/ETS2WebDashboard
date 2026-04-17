# Euro Truck Simulator 2 Web Dashboard

A browser-based ETS2 telemetry dashboard built with PHP, JavaScript, and CSS.

The main panel is `indexV2.php`. The built-in PHP router (`router.php`) also routes the site root to that page, so opening `http://localhost:8000/` loads the new dashboard by default.

## What This Project Includes

- `indexV2.php`: the main live dashboard
- `infos.php`: a secondary information workspace with deeper telemetry sections
- `settings.php`: a browser-based configuration editor for `config.local.php`
- `telemetry.php`: the PHP telemetry fetch, cache, and snapshot pipeline
- `tile-proxy.php`: a controlled proxy for external tile servers used by the map views
- `index.php`: a legacy dashboard page kept alongside the newer V2 panel

## Features

- Live ETS2 telemetry polling from a configurable upstream endpoint
- Main dashboard with a large speed ring, direct km/h telemetry readouts, road-limit and tempomat pills anchored to the top and bottom of the ring, route distance, ETA, scaled real-time ETA, and fuel range
- Dashboard status widgets for connection state, last update time, and active refresh interval
- Draggable and zoomable hero map with center controls, corrected truck heading, and a live job overlay
- Delivery-complete popup with income, XP, trip distance, and parking result
- Direct dashboard links to `settings.php` and the `infos.php` workspace
- Expanded information page in `infos.php` with `Overview`, `Systems`, `World`, and `Debug` tabs
- System and vehicle detail views for truck profile, health, drivetrain, trailer state, controls, lighting, world position, events, and raw telemetry JSON
- Interactive map system with saved browser preferences, configurable bounds, configurable tile sources, tile config discovery, overzoom support, and static fallback rendering
- Optional tile proxying through `tile-proxy.php` for approved map tile sources
- Telemetry backend with upstream fetch control, timeout handling, JSON output for polling, local cache fallback, and cache TTL control
- Integrated snapshot pipeline with timed capture, saved runtime state, duplicate snapshot avoidance, and optional pretty-printed output
- Browser-based settings workspace with tabs for `General`, `Telemetry`, `Frontend`, `Maps`, `Snapshots`, and `Transfer`
- Settings controls for app copy, theme colors, telemetry behavior, route planner tuning, speed ring tuning, polling behavior, storage keys, map bounds, and tile sources
- Config transfer features including JSON import, JSON export, PHP export, and a generated managed config preview
- Managed settings writes back to `config.local.php` while preserving unrelated local config values
- Config system with defaults in `config.php`, local overrides in `config.local.php`, environment-variable overrides, and theme color sanitization

## Requirements

- PHP 8.1 or newer recommended
- Euro Truck Simulator 2
- A telemetry source compatible with the configured upstream endpoint, such as TruckSim Telemetry / GPS Telemetry Server
- Write access to `tmp/` and `snapshots/` if cache or snapshots are enabled

## Quick Start

1. Clone the repository.
2. Copy `config.local.example.php` to `config.local.php`.
3. Update the telemetry source or any local overrides you want.
4. Start the local PHP server:

```bash
php -S localhost:8000 router.php
```

5. Open `http://localhost:8000/` or `http://localhost:8000/indexV2.php` for the main dashboard.
6. Open `http://localhost:8000/infos.php` for the extended telemetry workspace.
7. Open `http://localhost:8000/settings.php` to manage configuration from the browser.

## Pages

### Main Dashboard

`indexV2.php` is the primary dashboard screen.

It focuses on fast, at-a-glance driving information:

- speed and limit awareness
- live route status
- hero map with drag and zoom controls
- delivery completion popup
- connection and refresh status

### Info Workspace

`infos.php` exposes a denser telemetry view using tabs:

- `Overview`
- `Systems`
- `World`
- `Debug`

This page is useful when you want more detailed truck, trailer, controls, events, and raw payload visibility than the main dashboard shows.

### Settings Workspace

`settings.php` is the browser-based configuration workspace for the project.

It is split into these tabs:

- `General`
- `Telemetry`
- `Frontend`
- `Maps`
- `Snapshots`
- `Transfer`

The settings page edits the managed parts of `config.local.php` without requiring manual PHP edits for every change.

### Refresh And Speed Units

- The default telemetry refresh interval is `250ms`.
- Frontend speed values are treated as the telemetry source's native km/h values and are not converted with `* 3.6`.
- The hero speed ring uses the same km/h values for the center speed, overspeed state, peak tracking, road-limit marker, and cruise-control readout.

## Configuration

Project defaults live in `config.php`. Local overrides belong in `config.local.php`.

Main configuration groups:

- `app`
- `design`
- `telemetry`
- `snapshots`
- `frontend.telemetryEndpoint`
- `frontend.telemetryPolling`
- `frontend.speedRing`
- `frontend.storageKeys`
- `frontend.routePlanner`
- `frontend.mapBounds`
- `frontend.mapTiles`

### Example `config.local.php`

```php
<?php

declare(strict_types=1);

return [
    'app' => [
        'pageTitle' => 'ETS2 Dashboard (Local)',
        'heroTitle' => 'Local command center online',
        'heroSummary' => 'Watching the local telemetry feed in real time.',
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
        'refreshIntervalMs' => 250,
        'requestTimeoutMs' => 4500,
        'cacheEnabled' => true,
        'cacheTtlMs' => 10000,
    ],
    'snapshots' => [
        'enabled' => true,
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
        'routePlanner' => [
            'averageKph' => 63,
            'realTimeScale' => 17.5,
        ],
        'storageKeys' => [
            'activeTab' => 'ets2-dashboard-active-tab',
            'mapPreferences' => 'ets2-dashboard-map-preferences',
        ],
        'mapBounds' => [
            'minX' => -94118.3,
            'maxX' => 128280,
            'minZ' => -102857,
            'maxZ' => 57201.3,
        ],
        'mapTiles' => [
            'baseUrlCandidates' => ['http://10.147.17.64/tiles/', 'tiles', 'maps'],
            'configNames' => ['config.json', 'TileMapInfo.json'],
            'overzoomSteps' => 3,
        ],
    ],
];
```

## Environment Variable Overrides

`config.php` also supports environment variable overrides for the runtime-sensitive parts of the stack.

Supported variables include:

- `ETS2_TELEMETRY_UPSTREAM_URL`
- `ETS2_TELEMETRY_REFRESH_MS`
- `ETS2_TELEMETRY_TIMEOUT_MS`
- `ETS2_TELEMETRY_JSON_PRETTY`
- `ETS2_TELEMETRY_CACHE_ENABLED`
- `ETS2_TELEMETRY_CACHE_TTL_MS`
- `ETS2_TELEMETRY_CACHE_FILE`
- `ETS2_SNAPSHOTS_ENABLED`
- `ETS2_SNAPSHOTS_INTERVAL_MS`
- `ETS2_SNAPSHOTS_DIRECTORY`
- `ETS2_SNAPSHOTS_STATE_FILE`
- `ETS2_SNAPSHOTS_PRETTY_PRINT`

## Telemetry Pipeline

`telemetry.php` is the backend pipeline used by the dashboard.

It handles:

- fetching telemetry from the configured upstream URL
- validating HTTP success responses
- decoding JSON payloads
- writing and reading a local telemetry cache
- falling back to cached telemetry if upstream is unavailable
- capturing timed snapshots to disk
- tracking snapshot state in `tmp/snapshot-state.json`

The frontend uses `telemetry.php?format=json` as its default polling endpoint.

## Map And Tile System

The project supports both a static preview map and tile-backed map rendering.

Relevant pieces:

- `index.js`: frontend map rendering, dragging, zooming, map preference persistence, route timing calculations
- `tile-proxy.php`: optional proxy for allowed external tile sources
- `map-ets2-preview.jpg`: local static fallback image

`tile-proxy.php` only allows requests to configured tile base URLs from `frontend.mapTiles.baseUrlCandidates`.

## Snapshots

Snapshot generation is integrated into the PHP telemetry pipeline.

When enabled:

- timestamped JSON files are written into `snapshots/`
- runtime state is stored in `tmp/snapshot-state.json`
- repeated writes from the same cached source can be skipped

There is also a standalone `snapshot.js` script in the repository, but the main project flow uses the PHP-integrated snapshot system.

## Project Structure

- `indexV2.php`: main dashboard page
- `indexV2.css`: main dashboard styling
- `infos.php`: extended telemetry workspace
- `infos.css`: styling for the info workspace
- `settings.php`: settings/configuration workspace
- `settings.css`: settings page styling
- `settings.js`: settings page tab behavior
- `index.js`: frontend telemetry, maps, tabs, and rendering logic
- `telemetry.php`: telemetry fetch, cache, accessor, and snapshot pipeline
- `tile-proxy.php`: tile request proxy with allowed-base-url checks
- `config.php`: project defaults and environment override handling
- `config.local.example.php`: local config example
- `router.php`: PHP built-in server router that serves `indexV2.php` by default
- `index.php`: legacy dashboard page
- `index.css`: legacy dashboard styling
- `map-ets2-preview.jpg`: static map fallback image
- `snapshot.js`: standalone snapshot helper script
- `tmp/`: cache and runtime state files
- `snapshots/`: saved telemetry snapshots

## Notes

- `router.php` makes the project root load `indexV2.php`, so the V2 dashboard is the default landing page.
- The repository currently keeps the older `index.php` alongside the newer V2 dashboard.
- The settings page manages a defined set of configuration keys and preserves unrelated local config values during import/update flows.

## License

Open-source and free to customize for your own setup.
