"use strict";

const fs = require("fs/promises");
const path = require("path");

const projectRoot = __dirname;
const defaultConfig = {
    sourceFile: path.join(projectRoot, "tmp", "telemetry-cache.json"),
    outputDir: path.join(projectRoot, "snapshots"),
    intervalMs: 60000,
    runImmediately: true,
    skipUnchanged: true,
};

let snapshotTimer = null;
let snapshotInProgress = false;
let lastSourceCacheTimestamp = null;

function parseBoolean(value, fallback) {
    if (value === undefined) {
        return fallback;
    }

    const normalized = String(value).trim().toLowerCase();
    if (["1", "true", "yes", "on"].includes(normalized)) {
        return true;
    }

    if (["0", "false", "no", "off"].includes(normalized)) {
        return false;
    }

    return fallback;
}

function parsePositiveInteger(value, fallback) {
    const parsed = Number.parseInt(String(value ?? ""), 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function getRuntimeConfig() {
    const args = new Set(process.argv.slice(2));
    const intervalArg = process.argv.slice(2).find((arg) => arg.startsWith("--interval="));
    const sourceArg = process.argv.slice(2).find((arg) => arg.startsWith("--source="));
    const outputArg = process.argv.slice(2).find((arg) => arg.startsWith("--output="));

    return {
        sourceFile: sourceArg
            ? path.resolve(projectRoot, sourceArg.slice("--source=".length))
            : path.resolve(projectRoot, process.env.SNAPSHOT_SOURCE || defaultConfig.sourceFile),
        outputDir: outputArg
            ? path.resolve(projectRoot, outputArg.slice("--output=".length))
            : path.resolve(projectRoot, process.env.SNAPSHOT_OUTPUT_DIR || defaultConfig.outputDir),
        intervalMs: intervalArg
            ? parsePositiveInteger(intervalArg.slice("--interval=".length), defaultConfig.intervalMs)
            : parsePositiveInteger(process.env.SNAPSHOT_INTERVAL_MS, defaultConfig.intervalMs),
        runImmediately: args.has("--no-immediate")
            ? false
            : parseBoolean(process.env.SNAPSHOT_RUN_IMMEDIATELY, defaultConfig.runImmediately),
        skipUnchanged: args.has("--allow-duplicates")
            ? false
            : parseBoolean(process.env.SNAPSHOT_SKIP_UNCHANGED, defaultConfig.skipUnchanged),
        once: args.has("--once"),
    };
}

function buildSnapshotFilename(date) {
    const isoStamp = date.toISOString().replaceAll(":", "-").replaceAll(".", "-");
    return `telemetry-${isoStamp}.json`;
}

async function readTelemetrySnapshot(sourceFile) {
    const raw = await fs.readFile(sourceFile, "utf8");
    const parsed = JSON.parse(raw);

    return {
        cachedAtMs: Number(parsed?.cachedAtMs || 0),
        data: parsed?.data ?? parsed,
    };
}

async function writeSnapshotFile(outputDir, payload) {
    await fs.mkdir(outputDir, { recursive: true });

    const snapshotTakenAt = new Date();
    const snapshotPath = path.join(outputDir, buildSnapshotFilename(snapshotTakenAt));
    const formattedPayload = JSON.stringify({
        snapshotTakenAt: snapshotTakenAt.toISOString(),
        sourceCachedAtMs: payload.cachedAtMs || null,
        data: payload.data,
    }, null, 2);

    await fs.writeFile(snapshotPath, `${formattedPayload}\n`, "utf8");
    return snapshotPath;
}

async function captureSnapshot(config) {
    if (snapshotInProgress) {
        return;
    }

    snapshotInProgress = true;

    try {
        const payload = await readTelemetrySnapshot(config.sourceFile);

        if (
            config.skipUnchanged
            && payload.cachedAtMs > 0
            && payload.cachedAtMs === lastSourceCacheTimestamp
        ) {
            console.log(`[snapshot] skipped unchanged telemetry cache (${payload.cachedAtMs})`);
            return;
        }

        const snapshotPath = await writeSnapshotFile(config.outputDir, payload);
        lastSourceCacheTimestamp = payload.cachedAtMs > 0 ? payload.cachedAtMs : lastSourceCacheTimestamp;
        console.log(`[snapshot] wrote ${snapshotPath}`);
    } catch (error) {
        console.error(`[snapshot] failed: ${error.message}`);
    } finally {
        snapshotInProgress = false;
    }
}

function stopSnapshotTimer() {
    if (snapshotTimer !== null) {
        clearInterval(snapshotTimer);
        snapshotTimer = null;
    }
}

function startSnapshotTimer() {
    const config = getRuntimeConfig();

    if (config.once) {
        return captureSnapshot(config);
    }

    if (config.runImmediately) {
        void captureSnapshot(config);
    }

    snapshotTimer = setInterval(() => {
        void captureSnapshot(config);
    }, config.intervalMs);

    console.log(
        `[snapshot] watching ${config.sourceFile} every ${config.intervalMs} ms -> ${config.outputDir}`,
    );

    return config;
}

process.on("SIGINT", () => {
    stopSnapshotTimer();
    process.exit(0);
});

process.on("SIGTERM", () => {
    stopSnapshotTimer();
    process.exit(0);
});

startSnapshotTimer();
