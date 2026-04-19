<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telemetry.php';

$app_config = dashboard_config();
$app_title = (string) dashboard_config_value('app.pageTitle', 'ETS2 Command Dashboard');
$app_description = (string) dashboard_config_value('app.metaDescription', 'Live ETS2 dashboard for telemetry, route status, systems, and map tracking.');
$design_config = [
    'accentColor' => dashboard_sanitize_hex_color((string) dashboard_config_value('design.accentColor', '#54EFC7'), '#54EFC7'),
    'accentSecondaryColor' => dashboard_sanitize_hex_color((string) dashboard_config_value('design.accentSecondaryColor', '#79C7FF'), '#79C7FF'),
    'accentWarmColor' => dashboard_sanitize_hex_color((string) dashboard_config_value('design.accentWarmColor', '#FFBF69'), '#FFBF69'),
    'successColor' => dashboard_sanitize_hex_color((string) dashboard_config_value('design.successColor', '#43D79F'), '#43D79F'),
    'dangerColor' => dashboard_sanitize_hex_color((string) dashboard_config_value('design.dangerColor', '#FF7050'), '#FF7050'),
    'fontFamily' => dashboard_sanitize_font_family((string) dashboard_config_value('design.fontFamily', '"Space Grotesk", "Aptos", "Segoe UI", sans-serif'), '"Space Grotesk", "Aptos", "Segoe UI", sans-serif'),
    'fontScale' => (float) dashboard_config_value('design.fontScale', 1.0),
    'heroMapPlayerFontSizeRem' => (float) dashboard_config_value('design.heroMapPlayerFontSizeRem', 0.95),
    'panelRadiusPx' => (int) dashboard_config_value('design.panelRadiusPx', 28),
    'glassBlurPx' => (int) dashboard_config_value('design.glassBlurPx', 26),
];
$dashboard_theme_css_variables = dashboard_design_theme_variables($design_config);
$dashboard_theme_declarations = [];
foreach ($dashboard_theme_css_variables as $name => $value) {
    $dashboard_theme_declarations[] = $name . ':' . $value;
}
$dashboard_theme_css = ':root{' . implode(';', $dashboard_theme_declarations) . ';}';

$refresh_interval_ms = (int) get_telemetry_refresh_interval_ms();
$telemetry_source = null;
$json_data = fetch_telemetry_data(TELEMETRY_URL, $telemetry_source);
$frontend_config = is_array($app_config['frontend'] ?? null) ? $app_config['frontend'] : [];
$initial_payload = [
    'refreshIntervalMs' => $refresh_interval_ms,
    'fetchedAt' => gmdate('c'),
    'source' => $telemetry_source,
    'data' => $json_data,
];

$dashboard_config = [
    'telemetryEndpoint' => (string) dashboard_config_value('frontend.telemetryEndpoint', 'telemetry.php?format=json'),
    'tileProxyEndpoint' => 'tile-proxy.php',
    'refreshIntervalMs' => $refresh_interval_ms,
    'telemetryRequestTimeoutMs' => (int) dashboard_config_value('telemetry.requestTimeoutMs', 4500),
    'remoteTelemetryUrls' => is_array($frontend_config['remoteTelemetryUrls'] ?? null) ? $frontend_config['remoteTelemetryUrls'] : [],
    'playersRefreshMs' => (int) (($frontend_config['playersRefreshMs'] ?? null) ?? (($frontend_config['players']['refreshMs'] ?? null) ?? 250)),
    'playersRadiusDefault' => (int) (($frontend_config['playersRadiusDefault'] ?? null) ?? (($frontend_config['players']['radiusDefault'] ?? null) ?? 5500)),
    'playersServerDefault' => (int) (($frontend_config['playersServerDefault'] ?? null) ?? (($frontend_config['players']['serverDefault'] ?? null) ?? 50)),
    'telemetryPolling' => is_array($frontend_config['telemetryPolling'] ?? null) ? $frontend_config['telemetryPolling'] : [],
    'speedRing' => is_array($frontend_config['speedRing'] ?? null) ? $frontend_config['speedRing'] : [],
    'storageKeys' => is_array($frontend_config['storageKeys'] ?? null) ? $frontend_config['storageKeys'] : [],
    'routePlanner' => is_array($frontend_config['routePlanner'] ?? null) ? $frontend_config['routePlanner'] : [],
    'mapDefaults' => is_array($frontend_config['mapDefaults'] ?? null) ? $frontend_config['mapDefaults'] : [],
    'mapBounds' => is_array($frontend_config['mapBounds'] ?? null) ? $frontend_config['mapBounds'] : [],
    'mapTiles' => is_array($frontend_config['mapTiles'] ?? null) ? $frontend_config['mapTiles'] : [],
    'mapSources' => is_array($frontend_config['mapSources'] ?? null) ? $frontend_config['mapSources'] : [],
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
    <meta name="description" content="<?php echo htmlspecialchars($app_description, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($app_title, ENT_QUOTES, 'UTF-8'); ?> - Info</title>
    <link rel="stylesheet" href="infos.css">
    <style><?php echo $dashboard_theme_css; ?></style>
</head>

<body>
    <a class="back" href="indexV2.php">Back to dashboard</a>
    <form class="konvoy-server-form" id="konvoy-server-form">
        <label for="konvoy-server-urls" class="visually-hidden">Other player telemetry URLs split by comma</label>
        <input type="text" id="konvoy-server-urls" class="konvoy-server-input" placeholder="Other telemetry URLs, comma separated (http://localhost:8080/telemetry.php?format=json, http://example.com:8000/api/ets2/telemetry)" aria-describedby="konvoy-server-url-description konvoy-server-url-status" autocomplete="off" spellcheck="false">
        <button type="button" class="konvoy-server-toggle" id="remote-telemetry-toggle" aria-pressed="true" aria-label="Disable direct telemetry fetching">Direct URLs On</button>
        <button type="submit" class="konvoy-server-save">Use URLs</button>
        <p id="konvoy-server-url-description" class="konvoy-server-description">Enter direct telemetry endpoints for other players. Separate multiple URLs with commas.</p>
        <p id="konvoy-server-url-status" class="konvoy-server-status" aria-live="polite"></p>
    </form>

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
                        <div class="alert-settings">
                            <p class="alert-settings-label">Visible alert groups</p>
                            <div class="alert-settings-grid" id="alert-settings">
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-systems" data-alert-preference="systems" checked>
                                    <span>Systems</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-overspeed" data-alert-preference="overspeed" checked>
                                    <span>Overspeed</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-fuel" data-alert-preference="fuel" checked>
                                    <span>Low Fuel</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-fatigue" data-alert-preference="fatigue" checked>
                                    <span>Fatigue</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-damage" data-alert-preference="damage" checked>
                                    <span>Damage</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-deadline" data-alert-preference="deadline" checked>
                                    <span>Deadline</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-status" data-alert-preference="status" checked>
                                    <span>Status</span>
                                </label>
                                <label class="alert-setting-toggle">
                                    <input type="checkbox" id="alert-setting-fines" data-alert-preference="fines" checked>
                                    <span>Fines</span>
                                </label>
                            </div>
                        </div>
                    </article>
                </div>

                <article class="panel job-history-panel span-12">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Recent Deliveries</p>
                            <h2>Job History</h2>
                        </div>
                        <span class="panel-badge" id="job-history-count">0 entries</span>
                    </div>
                    <div class="job-history-toolbar">
                        <label class="job-history-search" for="job-history-filter">
                            <span class="visually-hidden">Filter job history by cargo, route, or city</span>
                            <input type="search" id="job-history-filter" placeholder="Filter by cargo or city" autocomplete="off" spellcheck="false">
                        </label>
                        <div class="job-history-actions">
                            <button class="job-history-action" type="button" id="job-history-export">Export JSON</button>
                            <button class="job-history-action is-danger" type="button" id="job-history-clear">Clear history</button>
                        </div>
                    </div>
                    <div class="job-history-list" id="job-history-list" aria-live="polite"></div>
                </article>

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
                            <div class="ets2-map-players" id="ets2-map-players"></div>
                        </div>
                        <div class="ets2-map-marker" id="ets2-map-marker">
                            <span class="ets2-map-marker-core"></span>
                        </div>
                        <div class="ets2-map-toolbar">
                            <span class="ets2-map-mode" id="ets2-map-mode">Static preview</span>
                            <span class="ets2-map-shortcuts" id="ets2-map-shortcuts">Shortcuts: +/- zoom, C center</span>
                            <div class="ets2-map-zoom-controls">
                                <select class="ets2-map-source-select" id="ets2-map-source" data-map-source-select aria-label="Select map source"></select>
                                <button class="ets2-map-zoom-button ets2-map-center-button" type="button" id="ets2-map-center" aria-label="Center on truck">Center</button>
                                <button class="ets2-map-zoom-button" type="button" data-map-zoom="out" aria-label="Zoom out">-</button>
                                <button class="ets2-map-zoom-button" type="button" data-map-zoom="in" aria-label="Zoom in">+</button>
                            </div>
                        </div>
                        <div class="ets2-map-job-overlay" id="ets2-map-job-overlay" aria-live="polite">
                            <span class="ets2-map-job-line" id="map-job-income">Income --</span>
                            <span class="ets2-map-job-line" id="map-job-cargo">Job --</span>
                            <span class="ets2-map-job-line" id="map-job-weight">Weight --</span>
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
