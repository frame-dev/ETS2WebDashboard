# ETS2 Web Dashboard Features

## Overview

This project provides a browser-based Euro Truck Simulator 2 dashboard built with PHP, JavaScript, and CSS. It focuses on fast live telemetry, a polished driving HUD, map tracking, and a browser-managed configuration flow.

## Core Dashboard

- Live telemetry dashboard served by `indexV2.php`
- Root URL routing through `router.php`
- Large central speed ring with live km/h display
- Road-limit readout anchored to the top of the ring
- Tempomat readout anchored to the bottom of the ring
- Peak-speed, trend, and overspeed status chips
- Connection status, last update time, and active refresh interval indicators
- Route summary with source/destination, distance, ETA, real-time ETA, and fuel range
- Delivery-complete popup with income, XP, distance, and parking result

## Telemetry Behavior

- Configurable polling interval with a default of `250ms`
- Telemetry values rendered directly in km/h where provided by the source
- Configurable upstream telemetry endpoint
- Request timeout handling for stalled telemetry requests
- Cached telemetry fallback when upstream data is unavailable
- Source-aware connection state reporting for live, cached, and failed updates

## Map Features

- Hero map with draggable and zoomable tile rendering
- Center-on-truck controls for the hero map
- Corrected truck heading for the live map marker
- Job overlay with income, cargo, and weight
- Static preview fallback when live tiles are unavailable
- Configurable world bounds for truck positioning
- Tile-source discovery from configurable base URLs and config names
- Optional tile proxy support through `tile-proxy.php`
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
- Local override support layered on top of defaults from `config.php`
- Environment-variable overrides for runtime-sensitive telemetry and snapshot settings

## Snapshot And Cache Pipeline

- Telemetry backend handled by `telemetry.php`
- Local telemetry cache stored in `tmp/`
- Cache TTL control
- Optional timed snapshots written to `snapshots/`
- Snapshot state tracking to avoid unnecessary duplicate writes
- Optional pretty-printed JSON output

## Frontend Experience

- Purpose-built dashboard UI rather than a plain data dump
- Responsive layout for desktop and smaller screens
- Styled speed ring, status pills, and hero map overlays
- Live UI updates without full-page refreshes

## Project Files

- `indexV2.php`: main dashboard
- `indexV2.css`: main dashboard styling
- `index.js`: frontend telemetry rendering, map logic, and interactivity
- `infos.php`: extended telemetry workspace
- `settings.php`: browser configuration workspace
- `telemetry.php`: telemetry fetch, cache, and snapshot pipeline
- `tile-proxy.php`: optional tile proxy
- `router.php`: built-in server router
- `config.php`: defaults and environment override handling
- `config.local.example.php`: local configuration example

