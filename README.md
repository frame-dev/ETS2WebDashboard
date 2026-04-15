# Euro Truck Simulator 2 Web Dashboard

A web dashboard for viewing real-time telemetry from Euro Truck Simulator 2 in the browser. It is built with PHP and JavaScript and gives you a clean live view of truck status, job progress, route details, map data, and raw telemetry.

## Features

- Real-time telemetry display from the local ETS2 telemetry server
- Live dashboard panels for speed, fuel, drivetrain, lights, trailer, world state, and events
- Interactive map view with zoom controls and saved map preferences
- Configurable polling, caching, and timeout settings
- Optional telemetry snapshots stored in the `snapshots` directory
- Settings page for managing snapshot configuration from the browser
- Local configuration support through `config.local.php`
- Lightweight setup using PHP's built-in server

## Requirements

- PHP 8.1 or newer recommended
- Trucksim GPS Telemetry Server running and configured to provide telemetry data
- Euro Truck Simulator 2 telemetry data available at the configured upstream endpoint
- Write access to `tmp` and `snapshots` when caching or snapshots are enabled

## Installation

1. Clone this repository.
2. Copy `config.local.example.php` to `config.local.php`.
3. Adjust your local settings as needed.
4. Start the local PHP server:

```bash
php -S localhost:8000 router.php
```

5. Open `http://localhost:8000` in your browser.

## Configuration

Most project options are defined in `config.php`. Override them locally in `config.local.php`.

Example:

```php
<?php

declare(strict_types=1);

return [
    'telemetry' => [
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
];
```

## Snapshot Settings

Snapshot settings can be managed in two ways:

- Edit `config.local.php` manually
- Open `settings.php` in the browser and save the snapshot options there

When snapshots are enabled, the telemetry pipeline periodically stores JSON snapshots in `snapshots/`.

## Development

- Main dashboard page: `index.php`
- Frontend logic: `index.js`
- Dashboard styles: `index.css`
- Settings page: `settings.php`
- Settings styles: `settings.css`
- Telemetry fetch and cache logic: `telemetry.php`

## License

Open-source and free to customize for your own setup.
