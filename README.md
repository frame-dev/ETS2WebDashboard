# Euro Truck Simulator 2 Web Dashboard

A browser-based ETS2 telemetry dashboard built with PHP, JavaScript, and CSS.

The main panel is `indexV2.php`. The built-in PHP router (`router.php`) routes the site root to that page, so opening `http://localhost:8000/` loads the newer dashboard by default.

## What This Project Includes

- `indexV2.php`: the main live dashboard
- `infos.php`: a secondary information workspace with deeper telemetry sections and direct remote-player URL controls
- `settings.php`: a browser-based configuration editor for `config.local.php`
- `telemetry.php`: the PHP telemetry fetch, cache, snapshot, and remote-player pipeline
- `tile-proxy.php`: a controlled proxy for external tile servers used by the map views
- `index.php`: a legacy dashboard page kept alongside the newer V2 panel

## Features

- Live ETS2 telemetry polling from a configurable upstream endpoint
- Shared `250ms` defaults across telemetry refresh, player refresh, and frontend polling floors
- Main dashboard with a large speed ring, direct km/h telemetry readouts, road-limit and tempomat pills, route distance, ETA, scaled real-time ETA, and fuel range
- Dashboard status widgets for connection state, last update time, and active refresh interval
- Dashboard notice cards for telemetry failures, cached fallback mode, and map or tile-source issues
- Built-in `Help` overlay with quick usage guidance, controls, shortcuts, and troubleshooting notes
- Draggable and zoomable hero map with center controls, corrected truck heading, follow defaults, and a live job overlay
- Other-player overlays on both map views, including TruckersMP area players and direct remote telemetry players
- Independent `TruckersMP` toolbar toggle so TruckersMP markers can be shown or hidden without disabling direct telemetry URL players
- Map-centered event popups for live job start and delivery completion events
- New-job popup with cargo, route, income, distance, weight, and deadline details
- Delivery-complete popup with income, XP, trip distance, and parking result
- Direct dashboard links to `settings.php` and the `infos.php` workspace
- Expanded information page in `infos.php` with `Overview`, `Systems`, `World`, and `Debug` tabs
- Recent-delivery job history in `infos.php` with cargo, route, income, XP, parking result, timing details, filtering, export, and clear actions
- Alert visibility controls in `infos.php` for systems, overspeed, low fuel, fatigue, damage, deadline, status, and fines
- Direct telemetry URL form in `infos.php` for loading other players from remote telemetry endpoints
- System and vehicle detail views for truck profile, health, drivetrain, trailer state, controls, lighting, world position, events, and raw telemetry JSON
- Interactive map system with saved browser preferences, remembered `Standard` or `ProMods` map selection, separate saved zoom and follow settings per map source, automatic per-source fallback bounds, tile config discovery, overzoom support, and static fallback rendering
- Optional tile proxying through `tile-proxy.php` for approved map tile sources
- Telemetry backend with upstream fetch control, timeout handling, JSON output for polling, local cache fallback, cache TTL control, remote-player aggregation, direct-URL persistence, and clearer fetch-error reporting
- Integrated snapshot pipeline with timed capture, saved runtime state, duplicate snapshot avoidance, optional pretty-printed output, and configurable snapshot filename patterns
- Browser-based settings workspace with tabs for `General`, `Telemetry`, `Frontend`, `Maps`, `Snapshots`, and `Transfer`
- Settings controls for app copy, theme colors, typography, hero-map player label size, panel styling, telemetry behavior, player polling defaults, route planner tuning, speed ring tuning, polling behavior, storage keys, map defaults, map bounds, snapshot naming, and tile sources
- Config transfer features including JSON import, JSON export, PHP export, generated managed config preview, and clearer inline import-error feedback
- Managed settings writes back to `config.local.php` while preserving unrelated local config values
- Config system with defaults in `config.php`, local overrides in `config.local.php`, environment-variable overrides, and theme sanitization
- Local launcher scripts with `run.bat` for Windows and `run.sh` for macOS or Linux PHP startup, including runtime checks and automatic local-config bootstrapping

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

Or use the included launcher scripts:

- Windows: `run.bat`
- macOS/Linux: `./run.sh`

`run.sh` will:

- prefer `PHP_BIN`, then `.runtime/php/bin/php`, then `php` on your `PATH`
- verify PHP `8.0+`, the `curl` extension, and HTTPS stream support
- create `.runtime/`, `tmp/`, and `snapshots/`
- create `config.local.php` from `config.local.example.php` if it does not exist

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
- built-in help overlay for controls, shortcuts, and troubleshooting
- TruckersMP overlay toggle
- live job-start and delivery-completion popups
- connection and refresh status

### Info Workspace

`infos.php` exposes a denser telemetry view using tabs:

- `Overview`
- `Systems`
- `World`
- `Debug`

This page is useful when you want more detailed truck, trailer, controls, events, and raw payload visibility than the main dashboard shows.

It also includes a recent-deliveries history panel so completed jobs can be reviewed without adding extra clutter to the main dashboard. That history can be filtered, exported to JSON, or cleared from browser storage.

It also includes alert visibility controls for groups such as systems, overspeed, low fuel, fatigue, damage, deadline, status, and fines. Those preferences are shared with the rest of the dashboard through browser storage.

It also includes a direct telemetry URL form for other players. Enter one or more comma-separated telemetry endpoints such as:

- `http://other-pc/telemetry.php?format=json`
- `http://other-pc:31377/api/ets2/telemetry`

Those URLs are proxied through `telemetry.php`, saved into `config.local.php`, and rendered as additional map players.

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

It includes visual controls for shared UI styling, including:

- font stack
- font scale
- hero-map player label size
- panel roundness
- glass blur strength

It also manages the player-overlay defaults:

- `frontend.playersRefreshMs`
- `frontend.playersRadiusDefault`
- `frontend.playersServerDefault`

### Refresh And Speed Units

- The default telemetry refresh interval is `250ms`.
- The default player refresh interval is `250ms`.
- Frontend polling defaults are aligned to `250ms` for minimum interval, hidden-tab interval, and max-backoff behavior, with no extra backoff step by default.
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
- `frontend.remoteTelemetryUrls`
- `frontend.playersRefreshMs`
- `frontend.playersRadiusDefault`
- `frontend.playersServerDefault`
- `frontend.telemetryPolling`
- `frontend.speedRing`
- `frontend.storageKeys`
- `frontend.routePlanner`
- `frontend.mapDefaults`
- `frontend.mapBounds`
- `frontend.mapTiles`
- `frontend.mapSources`

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
        'fontFamily' => '"Space Grotesk", "Aptos", "Segoe UI", sans-serif',
        'fontScale' => 1.0,
        'heroMapPlayerFontSizeRem' => 0.95,
        'panelRadiusPx' => 28,
        'glassBlurPx' => 26,
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
        'filenamePrefix' => 'telemetry-',
        'filenamePattern' => '{prefix}{date}-{ms}Z.{ext}',
        'timestampFormat' => 'Y-m-d\\TH-i-s',
    ],
    'frontend' => [
        'telemetryEndpoint' => 'telemetry.php?format=json',
        'remoteTelemetryUrls' => [
            'http://other-pc/telemetry.php?format=json',
        ],
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
        'routePlanner' => [
            'averageKph' => 63,
            'realTimeScale' => 17.5,
        ],
        'storageKeys' => [
            'activeTab' => 'ets2-dashboard-active-tab',
            'mapPreferences' => 'ets2-dashboard-map-preferences',
            'jobHistory' => 'ets2-dashboard-job-history',
            'alertPreferences' => 'ets2-dashboard-alert-preferences',
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
            'baseUrlCandidates' => ['http://10.147.17.64/tiles/', 'tiles', 'maps'],
            'configNames' => ['config.json', 'TileMapInfo.json'],
            'overzoomSteps' => 3,
            'retryDelayMs' => 8000,
        ],
        'mapSources' => [
            [
                'id' => 'standard',
                'name' => 'Standard',
                'baseUrlCandidates' => ['http://10.147.17.64/tiles/', 'tiles', 'maps'],
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
- aggregating remote player data from direct telemetry URLs
- saving `frontend.remoteTelemetryUrls` back into `config.local.php`

The frontend uses `telemetry.php?format=json` as its default polling endpoint.

Additional telemetry endpoints used by the UI:

- `telemetry.php?format=remotePlayers`
- `telemetry.php?format=saveRemoteTelemetryUrls`

## Map And Tile System

The project supports both a static preview map and tile-backed map rendering.

Relevant pieces:

- `index.js`: frontend map rendering, dragging, zooming, map preference persistence, route timing calculations
- `tile-proxy.php`: optional proxy for allowed external tile sources
- `map-ets2-preview.jpg`: local static fallback image

The map overlays can display:

- your own truck marker
- TruckersMP area players
- direct remote telemetry players from saved URLs

The dashboard can expose named map sources such as `Standard` and `ProMods`, each with its own tile base URLs, config discovery order, retry timing, and fallback bounds. The selected map source is shared between the hero map and world map and remembered in browser storage. Zoom and follow-truck preferences are also stored separately for each map source, so switching between `Standard` and `ProMods` can restore different preferred views.

The main dashboard also uses centered map event popups for live job transitions. A new job shows a short popup with cargo and route details, and a finished delivery shows a completion popup. These are triggered by live telemetry state changes, not by reloading the page.

`tile-proxy.php` only allows requests to configured tile base URLs from `frontend.mapTiles.baseUrlCandidates` and `frontend.mapSources`.

When tile config loading or tile proxy requests fail, the dashboard keeps the static preview visible and shows a user-facing warning notice.

## Snapshots

Snapshot generation is integrated into the PHP telemetry pipeline.

When enabled:

- timestamped JSON files are written into `snapshots/`
- runtime state is stored in `tmp/snapshot-state.json`
- repeated writes from the same cached source can be skipped
- snapshot naming can be tuned with a prefix, date format, and filename pattern tokens

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
- `telemetry.php`: telemetry fetch, cache, accessor, snapshot, and remote-player pipeline
- `tile-proxy.php`: tile request proxy with allowed-base-url checks
- `config.php`: project defaults and environment override handling
- `config.local.example.php`: local config example
- `router.php`: PHP built-in server router that serves `indexV2.php` by default
- `run.bat`: Windows launcher that bootstraps a local PHP runtime and runs `php -S`
- `run.sh`: macOS/Linux launcher that validates PHP requirements, prepares runtime folders, and starts `router.php`
- `index.php`: legacy dashboard page
- `index.css`: legacy dashboard styling
- `map-ets2-preview.jpg`: static map fallback image
- `snapshot.js`: standalone snapshot helper script
- `tmp/`: cache and runtime state files
- `snapshots/`: saved telemetry snapshots

## Feature Roadmap

- [ ] Make the tile server publicly available and test tile proxying with real external sources
- [x] Add more Settings controls for frontend behavior, such as map default zoom level, snapshot naming patterns, map settings, and telemetry polling backoff tuning
- [ ] Add more telemetry details to the info workspace, such as trailer states, control inputs, and event logs
- [ ] Expand frontend settings further with per-device map defaults, overlay placement controls, and mobile-specific dashboard tuning
- [ ] Implement a more robust snapshot management UI and workflow
- [x] Add error handling and user feedback for telemetry fetch failures, invalid config imports, and tile proxy errors
- [ ] Explore additional dashboard widgets or visualizations based on telemetry data, such as fuel consumption graphs, engine load indicators, or route progress bars

## Notes

- `router.php` makes the project root load `indexV2.php`, so the V2 dashboard is the default landing page.
- The settings page manages a defined set of configuration keys and preserves unrelated local config values during import and update flows.

## License

Open-source and free to customize for your own setup.
