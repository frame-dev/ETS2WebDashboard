<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telemetry.php';

$app_config = dashboard_config();
$app_title = (string) dashboard_config_value('app.pageTitle', 'ETS2 Command Dashboard');
$app_description = (string) dashboard_config_value('app.metaDescription', 'Live ETS2 dashboard for telemetry, route status, systems, and map tracking.');
$hero_eyebrow = (string) dashboard_config_value('app.heroEyebrow', 'Euro Truck Simulator 2');
$hero_title = (string) dashboard_config_value('app.heroTitle', 'Command dashboard online');
$hero_summary = (string) dashboard_config_value('app.heroSummary', 'Preparing a live operator view from your local telemetry feed.');
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
$frontend_config = is_array($app_config['frontend'] ?? null) ? $app_config['frontend'] : [];
$job_finished_details = get_job_delivered_details($json_data);
$job_finished_details = is_array($job_finished_details) ? $job_finished_details : [];
$job_finished_cargo = trim((string) get_job_cargo($json_data));
$job_finished_source = trim((string) get_job_source_city($json_data));
$job_finished_destination = trim((string) get_job_destination_city($json_data));
$job_finished_route = implode(' -> ', array_filter([$job_finished_source, $job_finished_destination], static fn($value): bool => $value !== ''));
$job_finished_revenue = isset($job_finished_details['revenue']) && is_numeric($job_finished_details['revenue'])
    ? 'Income ' . number_format((float) $job_finished_details['revenue'], 0, '.', "'")
    : 'Income --';
$job_finished_xp = isset($job_finished_details['earnedXp']) && is_numeric($job_finished_details['earnedXp'])
    ? 'XP ' . number_format((float) $job_finished_details['earnedXp'], 0, '.', "'")
    : 'XP --';
$job_finished_distance = isset($job_finished_details['distanceKm']) && is_numeric($job_finished_details['distanceKm'])
    ? number_format((float) $job_finished_details['distanceKm'], ((float) $job_finished_details['distanceKm'] < 10 ? 1 : 0), '.', "'") . ' km'
    : '-- km';
$job_finished_parking = !empty($job_finished_details['autoParked']) ? 'Auto parked' : 'Manual parking';
$job_finished_title = $job_finished_cargo !== '' ? $job_finished_cargo : 'Delivery completed';
$job_finished_meta = $job_finished_route !== '' ? $job_finished_route : 'Route unavailable';
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
    'popupEvents' => is_array($frontend_config['popupEvents'] ?? null) ? $frontend_config['popupEvents'] : [],
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
    <title><?php echo htmlspecialchars($app_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="indexV2.css">
    <style>
        <?php echo $dashboard_theme_css; ?>
    </style>
</head>

<body>
    <?php
    ?>
    <noscript>
        <p>This dashboard requires JavaScript to render live telemetry and map updates.</p>
    </noscript>
    <main class="dashboard-shell">
        <div class="top-toolbar">
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
            <button class="toolbar-toggle-button" type="button" id="truckersmp-toggle" aria-pressed="true" aria-label="Toggle TruckersMP player markers">
                TruckersMP
            </button>
            <button class="help-link" type="button" id="help-toggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="dashboard-help">
                <span class="help-link-icon" aria-hidden="true">?</span>
                <span class="help-link-copy">
                    <span class="help-link-label">Help</span>
                    <span class="help-link-meta">Controls & guide</span>
                </span>
            </button>
            <a href="settings.php" class="settings-link" aria-label="Dashboard settings and configuration">
                <span class="settings-link-icon" aria-hidden="true">O</span>
                <span class="settings-link-copy">
                    <span class="settings-link-label">Settings</span>
                </span>
            </a>
        </div>
        <section class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow"><?php echo htmlspecialchars($hero_eyebrow, ENT_QUOTES, 'UTF-8'); ?></p>
                <h1 id="hero-title"><?php echo htmlspecialchars($hero_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="hero-summary" id="hero-summary"><?php echo htmlspecialchars($hero_summary, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="dashboard-notices" id="dashboard-notices" hidden aria-live="polite"></div>
            </div>

            <div class="hero-speed">
                <span class="road-speed" id="road-speed-value">
                    <span class="speed-readout-label">Road limit</span>
                    <span class="speed-readout-reading">
                        <span class="speed-readout-value" id="road-speed-limit">--</span>
                        <span class="speed-readout-unit">km/h</span>
                    </span>
                </span>
                <div class="speed-ring" id="speed-ring">
                    <span class="speed-limit-marker" id="speed-limit-marker" aria-hidden="true"></span>
                    <div class="speed-ring-inner">
                        <span class="speed-unit">km/h</span>
                        <span class="speed-value" id="hero-speed-value">0</span>
                    </div>
                </div>
                <span class="cruise-control-speed" id="cruise-control-speed">
                    <span class="speed-readout-label">Cruise Control</span>
                    <span class="speed-readout-reading">
                        <span class="speed-readout-value" id="tempomat-speed-limit">--</span>
                        <span class="speed-readout-unit">km/h</span>
                    </span>
                </span>
                <div class="speed-ring-stats" id="speed-ring-stats">
                    <span class="speed-peak" id="speed-peak">Peak --</span>
                    <span class="speed-trend" id="speed-trend">Trend --</span>
                    <span class="speed-alert" id="speed-alert"></span>
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
                    <div class="hero-map-players" id="hero-map-players"></div>
                    <div class="hero-map-marker" id="hero-map-marker">
                        <span class="hero-map-marker-arrow" aria-hidden="true"></span>
                        <span class="hero-map-marker-core"></span>
                    </div>
                </div>
                <div class="hero-map-toolbar">
                    <span class="hero-map-shortcuts" id="hero-map-shortcuts">Shortcuts: +/- zoom, C center</span>
                    <select class="hero-map-select" id="hero-map-source" data-map-source-select aria-label="Select map source"></select>
                    <button class="hero-map-button hero-map-center-button" type="button" id="hero-map-center" aria-label="Center hero map on truck">Center</button>
                    <button class="hero-map-button" type="button" data-hero-map-zoom="out" aria-label="Zoom hero map out">-</button>
                    <button class="hero-map-button" type="button" data-hero-map-zoom="in" aria-label="Zoom hero map in">+</button>
                </div>
                <div class="hero-map-job-overlay" id="hero-map-job-overlay" aria-live="polite">
                    <span class="hero-map-job-line" id="hero-map-job-income">Income --</span>
                    <span class="hero-map-job-line" id="hero-map-job-cargo">Job --</span>
                    <span class="hero-map-job-line" id="hero-map-job-weight">Weight --</span>
                </div>
                <a class="more-info" href="infos.php" aria-label="Open info panel">Info</a>
                <div
                    class="job-finished-popup"
                    id="job-finished-popup"
                    aria-live="polite"
                    aria-hidden="true">
                    <span class="job-finished-popup-badge" id="job-finished-popup-badge">Delivery complete</span>
                    <strong class="job-finished-popup-title" id="job-finished-popup-title"><?php echo htmlspecialchars($job_finished_title, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="job-finished-popup-meta" id="job-finished-popup-meta"><?php echo htmlspecialchars($job_finished_meta, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="job-finished-popup-stats">
                        <span class="job-finished-popup-stat" id="job-finished-popup-revenue"><?php echo htmlspecialchars($job_finished_revenue, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="job-finished-popup-stat" id="job-finished-popup-xp"><?php echo htmlspecialchars($job_finished_xp, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="job-finished-popup-stat" id="job-finished-popup-distance"><?php echo htmlspecialchars($job_finished_distance, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="job-finished-popup-stat" id="job-finished-popup-parking"><?php echo htmlspecialchars($job_finished_parking, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="job-started-popup" id="job-started-popup" aria-live="polite" aria-hidden="true">
                    <span class="job-started-popup-badge" id="job-started-popup-badge">New delivery</span>
                    <strong class="job-started-popup-title" id="job-started-popup-title">Delivery ready</strong>
                    <span class="job-started-popup-meta" id="job-started-popup-meta">Pickup unavailable -> Destination unavailable</span>
                    <div class="job-started-popup-stats">
                        <span class="job-started-popup-stat" id="job-started-popup-income">Income --</span>
                        <span class="job-started-popup-stat" id="job-started-popup-distance">Distance --</span>
                        <span class="job-started-popup-stat" id="job-started-popup-weight">Weight --</span>
                        <span class="job-started-popup-stat" id="job-started-popup-deadline">Deadline --</span>
                    </div>
                </div>
            </div>
        </section>
        <div class="help-overlay" id="help-overlay" hidden>
            <div class="help-backdrop" data-help-close></div>
            <section class="help-dialog" id="dashboard-help" role="dialog" aria-modal="true" aria-labelledby="help-title" aria-describedby="help-intro" tabindex="-1">
                <div class="help-dialog-header">
                    <div>
                        <p class="help-eyebrow">Dashboard Help</p>
                        <h2 id="help-title">Quick guide for the live ETS2 dashboard</h2>
                        <p class="help-intro" id="help-intro">Everything important is here: what the main controls do, how the maps behave, where to go for deeper details, and what to check when something looks wrong.</p>
                    </div>
                    <button class="help-close-button" type="button" id="help-close" aria-label="Close help">Close</button>
                </div>
                <div class="help-grid">
                    <article class="help-card">
                        <h3>Start Here</h3>
                        <ul class="help-list">
                            <li><strong>Connection</strong> shows whether telemetry is reaching the dashboard right now.</li>
                            <li><strong>Last Update</strong> tells you when the last telemetry payload arrived.</li>
                            <li><strong>Refresh</strong> shows the polling interval used for live updates.</li>
                            <li>The large center panel is your main driving view: speed, route, truck state, map, and notices.</li>
                        </ul>
                    </article>
                    <article class="help-card">
                        <h3>Main Controls</h3>
                        <ul class="help-list">
                            <li><strong>TruckersMP</strong> turns nearby player markers on or off.</li>
                            <li><strong>Help</strong> opens this guide at any time.</li>
                            <li><strong>Settings</strong> lets you change telemetry, map sources, design, and other saved dashboard options.</li>
                            <li><strong>Info</strong> opens the detailed page with overview, systems, world, and debug tabs.</li>
                        </ul>
                    </article>
                    <article class="help-card">
                        <h3>Hero Map</h3>
                        <ul class="help-list">
                            <li>Use <strong>+</strong> and <strong>-</strong> to zoom the map.</li>
                            <li>Press <strong>C</strong> or use <strong>Center</strong> to snap the map back to the truck.</li>
                            <li>Drag the map to enter free pan mode. Centering restores follow-truck mode.</li>
                            <li>The map selector lets you switch between <strong>Standard</strong> and <strong>ProMods</strong>. Bounds and tiles update automatically with the selected map.</li>
                        </ul>
                    </article>
                    <article class="help-card">
                        <h3>Driving Readouts</h3>
                        <ul class="help-list">
                            <li>The speed ring shows current speed, road speed limit, cruise control, peak, and trend.</li>
                            <li>The route panel shows source, destination, planned distance, ETA, and estimated real time.</li>
                            <li>The job overlay on the map summarizes income, cargo, and weight.</li>
                            <li>When a new job starts, a centered popup summarizes cargo, route, income, distance, weight, and deadline.</li>
                            <li>When a delivery finishes, the popup summarizes route, income, XP, distance, and parking result.</li>
                        </ul>
                    </article>
                    <article class="help-card">
                        <h3>Info Page</h3>
                        <ul class="help-list">
                            <li><strong>Overview</strong> gives route, truck profile, and alerts.</li>
                            <li><strong>Systems</strong> shows health, drivetrain, trailer, controls, and lighting.</li>
                            <li><strong>World</strong> includes the larger interactive map and world/navigation details.</li>
                            <li><strong>Debug</strong> shows the raw telemetry JSON for troubleshooting.</li>
                        </ul>
                    </article>
                    <article class="help-card">
                        <h3>If Something Breaks</h3>
                        <ul class="help-list">
                            <li>If telemetry fails, check that the game telemetry API is reachable and the configured endpoint is correct.</li>
                            <li>If map tiles do not load, the dashboard falls back to the static preview and retries automatically.</li>
                            <li>If the truck is missing from the map, wait for a fresh telemetry update and confirm the game is connected.</li>
                            <li>If the wrong map is shown, switch the map source selector or adjust map sources in settings.</li>
                        </ul>
                    </article>
                </div>
                <div class="help-dialog-footer">
                    <span class="help-tip">Shortcuts: <strong>?</strong> opens help, <strong>Esc</strong> closes it, <strong>+</strong>/<strong>-</strong> zoom the active map, <strong>C</strong> centers it.</span>
                </div>
            </section>
        </div>
    </main>
    <script>
        window.dashboardConfig = <?php echo json_encode($dashboard_config, $json_flags); ?>;
    </script>
    <script src="index.js" defer></script>
</body>

</html>
