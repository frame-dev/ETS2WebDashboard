<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function settings_format_bool(bool $value): string
{
    return $value ? 'Enabled' : 'Disabled';
}

function settings_format_ms(int $value): string
{
    return $value % 1000 === 0 ? ((string) ($value / 1000)) . 's' : $value . ' ms';
}

function settings_format_datetime_ms(int $timestampMs): string
{
    if ($timestampMs <= 0) {
        return 'Never';
    }

    $date = new DateTimeImmutable('@' . (string) floor($timestampMs / 1000));
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
}

function settings_load_local_config(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $loaded = require $path;
    return is_array($loaded) ? $loaded : [];
}

function settings_export_local_config(array $config): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
}

function settings_read_json_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function settings_checkbox_post(string $name): bool
{
    return ($_POST[$name] ?? '') === '1';
}

$appTitle = (string) dashboard_config_value('app.pageTitle', 'ETS2 Command Dashboard');
$configLocalPath = __DIR__ . '/config.local.php';
$currentConfig = [
    'enabled' => (bool) dashboard_config_value('snapshots.enabled', false),
    'intervalMs' => (int) dashboard_config_value('snapshots.intervalMs', 60000),
    'directory' => (string) dashboard_config_value('snapshots.directory', __DIR__ . '/snapshots'),
    'stateFile' => (string) dashboard_config_value('snapshots.stateFile', __DIR__ . '/tmp/snapshot-state.json'),
    'prettyPrint' => (bool) dashboard_config_value('snapshots.prettyPrint', true),
];
$telemetryCacheFile = (string) dashboard_config_value('telemetry.cacheFile', __DIR__ . '/tmp/telemetry-cache.json');
$formData = $currentConfig;
$flash = null;
$flashType = null;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formData = [
        'enabled' => settings_checkbox_post('snapshot_enabled'),
        'intervalMs' => max(1000, (int) ($_POST['snapshot_interval_ms'] ?? $currentConfig['intervalMs'])),
        'directory' => trim((string) ($_POST['snapshot_directory'] ?? $currentConfig['directory'])),
        'stateFile' => trim((string) ($_POST['snapshot_state_file'] ?? $currentConfig['stateFile'])),
        'prettyPrint' => settings_checkbox_post('snapshot_pretty_print'),
    ];

    if ($formData['directory'] === '') {
        $errors[] = 'Snapshot directory cannot be empty.';
    }

    if ($formData['stateFile'] === '') {
        $errors[] = 'Snapshot state file cannot be empty.';
    }

    if ($errors === []) {
        try {
            $localConfig = settings_load_local_config($configLocalPath);
            $localConfig['snapshots'] = $formData;
            $saved = @file_put_contents($configLocalPath, settings_export_local_config($localConfig), LOCK_EX);

            if ($saved === false) {
                $errors[] = 'Could not write config.local.php.';
            } else {
                header('Location: settings.php?saved=1');
                exit;
            }
        } catch (Throwable $exception) {
            $errors[] = 'Could not load config.local.php: ' . $exception->getMessage();
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $flash = 'Snapshot settings were saved to config.local.php.';
    $flashType = 'success';
}

if ($errors !== []) {
    $flash = implode(' ', $errors);
    $flashType = 'error';
}

$snapshotState = settings_read_json_file($formData['stateFile']);
$snapshotFiles = [];
if (is_dir($formData['directory'])) {
    $snapshotFiles = glob(rtrim($formData['directory'], '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];
    usort($snapshotFiles, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
}
$snapshotFileCount = count($snapshotFiles);
$recentSnapshotFiles = array_slice($snapshotFiles, 0, 8);
$lastSnapshotAtMs = (int) ($snapshotState['lastSnapshotAtMs'] ?? 0);
$lastSnapshotFile = (string) ($snapshotState['lastSnapshotFile'] ?? '');
$configPreview = settings_export_local_config(['snapshots' => $formData]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="settings.css">
</head>
<body>
    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">Project Settings</p>
                <h1>Snapshot Control Room</h1>
                <p class="copy">Tune how often telemetry snapshots are archived, where they are stored, and how the state file is tracked. Saving here writes the snapshot section directly into <code>config.local.php</code>.</p>
                <span class="hero-badge <?php echo $formData['enabled'] ? 'live' : ''; ?>">
                    <?php echo htmlspecialchars(settings_format_bool($formData['enabled']), ENT_QUOTES, 'UTF-8'); ?> for live snapshot capture
                </span>
            </div>
            <div class="hero-side">
                <a class="back" href="index.php">Back to dashboard</a>
                <div class="stats">
                    <article class="stat"><small>Interval</small><strong><?php echo htmlspecialchars(settings_format_ms((int) $formData['intervalMs']), ENT_QUOTES, 'UTF-8'); ?></strong><span>Minimum time between saved snapshots.</span></article>
                    <article class="stat"><small>Snapshots</small><strong><?php echo htmlspecialchars((string) $snapshotFileCount, ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo $lastSnapshotFile !== '' ? htmlspecialchars($lastSnapshotFile, ENT_QUOTES, 'UTF-8') : 'No snapshots written yet'; ?></span></article>
                    <article class="stat"><small>Last Write</small><strong><?php echo htmlspecialchars(settings_format_datetime_ms($lastSnapshotAtMs), ENT_QUOTES, 'UTF-8'); ?></strong><span>Read from the snapshot state file.</span></article>
                    <article class="stat"><small>Cache Source</small><strong><?php echo htmlspecialchars(basename($telemetryCacheFile), ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars($telemetryCacheFile, ENT_QUOTES, 'UTF-8'); ?></span></article>
                </div>
            </div>
        </section>

        <div class="layout">
            <section class="panel">
                <div class="head">
                    <div><p class="eyebrow">Editor</p><h2>Snapshot Settings</h2></div>
                </div>
                <p class="sub">Save these fields to <code><?php echo htmlspecialchars($configLocalPath, ENT_QUOTES, 'UTF-8'); ?></code>. Environment variables still override file-based values if they are present.</p>
                <?php if ($flash !== null): ?><div class="flash <?php echo $flashType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <form class="form" method="post" action="settings.php">
                    <div class="form-grid">
                        <div class="toggle-card">
                            <div class="toggle-copy"><span class="toggle-title">Enable snapshots</span><span>When enabled, the telemetry pipeline writes timestamped snapshot files into the configured directory.</span></div>
                            <label class="switch" aria-label="Enable snapshots"><input type="checkbox" name="snapshot_enabled" value="1" <?php echo $formData['enabled'] ? 'checked' : ''; ?>><span class="track"></span></label>
                        </div>
                        <div class="toggle-card">
                            <div class="toggle-copy"><span class="toggle-title">Pretty print JSON</span><span>Formatted files are easier to inspect; compact files are smaller.</span></div>
                            <label class="switch" aria-label="Pretty print snapshot JSON"><input type="checkbox" name="snapshot_pretty_print" value="1" <?php echo $formData['prettyPrint'] ? 'checked' : ''; ?>><span class="track"></span></label>
                        </div>
                        <div class="field">
                            <label for="snapshot-interval-ms">Snapshot interval in milliseconds</label>
                            <input id="snapshot-interval-ms" name="snapshot_interval_ms" type="number" min="1000" step="1000" value="<?php echo htmlspecialchars((string) $formData['intervalMs'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="hint">The pipeline will wait at least this long between saved snapshots.</span>
                        </div>
                        <div class="field">
                            <label for="snapshot-state-file">State file path</label>
                            <input id="snapshot-state-file" name="snapshot_state_file" type="text" value="<?php echo htmlspecialchars($formData['stateFile'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="hint">Tracks the last saved snapshot so duplicates can be avoided.</span>
                        </div>
                        <div class="field full">
                            <label for="snapshot-directory">Snapshot directory</label>
                            <input id="snapshot-directory" name="snapshot_directory" type="text" value="<?php echo htmlspecialchars($formData['directory'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="hint">Files like <code>telemetry-2026-04-15T21-13-45-432Z.json</code> will be written here.</span>
                        </div>
                    </div>
                    <div class="actions">
                        <p>Saving here updates only the <code>snapshots</code> section in <code>config.local.php</code>. Other local config keys are preserved.</p>
                        <button class="save" type="submit">Save Snapshot Settings</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="head">
                    <div><p class="eyebrow">Runtime</p><h2>Live Status</h2></div>
                    <span class="badge <?php echo $formData['enabled'] ? 'live' : ''; ?>"><?php echo htmlspecialchars(settings_format_bool($formData['enabled']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="runtime">
                    <div class="row"><strong>Interval</strong><span><?php echo htmlspecialchars(settings_format_ms((int) $formData['intervalMs']), ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="row"><strong>Snapshot dir</strong><span><?php echo htmlspecialchars($formData['directory'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="row"><strong>State file</strong><span><?php echo htmlspecialchars($formData['stateFile'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="row"><strong>Telemetry cache</strong><span><?php echo htmlspecialchars($telemetryCacheFile, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="row"><strong>JSON output</strong><span><?php echo htmlspecialchars($formData['prettyPrint'] ? 'Pretty printed' : 'Compact', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="row"><strong>Last snapshot</strong><span><?php echo htmlspecialchars(settings_format_datetime_ms($lastSnapshotAtMs), ENT_QUOTES, 'UTF-8'); ?></span></div>
                </div>
                <div class="note">If snapshot environment variables are set, they override what is saved here. This page controls the local file-based project settings.</div>
            </section>
        </div>

        <div class="layout">
            <section class="panel">
                <div class="head"><div><p class="eyebrow">Preview</p><h2>Generated Config Snippet</h2></div></div>
                <pre><?php echo htmlspecialchars($configPreview, ENT_QUOTES, 'UTF-8'); ?></pre>
            </section>
            <section class="panel">
                <div class="head"><div><p class="eyebrow">Recent Output</p><h2>Latest Snapshot Files</h2></div></div>
                <?php if ($recentSnapshotFiles === []): ?>
                    <div class="empty">No snapshot files were found in the configured directory yet. Once snapshots are enabled and telemetry is being served, the latest archive files will appear here.</div>
                <?php else: ?>
                    <div class="files">
                        <?php foreach ($recentSnapshotFiles as $snapshotFile): ?>
                            <article class="file">
                                <strong><?php echo htmlspecialchars(basename($snapshotFile), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="meta"><?php echo htmlspecialchars($snapshotFile, ENT_QUOTES, 'UTF-8'); ?><br><?php echo htmlspecialchars((string) filesize($snapshotFile), ENT_QUOTES, 'UTF-8'); ?> bytes • <?php echo htmlspecialchars(date('Y-m-d H:i:s', (int) (filemtime($snapshotFile) ?: time())), ENT_QUOTES, 'UTF-8'); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
