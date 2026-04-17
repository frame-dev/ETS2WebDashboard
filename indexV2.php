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
$job_finished = (bool) get_job_finished($json_data)
    || (bool) get_job_delivered($json_data)
    || !empty(get_job_delivered_details($json_data));
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
    'telemetryPolling' => is_array($frontend_config['telemetryPolling'] ?? null) ? $frontend_config['telemetryPolling'] : [],
    'speedRing' => is_array($frontend_config['speedRing'] ?? null) ? $frontend_config['speedRing'] : [],
    'storageKeys' => is_array($frontend_config['storageKeys'] ?? null) ? $frontend_config['storageKeys'] : [],
    'routePlanner' => is_array($frontend_config['routePlanner'] ?? null) ? $frontend_config['routePlanner'] : [],
    'mapDefaults' => is_array($frontend_config['mapDefaults'] ?? null) ? $frontend_config['mapDefaults'] : [],
    'mapBounds' => is_array($frontend_config['mapBounds'] ?? null) ? $frontend_config['mapBounds'] : [],
    'mapTiles' => is_array($frontend_config['mapTiles'] ?? null) ? $frontend_config['mapTiles'] : [],
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
    <style><?php echo $dashboard_theme_css; ?></style>
</head>

<body>
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
            <a href="settings.php" class="settings-link" aria-label="Dashboard settings and configuration">
                <span class="settings-link-icon" aria-hidden="true">O</span>
                <span class="settings-link-copy">
                    <span class="settings-link-label">Settings</span>
                    <span class="settings-link-meta">Theme, telemetry, snapshots</span>
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
                    <span class="speed-readout-label">Tempomat</span>
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
                    <div class="hero-map-marker" id="hero-map-marker">
                        <span class="hero-map-marker-arrow" aria-hidden="true"></span>
                        <span class="hero-map-marker-core"></span>
                    </div>
                </div>
                <div class="hero-map-toolbar">
                    <span class="hero-map-shortcuts" id="hero-map-shortcuts">Shortcuts: +/- zoom, C center</span>
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
                    class="job-finished-popup<?php echo $job_finished ? ' is-visible' : ''; ?>"
                    id="job-finished-popup"
                    aria-live="polite"
                    aria-hidden="<?php echo $job_finished ? 'false' : 'true'; ?>">
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
            </div>
        </section>
    </main>
    <script>
        window.dashboardConfig = <?php echo json_encode($dashboard_config, $json_flags); ?>;
    </script>
    <script src="index.js" defer></script>
</body>

</html>
