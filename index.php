<?php

declare(strict_types=1);

require_once 'telemetry.php';

$refresh_interval_ms = (int) get_telemetry_refresh_interval_ms();
$initial_payload = [
    'refreshIntervalMs' => $refresh_interval_ms,
    'fetchedAt' => gmdate('c'),
    'data' => $json_data,
];

$dashboard_config = [
    'telemetryEndpoint' => 'telemetry.php?format=json',
    'refreshIntervalMs' => $refresh_interval_ms,
    'telemetryRequestTimeoutMs' => 4500,
    'mapTiles' => [
        'baseUrlCandidates' => ['tiles', 'maps', 'http://127.0.0.1:8081'],
        'configNames' => ['config.json', 'TileMapInfo.json'],
        'overzoomSteps' => 3,
    ],
    'initialPayload' => $initial_payload,
];

$json_flags = JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Live ETS2 dashboard for telemetry, route status, systems, and map tracking.">
    <title>ETS2 Command Dashboard</title>
    <link rel="stylesheet" href="index.css">
</head>

<body>
    <noscript>
        <p>This dashboard requires JavaScript to render live telemetry and map updates.</p>
    </noscript>
    <main class="dashboard-shell">
        <section class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">Euro Truck Simulator 2</p>
                <h1 id="hero-title">Command dashboard online</h1>
                <p class="hero-summary" id="hero-summary">Preparing a live operator view from your local telemetry feed.</p>
                <div class="hero-tags" id="hero-tags"></div>
            </div>

            <div class="hero-speed">
                <div class="speed-ring" id="speed-ring">
                    <div class="speed-ring-inner">
                        <span class="speed-value" id="hero-speed-value">0</span>
                        <span class="road-speed" id="road-speed-value">0</span>
                        <span class="cruise-control-speed" id="cruise-control-speed"></span>
                    </div>
                </div>
                <div class="speed-meta">
                    <div class="status-stack">
                        <span class="meta-label">Connection</span>
                        <strong id="connection-status" role="status" aria-live="polite">Connecting...</strong>
                    </div>
                    <div class="status-stack">
                        <span class="meta-label">Last Update</span>
                        <strong id="last-updated" aria-live="polite">Initial load</strong>
                    </div>
                    <div class="status-stack">
                        <span class="meta-label">Refresh</span>
                        <strong id="refresh-interval"><?php echo $refresh_interval_ms; ?> ms</strong>
                    </div>
                </div>
            </div>
            <div class="hero-route">
                <span class="from-to">From - To</span>
                <span class="from-to-value" id="from-to-value">Not Active</span>
                <span class="route-distance" id="route-distance">-- km</span>
                <span class="fuel-range" id="fuel-range">-- km range</span>
                <span class="route-time" id="route-time">ETA --:--</span>
                <span class="route-real-time" id="route-real-time">REAL --:--</span>
            </div>
            <div class="hero-map" id="hero-map">
                <div class="hero-map-stage" id="hero-map-stage" tabindex="0" aria-label="Hero map, use plus or minus to zoom and C to center">
                    <div class="hero-map-tiles" id="hero-map-tiles"></div>
                    <img class="hero-map-fallback" id="hero-map-fallback" src="map-ets2-preview.jpg" alt="Static ETS2 world map">
                    <div class="hero-map-marker" id="hero-map-marker">
                        <span class="hero-map-marker-core"></span>
                    </div>
                </div>
                <div class="hero-map-toolbar">
                    <button class="hero-map-button hero-map-center-button" type="button" id="hero-map-center" aria-label="Center hero map on truck">Center</button>
                    <button class="hero-map-button" type="button" data-hero-map-zoom="out" aria-label="Zoom hero map out">-</button>
                    <button class="hero-map-button" type="button" data-hero-map-zoom="in" aria-label="Zoom hero map in">+</button>
                </div>
            </div>
        </section>

        <section class="metric-ribbon">
            <article class="metric-card">
                <span class="metric-label">Fuel</span>
                <strong class="metric-value" id="metric-fuel">--</strong>
                <span class="metric-note" id="metric-fuel-note">Waiting for telemetry</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Range</span>
                <strong class="metric-value" id="metric-range">--</strong>
                <span class="metric-note" id="metric-range-note">Estimated road range</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Engine</span>
                <strong class="metric-value" id="metric-rpm">--</strong>
                <span class="metric-note" id="metric-rpm-note">RPM and gear</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Odometer</span>
                <strong class="metric-value" id="metric-odometer">--</strong>
                <span class="metric-note" id="metric-odometer-note">Truck lifetime</span>
            </article>
        </section>

        <section class="workspace-shell">
            <div class="section-tabs" role="tablist" aria-label="Dashboard sections">
                <button class="section-tab is-active" type="button" role="tab" id="tab-overview" aria-controls="panel-overview" data-tab="overview" aria-pressed="true">Overview</button>
                <button class="section-tab" type="button" role="tab" id="tab-systems" aria-controls="panel-systems" data-tab="systems" aria-pressed="false">Systems</button>
                <button class="section-tab" type="button" role="tab" id="tab-world" aria-controls="panel-world" data-tab="world" aria-pressed="false">World</button>
                <button class="section-tab" type="button" role="tab" id="tab-debug" aria-controls="panel-debug" data-tab="debug" aria-pressed="false">Debug</button>
            </div>

            <div class="tab-pages">
                <section class="dashboard-grid tab-page is-active" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" data-tab-panel="overview">
                    <article class="panel route-panel span-8">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Delivery</p>
                                <h2>Route Snapshot</h2>
                            </div>
                            <span class="panel-badge" id="route-badge">Idle</span>
                        </div>
                        <div class="route-journey">
                            <div>
                                <span class="route-label">From</span>
                                <strong id="route-source">No active pickup</strong>
                            </div>
                            <div class="route-line"></div>
                            <div>
                                <span class="route-label">To</span>
                                <strong id="route-destination">No destination</strong>
                            </div>
                        </div>
                        <div class="stats-grid" id="route-stats"></div>
                    </article>

                    <div class="overview-side span-4">
                        <article class="panel truck-panel">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">Vehicle</p>
                                    <h2>Truck Profile</h2>
                                </div>
                            </div>
                            <div class="stats-grid compact-grid" id="truck-stats"></div>
                        </article>

                        <article class="panel alerts-panel">
                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">Attention</p>
                                    <h2>Alerts & Status</h2>
                                </div>
                            </div>
                            <div class="alert-feed" id="alert-feed"></div>
                        </article>
                    </div>

                </section>

                <section class="dashboard-grid tab-page" id="panel-systems" role="tabpanel" aria-labelledby="tab-systems" data-tab-panel="systems">
                    <article class="panel systems-panel span-4">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Truck Health</p>
                                <h2>Systems</h2>
                            </div>
                        </div>
                        <div class="pill-grid" id="systems-pills"></div>
                        <div class="gauge-list" id="systems-gauges"></div>
                    </article>

                    <article class="panel drivetrain-panel span-4">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Powertrain</p>
                                <h2>Drive & Brake</h2>
                            </div>
                        </div>
                        <div class="stats-grid compact-grid" id="drivetrain-stats"></div>
                    </article>

                    <article class="panel trailer-panel span-4">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Trailer</p>
                                <h2>Attached Unit</h2>
                            </div>
                            <span class="panel-badge" id="trailer-badge">Detached</span>
                        </div>
                        <div class="trailer-summary" id="trailer-summary"></div>
                        <div class="gauge-list" id="trailer-gauges"></div>
                    </article>

                    <article class="panel controls-panel span-4">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Driver Input</p>
                                <h2>Controls</h2>
                            </div>
                        </div>
                        <div class="controls-list" id="controls-list"></div>
                    </article>

                    <article class="panel lights-panel span-8">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Signals</p>
                                <h2>Lighting & Indicators</h2>
                            </div>
                        </div>
                        <div class="light-grid" id="light-grid"></div>
                    </article>
                </section>

                <section class="dashboard-grid tab-page" id="panel-world" role="tabpanel" aria-labelledby="tab-world" data-tab-panel="world">
                    <article class="panel map-panel span-7">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">World Map</p>
                                <h2>ETS2 Map</h2>
                            </div>
                            <span class="panel-badge" id="map-badge">Tracking</span>
                        </div>
                        <div class="ets2-map" id="ets2-map">
                            <div class="ets2-map-stage" id="ets2-map-stage" tabindex="0" aria-label="World map, use plus or minus to zoom and C to center">
                                <div class="ets2-map-tiles" id="ets2-map-tiles"></div>
                                <img class="ets2-map-image ets2-map-fallback" id="ets2-map-fallback" src="map-ets2-preview.jpg" alt="Static ETS2 world map">
                            </div>
                            <div class="ets2-map-marker" id="ets2-map-marker">
                                <span class="ets2-map-marker-core"></span>
                            </div>
                            <div class="ets2-map-toolbar">
                                <span class="ets2-map-mode" id="ets2-map-mode">Static preview</span>
                                <div class="ets2-map-zoom-controls">
                                    <button class="ets2-map-zoom-button ets2-map-center-button" type="button" id="ets2-map-center" aria-label="Center on truck">Center</button>
                                    <button class="ets2-map-zoom-button" type="button" data-map-zoom="out" aria-label="Zoom out">-</button>
                                    <button class="ets2-map-zoom-button" type="button" data-map-zoom="in" aria-label="Zoom in">+</button>
                                </div>
                            </div>
                            <div class="ets2-map-label" id="ets2-map-label" aria-live="polite">Truck position</div>
                        </div>
                        <div class="map-meta" id="map-meta"></div>
                    </article>

                    <article class="panel world-panel span-5">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">World State</p>
                                <h2>Position & Navigation</h2>
                            </div>
                        </div>
                        <div class="stats-grid" id="world-stats"></div>
                    </article>

                    <article class="panel events-panel span-12">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Gameplay</p>
                                <h2>Recent Events</h2>
                            </div>
                        </div>
                        <div class="stats-grid compact-grid" id="event-stats"></div>
                    </article>
                </section>

                <section class="dashboard-grid tab-page" id="panel-debug" role="tabpanel" aria-labelledby="tab-debug" data-tab-panel="debug">
                    <article class="panel raw-panel span-12">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Debug View</p>
                                <h2>Raw Telemetry Payload</h2>
                            </div>
                            <span class="panel-badge">JSON</span>
                        </div>
                        <pre id="telemetry-output"><?php echo htmlspecialchars((string) json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
                    </article>
                </section>
            </div>
        </section>
    </main>

    <script>
        window.dashboardConfig = <?php echo json_encode($dashboard_config, $json_flags); ?>;
    </script>
    <script src="index.js" defer></script>
</body>

</html>