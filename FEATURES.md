# ETS2 Web Dashboard Features

## Overview

This project provides a browser-based Euro Truck Simulator 2 dashboard built with PHP, JavaScript, and CSS. It focuses on fast live telemetry, a polished driving HUD, map tracking, and a browser-managed configuration flow.

## Core Dashboard

- Live telemetry dashboard served by `indexV2.php`
- Root URL routing through `router.php`
- Large central speed ring with live km/h display
- Road-limit readout grouped above the ring inside the same speed panel
- Tempomat readout grouped below the ring inside the same speed panel
- Peak-speed, trend, and overspeed status chips
- Connection status, last update time, and active refresh interval indicators
- Inline dashboard notice cards for telemetry failures, cached fallback mode, and map or tile issues
- Route summary with source/destination, distance, ETA, real-time ETA, and fuel range
- Delivery-complete popup with income, XP, distance, and parking result

## Telemetry Behavior

- Configurable polling interval with a default of `250ms`
- Telemetry values rendered directly in km/h where provided by the source
- Configurable upstream telemetry endpoint
- Request timeout handling for stalled telemetry requests
- Cached telemetry fallback when upstream data is unavailable
- Source-aware connection state reporting for live, cached, and failed updates
- Frontend polling controls for retry backoff, hidden-tab cadence, minimum interval, and cached-response slowdown
- More detailed frontend error messaging for failed telemetry responses and invalid JSON payloads

## Map Features

- Hero map with draggable and zoomable tile rendering
- Center-on-truck controls for the hero map
- Configurable default zoom and follow-truck behavior for both map views
- Corrected and stabilized truck heading for the live map marker
- Job overlay with income, cargo, and weight
- Static preview fallback when live tiles are unavailable
- Configurable world bounds for truck positioning
- Tile-source discovery from configurable base URLs and config names
- Configurable tile retry delay when live tile metadata is unavailable
- Optional tile proxy support through `tile-proxy.php`
- Tile proxy errors returned in a frontend-readable format for better user feedback
- Saved browser map preferences for zoom and follow behavior

## Information Workspace

- Secondary telemetry page in `infos.php`
- Tabbed detail views for `Overview`, `Systems`, `World`, and `Debug`
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
- Visual settings for shared UI typography and surface styling
- Snapshot naming controls for prefix, timestamp format, and filename pattern
- Local override support layered on top of defaults from `config.php`
- Environment-variable overrides for runtime-sensitive telemetry and snapshot settings

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
- Live UI updates without full-page refreshes

## Local Run Support

- `run.bat` for Windows local startup
- `run.sh` for macOS and Linux local startup
- Built-in PHP server routing through `router.php`

## Project Files

- `indexV2.php`: main dashboard
- `indexV2.css`: main dashboard styling
- `index.js`: frontend telemetry rendering, map logic, and interactivity
- `infos.php`: extended telemetry workspace
- `settings.php`: browser configuration workspace
- `settings.css`: styling for the settings workspace
- `telemetry.php`: telemetry fetch, cache, and snapshot pipeline
- `tile-proxy.php`: optional tile proxy
- `router.php`: built-in server router
- `run.bat`: Windows launcher
- `run.sh`: macOS/Linux launcher
- `config.php`: defaults and environment override handling
- `config.local.example.php`: local configuration example
