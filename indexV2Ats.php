<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pageTitle = (string) dashboard_config_value('app.pageTitle', 'ETS2 Command Dashboard');
$documentTitle = 'American Truck Simulator Dashboard';
$themeVariables = dashboard_design_theme_variables([
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
]);

$themeDeclarations = [];
foreach ($themeVariables as $name => $value) {
    $themeDeclarations[] = $name . ':' . $value;
}
$themeCss = ':root{' . implode(';', $themeDeclarations) . ';}';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="American Truck Simulator dashboard preview and project status page.">
    <title><?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        <?php echo $themeCss; ?>

        :root {
            color-scheme: dark;
            --ats-sand: #f3c17c;
            --ats-sunset: #ff8f5f;
            --ats-sky: #6fc6ff;
            --ats-deep: #071019;
            --ats-surface: rgba(10, 19, 30, 0.82);
            --ats-stroke: rgba(255, 214, 153, 0.16);
            --ats-text: #eff8ff;
            --ats-muted: #a7bdcf;
            --ats-shadow: 0 28px 80px rgba(0, 0, 0, 0.38);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background:
                radial-gradient(circle at top left, rgba(255, 191, 105, 0.18), transparent 26%),
                radial-gradient(circle at top right, rgba(111, 198, 255, 0.16), transparent 24%),
                linear-gradient(165deg, #05080d 0%, #0a1320 46%, #080f19 100%);
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--ats-text);
            font-family: var(--ui-font-family);
            font-size: calc(16px * var(--ui-font-scale));
            line-height: 1.45;
            background:
                radial-gradient(circle at 12% 18%, rgba(255, 191, 105, 0.12), transparent 0 20rem),
                radial-gradient(circle at 88% 10%, rgba(111, 198, 255, 0.08), transparent 0 22rem),
                radial-gradient(circle at 50% 100%, rgba(255, 143, 95, 0.08), transparent 0 28rem);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.2;
            background:
                linear-gradient(rgba(255, 255, 255, 0.022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.022) 1px, transparent 1px);
            background-size: 32px 32px;
            mask-image: radial-gradient(circle at center, black 42%, transparent 88%);
        }

        a {
            color: inherit;
        }

        .page-shell {
            width: min(1180px, calc(100% - 28px));
            margin: 18px auto;
            padding: 18px 0 28px;
        }

        .top-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .brand-badge,
        .top-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 16px;
            border-radius: 18px;
            border: 1px solid var(--ats-stroke);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02)),
                rgba(8, 16, 26, 0.64);
            backdrop-filter: blur(var(--glass-blur-strength)) saturate(140%);
            box-shadow: var(--ats-shadow);
            text-decoration: none;
        }

        .brand-badge strong {
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 0.84rem;
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border-radius: 32px;
            border: 1px solid rgba(255, 214, 153, 0.2);
            background:
                linear-gradient(160deg, rgba(9, 18, 30, 0.94), rgba(10, 22, 37, 0.86)),
                radial-gradient(circle at top left, rgba(255, 191, 105, 0.18), transparent 38%),
                radial-gradient(circle at bottom right, rgba(111, 198, 255, 0.12), transparent 34%);
            box-shadow: var(--ats-shadow);
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -120px;
            bottom: -110px;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background:
                radial-gradient(circle, rgba(255, 191, 105, 0.18) 0%, rgba(255, 143, 95, 0.1) 42%, transparent 72%);
            filter: blur(14px);
            pointer-events: none;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.8fr);
            gap: 20px;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: var(--ats-sand);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            max-width: 12ch;
            font-size: clamp(2.6rem, 6vw, 5.4rem);
            line-height: 0.94;
            letter-spacing: -0.04em;
        }

        .hero-copy {
            margin: 16px 0 0;
            max-width: 60ch;
            color: var(--ats-muted);
            font-size: 1rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 16px;
            border: 1px solid rgba(255, 214, 153, 0.18);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02)),
                rgba(9, 18, 30, 0.48);
            color: var(--ats-text);
            font-weight: 700;
            text-decoration: none;
        }

        .action-link.primary {
            border-color: rgba(255, 191, 105, 0.28);
            background:
                linear-gradient(180deg, rgba(255, 191, 105, 0.22), rgba(255, 143, 95, 0.14)),
                rgba(9, 18, 30, 0.48);
        }

        .status-card {
            display: grid;
            gap: 14px;
            align-content: start;
            padding: 18px;
            border-radius: 24px;
            border: 1px solid rgba(255, 214, 153, 0.16);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.018)),
                rgba(7, 14, 23, 0.56);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            min-height: 30px;
            padding: 4px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 191, 105, 0.24);
            background: rgba(255, 191, 105, 0.12);
            color: #ffe6ba;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .status-list {
            display: grid;
            gap: 10px;
        }

        .status-item {
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid rgba(184, 226, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
        }

        .status-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 0.92rem;
        }

        .status-item span {
            color: var(--ats-muted);
            font-size: 0.82rem;
        }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 18px;
        }

        .panel {
            padding: 20px;
            border-radius: 24px;
            border: 1px solid rgba(184, 226, 255, 0.12);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.02)),
                rgba(9, 18, 30, 0.48);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .panel h2 {
            margin: 0 0 8px;
            font-size: 1.1rem;
        }

        .panel p,
        .panel li {
            color: var(--ats-muted);
        }

        .panel ul {
            margin: 0;
            padding-left: 18px;
        }

        .roadmap {
            margin-top: 18px;
            padding: 22px;
            border-radius: 28px;
            border: 1px solid rgba(255, 214, 153, 0.16);
            background:
                linear-gradient(160deg, rgba(9, 18, 30, 0.9), rgba(10, 22, 37, 0.82)),
                radial-gradient(circle at right, rgba(255, 143, 95, 0.12), transparent 34%);
        }

        .roadmap h2 {
            margin: 0 0 12px;
            font-size: 1.24rem;
        }

        .roadmap-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .roadmap-item {
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(184, 226, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
        }

        .roadmap-item strong {
            display: block;
            margin-bottom: 6px;
        }

        @media (max-width: 980px) {
            .hero-grid,
            .section-grid,
            .roadmap-grid {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 22px;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                width: min(100%, calc(100% - 16px));
                margin: 8px auto;
                padding: 8px 0 18px;
            }

            .hero,
            .panel,
            .roadmap {
                border-radius: 22px;
            }

            h1 {
                font-size: clamp(2.2rem, 12vw, 3.6rem);
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <div class="top-row">
            <div class="brand-badge">
                <strong><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <a class="top-link" href="indexV2.php">Back to ETS2 Dashboard</a>
        </div>

        <section class="hero">
            <div class="hero-grid">
                <div>
                    <p class="eyebrow">American Truck Simulator</p>
                    <h1>ATS dashboard is on the road map</h1>
                    <p class="hero-copy">
                        This project already has the newer ETS2 dashboard flow, map system, settings workspace, and telemetry pipeline.
                        The ATS page is now a proper project landing page instead of a blank placeholder, while the live ATS dashboard build is still in progress.
                    </p>
                    <div class="hero-actions">
                        <a class="action-link primary" href="indexV2.php">Open Current Dashboard</a>
                        <a class="action-link" href="settings.php">Open Settings</a>
                        <a class="action-link" href="infos.php">Open Info Workspace</a>
                    </div>
                </div>

                <aside class="status-card" aria-label="ATS status">
                    <span class="status-pill">Work in Progress</span>
                    <div class="status-list">
                        <div class="status-item">
                            <strong>Foundation ready</strong>
                            <span>Telemetry polling, map sources, storage, and popup systems already exist in the shared codebase.</span>
                        </div>
                        <div class="status-item">
                            <strong>Next step</strong>
                            <span>Hook ATS telemetry into the same frontend patterns and adapt the route, map, and event views where game data differs.</span>
                        </div>
                        <div class="status-item">
                            <strong>Current use</strong>
                            <span>This page gives ATS a clean home while development continues, instead of dropping you onto bare HTML.</span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="section-grid" aria-label="ATS development summary">
            <article class="panel">
                <h2>What is planned</h2>
                <p>The ATS version is intended to follow the same V2 dashboard direction: a clean driving HUD, live maps, detailed information panels, and browser-based settings.</p>
            </article>
            <article class="panel">
                <h2>What already exists</h2>
                <ul>
                    <li>Live telemetry bridge in `telemetry.php`</li>
                    <li>Modern dashboard flow in `indexV2.php`</li>
                    <li>Secondary detail workspace in `infos.php`</li>
                    <li>Managed configuration in `settings.php`</li>
                </ul>
            </article>
            <article class="panel">
                <h2>What will likely change</h2>
                <p>ATS-specific city data, map coverage, branding, and some gameplay labels will need tuning, even though much of the core rendering can be shared.</p>
            </article>
        </section>

        <section class="roadmap" aria-label="ATS roadmap">
            <h2>Planned ATS milestones</h2>
            <div class="roadmap-grid">
                <div class="roadmap-item">
                    <strong>1. Shared telemetry hookup</strong>
                    <span>Feed ATS payloads into the existing live frontend pipeline.</span>
                </div>
                <div class="roadmap-item">
                    <strong>2. ATS map support</strong>
                    <span>Align tile config, bounds, and route localization for ATS-compatible maps.</span>
                </div>
                <div class="roadmap-item">
                    <strong>3. ATS polish pass</strong>
                    <span>Adjust copy, panels, and event handling so the page feels native instead of copied over.</span>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
