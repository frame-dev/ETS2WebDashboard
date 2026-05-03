# ETS2 Web Dashboard Features

## Overview

This project provides browser-based Euro Truck Simulator 2 and American Truck Simulator dashboards built with PHP, JavaScript, and CSS. It focuses on fast live telemetry, a polished driving HUD, map tracking, remote player overlays, and a browser-managed configuration flow.

## Core Dashboard

- Live telemetry dashboard served by `indexV2.php`
- Root URL routing through `router.php`
- Large central speed ring with live km/h display
- Road-limit readout grouped above the ring inside the same speed panel
- Tempomat readout grouped below the ring inside the same speed panel
- Peak-speed, trend, and overspeed status chips
- Connection status, last update time, and active refresh interval indicators
- Inline dashboard notice cards for telemetry failures, cached fallback mode, and map or tile issues
- In-app `Help` overlay with dashboard guidance, shortcuts, and troubleshooting notes
- Route summary with source and destination, distance, ETA, real-time ETA, and fuel range
- Toggleable telemetry insight widgets with route progress, fuel-use sparkline, and engine RPM load
- New-job popup centered on the hero map with cargo, route, income, distance, weight, and deadline details
- Delivery-complete popup with income, XP, distance, and parking result
- Live event popup logic that only shows job-start and delivery-finished popups on real telemetry transitions, not on page refresh
- Main-toolbar `TruckersMP` toggle for area-player visibility
- Other-player overlays sourced from both TruckersMP and direct telemetry URLs
- ATS dashboard variant at `/ats` with ATS telemetry, map sources, cache, snapshots, and info workspace wiring

## Telemetry Behavior

- Configurable telemetry polling interval with a default of `250ms`
- Player polling defaults aligned to `250ms`
- Telemetry values rendered directly in km/h where provided by the source
- Configurable upstream telemetry endpoint
- Separate ATS upstream, cache, snapshot, and frontend endpoint settings
- Request timeout handling for stalled telemetry requests
- Cached telemetry fallback when upstream data is unavailable
- Source-aware connection state reporting for live, cached, and failed updates
- Frontend polling controls for retry backoff, hidden-tab cadence, minimum interval, and cached-response slowdown
- Direct remote-player aggregation through `telemetry.php?format=remotePlayers`
- Server-side persistence of direct telemetry URLs through `telemetry.php?format=saveRemoteTelemetryUrls`
- More detailed frontend error messaging for failed telemetry responses and invalid JSON payloads

## Map Features

- Hero map with draggable and zoomable tile rendering
- Center-on-truck controls for the hero map
- Configurable default zoom and follow-truck behavior for both map views
- Corrected and stabilized truck heading for the live map marker
- Job overlay with income, cargo, and weight
- Player overlays on both the hero map and the world map
- Configurable default TruckersMP radius and server id
- Configurable hero-map player label font size
- Static preview fallback when live tiles are unavailable
- Configurable world bounds for truck positioning
- Tile-source discovery from configurable base URLs and config names
- Named map selection shared between the hero map and world map, including `Standard` and `ProMods`
- ATS-specific map source support with fallback to shared map configuration when needed
- Automatic fallback-bound switching based on the selected map (`Standard` or `ProMods`)
- Saved browser preference for the selected map source, including `Standard` and `ProMods`
- Separate saved zoom and follow-truck browser preferences for each map source, including `Standard` and `ProMods`
- Configurable tile retry delay when live tile metadata is unavailable
- Optional tile proxy support through `tile-proxy.php`
- Tile proxy errors returned in a frontend-readable format for better user feedback
- Saved browser map preferences for zoom and follow behavior

## Information Workspace

- Secondary telemetry page in `infos.php`
- Tabbed detail views for `Overview`, `Systems`, `World`, and `Debug`
- Recent-delivery job history in the `Overview` tab, including cargo, route, income, XP, parking result, filtering, export, and clear actions
- Alert-group controls in the `Overview` tab for systems, overspeed, low fuel, fatigue, damage, deadline, status, and fines
- Direct telemetry URL form for loading other-player telemetry endpoints
- Truck profile and vehicle identity details
- Drivetrain, controls, lighting, and trailer status sections
- World position, events, and raw telemetry JSON views

## Settings And Configuration

- Browser-based settings editor in `settings.php`
- Managed writes to `config.local.php`
- JSON import and export tools
- PHP config export
- Generated managed config preview
- Config sections for app copy, design, telemetry, maps, snapshots, and frontend behavior
- Inline import feedback for upload failures, invalid JSON, and missing managed config sections
- Visual settings for shared UI typography, hero-map player labels, and surface styling
- Frontend player settings for refresh interval, search radius, and default server
- Frontend layout settings for per-device map defaults, overlay placement, and mobile dashboard tuning
- Snapshot naming controls for prefix, timestamp format, and filename pattern
- Snapshot management UI for ETS2 and ATS archive counts, sizes, downloads, per-file deletion, bulk cleanup, pruning, and runtime state resets
- Support for named frontend map sources such as `Standard` and `ProMods` in addition to legacy single-source tile configuration
- Local override support layered on top of defaults from `config.php`
- Environment-variable overrides for runtime-sensitive telemetry and snapshot settings
- Shared PHP helpers for escaping, frontend JSON bootstrapping, and config array fallbacks across dashboard entry points

## Snapshot And Cache Pipeline

- Telemetry backend handled by `telemetry.php`
- Local telemetry cache stored in `tmp/`
- Cache TTL control
- Optional timed snapshots written to `snapshots/`
- Snapshot state tracking to avoid unnecessary duplicate writes
- Optional pretty-printed JSON output
- Configurable snapshot filename prefix and pattern tokens

## Frontend Experience

- Purpose-built dashboard UI rather than a plain data dump
- Responsive layout for desktop and smaller screens
- Styled speed ring, status pills, and hero map overlays
- Shared design variables for colors, font stack, font scale, panel roundness, and glass blur
- Shared design variable for hero-map player name font size
- Live UI updates without full-page refreshes

## Local Run Support

- `run.bat` for Windows local startup
- `run.sh` for macOS and Linux local startup
- `run.sh` checks for PHP `8.0+`, `curl`, and HTTPS support before launch
- `run.sh` creates `.runtime/`, `tmp/`, `snapshots/`, and bootstraps `config.local.php` when needed
- Built-in PHP server routing through `router.php`

## Project Files

- `indexV2.php`: main dashboard
- `indexV2Ats.php`: American Truck Simulator dashboard
- `indexV2.css`: main dashboard styling
- `index.js`: frontend telemetry rendering, map logic, and interactivity
- `infos.php`: extended telemetry workspace
- `infos.css`: styling for the info workspace
- `settings.php`: browser configuration workspace
- `settings.css`: styling for the settings workspace
- `settings.js`: settings workspace tab behavior
- `telemetry.php`: telemetry fetch, cache, snapshot, and remote-player pipeline
- `telemetryAts.php`: ATS telemetry fetch, cache, and snapshot pipeline
- `tile-proxy.php`: optional tile proxy
- `router.php`: built-in server router
- `run.bat`: Windows launcher
- `run.sh`: macOS/Linux launcher
- `config.php`: defaults and environment override handling
- `config.local.example.php`: local configuration example
