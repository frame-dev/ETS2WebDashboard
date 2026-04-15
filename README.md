# Euro Truck Simulator 2 Web Dashboard

A browser-based dashboard for viewing live Euro Truck Simulator 2 telemetry with PHP and JavaScript. It combines a glass-styled control panel, a route-aware driving dashboard, optional snapshot archiving, and a browser settings workspace for tuning the project without editing every config key by hand.

## Features

- Real-time telemetry display from a local ETS2 telemetry server
- Live dashboard panels for speed, route progress, truck systems, trailer state, map position, and debug data
- Interactive map view with zoom controls, saved preferences, configurable tile sources, and configurable world bounds
- Tunable frontend polling, route planner, speed ring, storage key, and telemetry endpoint settings
- Optional telemetry caching and optional periodic telemetry snapshots handled by the PHP telemetry pipeline
- Browser-based Settings page with tabs for General, Telemetry, Frontend, Maps, Snapshots, and Transfer
- Theme and design color controls that update the main dashboard styling
- Import and export for managed configuration as JSON or PHP
- Local override support through `config.local.php`

## Requirements

- PHP 8.1 or newer recommended
- A running ETS2 telemetry source such as TruckSim Telemetry / GPS Telemetry Server
- Euro Truck Simulator 2 telemetry available at the configured upstream endpoint
- Write access to `tmp/` and `snapshots/` if caching or snapshots are enabled

## Quick Start

1. Clone the repository.
2. Copy `config.local.example.php` to `config.local.php`.
3. Update the telemetry source or any local overrides you want.
4. Start the local PHP server:

```bash
php -S localhost:8000 router.php
```

5. Open `http://localhost:8000` in your browser.
6. Open `http://localhost:8000/settings.php` to manage project settings from the UI.

## Configuration

Project defaults live in `config.php`. Local overrides belong in `config.local.php`.

Current configurable areas include:

- `app`: page title and hero copy
- `design`: dashboard accent, secondary, warm, success, and danger colors
- `telemetry`: upstream URL, refresh interval, timeout, pretty printing, cache settings
- `snapshots`: enable flag, interval, directory, state file, pretty printing
- `frontend.telemetryEndpoint`
- `frontend.telemetryPolling`
- `frontend.routePlanner`
- `frontend.speedRing`
- `frontend.storageKeys`
- `frontend.mapBounds`
- `frontend.mapTiles`

Example local config:

```php
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
        'cacheEnabled' => true,
    ],
    'snapshots' => [
        'enabled' => true,
        'intervalMs' => 60000,
        'directory' => __DIR__ . '/snapshots',
        'stateFile' => __DIR__ . '/tmp/snapshot-state.json',
        'prettyPrint' => true,
    ],
    'frontend' => [
        'telemetryPolling' => [
            'hiddenIntervalMs' => 12000,
        ],
        'speedRing' => [
            'maxDisplayKph' => 130,
        ],
    ],
];
```

## Settings Page

`settings.php` is the main browser-based configuration workspace.

It currently supports:

- tabbed editing instead of a single long page
- live theme color controls
- telemetry and caching controls
- frontend behavior and map tuning
- snapshot management and recent snapshot visibility
- config import/export
- writing managed settings back to `config.local.php`

The tab state is remembered locally in the browser so you return to the same section you were editing.

## Snapshots

Snapshot generation is integrated into the PHP telemetry pipeline in `telemetry.php`.

When snapshots are enabled:

- timestamped JSON files are written to `snapshots/`
- snapshot state is tracked in `tmp/snapshot-state.json`
- duplicate writes can be skipped based on the saved state

There is also a standalone `snapshot.js` utility in the repo, but the main project workflow now uses the PHP-integrated snapshot pipeline.

## Project Structure

- `index.php`: main dashboard page
- `index.js`: dashboard frontend logic
- `index.css`: dashboard styling
- `telemetry.php`: telemetry fetch, cache, and snapshot pipeline
- `settings.php`: browser settings page
- `settings.css`: settings page styling
- `settings.js`: settings page tab behavior
- `config.php`: project defaults
- `config.local.example.php`: local override example
- `router.php`: router for PHP's built-in server

## License

Open-source and free to customize for your own setup.
