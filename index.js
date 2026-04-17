const config = window.dashboardConfig || {};
const telemetryEndpoint = config.telemetryEndpoint || "telemetry.php?format=json";
const refreshIntervalMs = Number(config.refreshIntervalMs) || 5000;
const telemetryRequestTimeoutMs = Number(config.telemetryRequestTimeoutMs) || Math.max(2500, refreshIntervalMs - 500);
const telemetryPollingConfig = config.telemetryPolling || {};
const speedRingConfig = config.speedRing || {};
const speedRingMaxDisplayKph = Number(speedRingConfig.maxDisplayKph) || 130;
const speedRingOverspeedToleranceKph = Number(speedRingConfig.overspeedToleranceKph) || 2;
const speedRingTrendSensitivityKph = Number(speedRingConfig.trendSensitivityKph) || 0.8;
const telemetryBackoffStepMs = Number(telemetryPollingConfig.backoffStepMs) || 1000;
const telemetryMaxBackoffMs = Number(telemetryPollingConfig.maxBackoffMs) || 30000;
const telemetryHiddenIntervalMs = Number(telemetryPollingConfig.hiddenIntervalMs) || 12000;
const activeTabStorageKey = (config.storageKeys && config.storageKeys.activeTab) || "ets2-dashboard-active-tab";
const mapPreferencesStorageKey = (config.storageKeys && config.storageKeys.mapPreferences) || "ets2-dashboard-map-preferences";
const tileProxyEndpoint = config.tileProxyEndpoint || "tile-proxy.php";
const tabsRoot = document.querySelector(".section-tabs");
const routePlannerAverageKph = Number(config.routePlanner?.averageKph) || 63;
const routePlannerRealTimeScale = Number(config.routePlanner?.realTimeScale) || 17.5;
const ets2MapBounds = {
    minX: Number(config.mapBounds?.minX) || -94118.3,
    maxX: Number(config.mapBounds?.maxX) || 128280,
    minZ: Number(config.mapBounds?.minZ) || -102857,
    maxZ: Number(config.mapBounds?.maxZ) || 57201.3,
};
const tileMapConfig = config.mapTiles || {};
const defaultTileBaseUrlCandidates = [
    "http://10.147.17.64/tiles/",
    "tiles",
    "maps",
    "http://127.0.0.1:8081",
];
const tileMapState = {
    initialized: false,
    config: null,
    sourceLabel: "Static preview",
    baseUrl: "",
    configUrl: "",
    tileTemplate: "",
    minZoom: 0,
    maxZoom: 8,
    nativeMaxZoom: 8,
    availableTileMaxZoom: 8,
    overzoomSteps: 2,
    zoom: 4,
    followTruck: true,
    manualCenter: null,
    currentTruckPixel: null,
    lastView: null,
    drag: {
        active: false,
        pointerId: null,
        startClientX: 0,
        startClientY: 0,
        startCenterX: 0,
        startCenterY: 0,
    },
};

const heroMapState = {
    zoom: 3,
    minZoom: 0,
    maxZoom: 8,
    defaultZoom: 3,
    followTruck: true,
    manualCenter: null,
    lastView: null,
    drag: {
        active: false,
        pointerId: null,
        startClientX: 0,
        startClientY: 0,
        startCenterX: 0,
        startCenterY: 0,
    },
};

const elements = {
    heroTitle: document.getElementById("hero-title"),
    heroSummary: document.getElementById("hero-summary"),
    heroTags: document.getElementById("hero-tags"),
    heroSpeedValue: document.getElementById("hero-speed-value"),
    speedPeak: document.getElementById("speed-peak"),
    speedTrend: document.getElementById("speed-trend"),
    speedAlert: document.getElementById("speed-alert"),
    speedLimitMarker: document.getElementById("speed-limit-marker"),
    roadSpeedValue: document.getElementById("road-speed-value"),
    roadSpeedLimit: document.getElementById("road-speed-limit"),
    cruiseControlSpeed: document.getElementById("cruise-control-speed"),
    cruiseControlLimit: document.getElementById("tempomat-speed-limit"),
    speedRing: document.getElementById("speed-ring"),
    lastUpdated: document.getElementById("last-updated"),
    refreshInterval: document.getElementById("refresh-interval"),
    connectionStatus: document.getElementById("connection-status"),
    metricFuel: document.getElementById("metric-fuel"),
    metricFuelNote: document.getElementById("metric-fuel-note"),
    metricRange: document.getElementById("metric-range"),
    metricRangeNote: document.getElementById("metric-range-note"),
    metricRpm: document.getElementById("metric-rpm"),
    metricRpmNote: document.getElementById("metric-rpm-note"),
    metricOdometer: document.getElementById("metric-odometer"),
    metricOdometerNote: document.getElementById("metric-odometer-note"),
    fromToValue: document.getElementById("from-to-value"),
    routeDistance: document.getElementById("route-distance"),
    routeTime: document.getElementById("route-time"),
    routeRealTime: document.getElementById("route-real-time"),
    fuelRange: document.getElementById("fuel-range"),
    heroMap: document.getElementById("hero-map"),
    heroMapStage: document.getElementById("hero-map-stage"),
    heroMapTiles: document.getElementById("hero-map-tiles"),
    heroMapFallback: document.getElementById("hero-map-fallback"),
    heroMapMarker: document.getElementById("hero-map-marker"),
    heroMapCenter: document.getElementById("hero-map-center"),
    heroMapShortcuts: document.getElementById("hero-map-shortcuts"),
    heroMapJobIncome: document.getElementById("hero-map-job-income"),
    heroMapJobCargo: document.getElementById("hero-map-job-cargo"),
    heroMapJobWeight: document.getElementById("hero-map-job-weight"),
    jobFinishedPopup: document.getElementById("job-finished-popup"),
    jobFinishedPopupBadge: document.getElementById("job-finished-popup-badge"),
    jobFinishedPopupTitle: document.getElementById("job-finished-popup-title"),
    jobFinishedPopupMeta: document.getElementById("job-finished-popup-meta"),
    jobFinishedPopupRevenue: document.getElementById("job-finished-popup-revenue"),
    jobFinishedPopupXp: document.getElementById("job-finished-popup-xp"),
    jobFinishedPopupDistance: document.getElementById("job-finished-popup-distance"),
    jobFinishedPopupParking: document.getElementById("job-finished-popup-parking"),
    mapBadge: document.getElementById("map-badge"),
    ets2Map: document.getElementById("ets2-map"),
    ets2MapStage: document.getElementById("ets2-map-stage"),
    ets2MapTiles: document.getElementById("ets2-map-tiles"),
    ets2MapFallback: document.getElementById("ets2-map-fallback"),
    ets2MapMarker: document.getElementById("ets2-map-marker"),
    ets2MapLabel: document.getElementById("ets2-map-label"),
    ets2MapMode: document.getElementById("ets2-map-mode"),
    ets2MapCenter: document.getElementById("ets2-map-center"),
    ets2MapShortcuts: document.getElementById("ets2-map-shortcuts"),
    mapJobIncome: document.getElementById("map-job-income"),
    mapJobCargo: document.getElementById("map-job-cargo"),
    mapJobWeight: document.getElementById("map-job-weight"),
    mapMeta: document.getElementById("map-meta"),
    routeBadge: document.getElementById("route-badge"),
    routeSource: document.getElementById("route-source"),
    routeDestination: document.getElementById("route-destination"),
    routeStats: document.getElementById("route-stats"),
    truckStats: document.getElementById("truck-stats"),
    systemsPills: document.getElementById("systems-pills"),
    systemsGauges: document.getElementById("systems-gauges"),
    drivetrainStats: document.getElementById("drivetrain-stats"),
    alertFeed: document.getElementById("alert-feed"),
    controlsList: document.getElementById("controls-list"),
    lightGrid: document.getElementById("light-grid"),
    trailerBadge: document.getElementById("trailer-badge"),
    trailerSummary: document.getElementById("trailer-summary"),
    trailerGauges: document.getElementById("trailer-gauges"),
    worldStats: document.getElementById("world-stats"),
    eventStats: document.getElementById("event-stats"),
    telemetryOutput: document.getElementById("telemetry-output"),
};

const tabButtons = Array.from(document.querySelectorAll("[data-tab]"));
const tabPanels = Array.from(document.querySelectorAll("[data-tab-panel]"));
const mapZoomButtons = Array.from(document.querySelectorAll("[data-map-zoom]"));
const heroMapZoomButtons = Array.from(document.querySelectorAll("[data-hero-map-zoom]"));
const systemLocale = typeof navigator !== "undefined"
    ? (navigator.languages?.[0] || navigator.language || "en-US")
    : "en-US";
const cityLocalizationState = {
    loaded: false,
    loading: false,
    cityByKey: new Map(),
    localeCandidates: buildLocaleCandidates(systemLocale),
};

let refreshTimer = null;
let tileMapRetryTimer = null;
let latestTelemetryData = null;
let mapRenderFrameHandle = null;
let mapRenderUsesAnimationFrame = false;
let pendingMapRenderData = null;
let pendingRouteRender = false;
let telemetryRequestInFlight = false;
let telemetryAbortController = null;
let telemetryTimeoutHandle = null;
let hasLoadedMapPreferences = false;
let activeMapTarget = "world";
let telemetryConsecutiveFailures = 0;
let telemetryLastSourceType = "upstream";
let speedRingPeakKph = 0;
let speedRingPreviousKph = null;
let previousJobFinishedState = false;
let previousJobDeliveredState = false;
let jobFinishedPopupVisibleUntil = 0;
let previousJobFinishedSignature = "";
let jobFinishedPopupHydrated = false;
const jobFinishedPopupDurationMs = 5000;

function buildLocaleCandidates(locale) {
    const raw = String(locale || "").trim().toLowerCase().replaceAll("-", "_");
    const candidates = [];

    if (raw) {
        candidates.push(raw);
        const [language] = raw.split("_");
        if (language && language !== raw) {
            candidates.push(language);
        }
    }

    ["en_us", "en_gb", "en"].forEach((value) => {
        if (!candidates.includes(value)) {
            candidates.push(value);
        }
    });

    return candidates;
}

function normalizeCityLookupKey(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .normalize("NFKD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ");
}

function pickLocalizedCityName(localizedNames, candidates) {
    if (!localizedNames || typeof localizedNames !== "object") {
        return null;
    }

    const entries = Object.entries(localizedNames).filter(([, value]) => typeof value === "string" && value.trim() !== "");
    if (entries.length === 0) {
        return null;
    }

    const normalized = new Map(entries.map(([key, value]) => [String(key).toLowerCase(), value]));

    for (const candidate of candidates) {
        if (normalized.has(candidate)) {
            return normalized.get(candidate) || null;
        }
    }

    for (const candidate of candidates) {
        if (!candidate.includes("_")) {
            const prefixed = `${candidate}_`;
            const key = Array.from(normalized.keys()).find((name) => name.startsWith(prefixed));
            if (key) {
                return normalized.get(key) || null;
            }
        }
    }

    return entries[0][1] || null;
}

function localizeCityName(cityName) {
    if (typeof cityName !== "string" || cityName.trim() === "") {
        return cityName;
    }

    const key = normalizeCityLookupKey(cityName);
    const match = cityLocalizationState.cityByKey.get(key);
    if (!match) {
        return cityName;
    }

    const localized = pickLocalizedCityName(match.localizedNames, cityLocalizationState.localeCandidates);
    return localized || match.name || cityName;
}

function formatLocalizedRouteLocation(cityName, companyName, fallback) {
    if (typeof cityName !== "string" || cityName.trim() === "") {
        return fallback;
    }

    const localizedCity = localizeCityName(cityName);
    return `${localizedCity}${companyName ? ` • ${companyName}` : ""}`;
}

function loadCityLocalizations() {
    if (cityLocalizationState.loaded || cityLocalizationState.loading) {
        return;
    }

    const baseUrlCandidates = Array.isArray(tileMapConfig.baseUrlCandidates) && tileMapConfig.baseUrlCandidates.length > 0
        ? tileMapConfig.baseUrlCandidates
        : defaultTileBaseUrlCandidates;
    const prioritizedBaseUrls = Array.from(new Set([
        tileMapState.baseUrl,
        ...baseUrlCandidates,
    ].filter((baseUrl) => typeof baseUrl === "string" && baseUrl.trim() !== "")));

    cityLocalizationState.loading = true;
    (async () => {
        for (const baseUrl of prioritizedBaseUrls) {
            try {
                const response = await window.fetch(buildTileRequestUrl(baseUrl, "Cities.json"), { cache: "force-cache" });
                if (!response.ok) {
                    throw new Error(`Failed to load city localizations (${response.status})`);
                }

                const payload = await response.json();
                if (!Array.isArray(payload)) {
                    continue;
                }

                const lookup = new Map();
                payload.forEach((city) => {
                    const baseName = typeof city?.Name === "string" ? city.Name : "";
                    if (!baseName) {
                        return;
                    }

                    const localizedNames = city?.LocalizedNames && typeof city.LocalizedNames === "object"
                        ? city.LocalizedNames
                        : {};
                    const entry = { name: baseName, localizedNames };
                    const aliases = [
                        baseName,
                        ...Object.values(localizedNames).filter((value) => typeof value === "string"),
                    ];

                    aliases.forEach((alias) => {
                        const aliasKey = normalizeCityLookupKey(alias);
                        if (aliasKey && !lookup.has(aliasKey)) {
                            lookup.set(aliasKey, entry);
                        }
                    });
                });

                cityLocalizationState.cityByKey = lookup;
                cityLocalizationState.loaded = true;

                rerenderMapsFromLatestData(true);
                return;
            } catch (error) {
                // Try the next configured source.
            }
        }
    })()
        .catch(() => {
            // Ignore localization fetch failures and keep telemetry rendering with source names.
        })
        .finally(() => {
            cityLocalizationState.loading = false;
        });
}

function clampTelemetryDelay(value) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        return refreshIntervalMs;
    }

    return Math.max(250, Math.min(parsed, telemetryMaxBackoffMs));
}

function getNextTelemetryDelayMs() {
    let delay = refreshIntervalMs;

    if (telemetryConsecutiveFailures > 0) {
        delay = Math.min(
            refreshIntervalMs + (telemetryConsecutiveFailures * telemetryBackoffStepMs),
            telemetryMaxBackoffMs,
        );
    }

    if (telemetryLastSourceType === "cache") {
        delay = Math.max(delay, Math.min(telemetryMaxBackoffMs, refreshIntervalMs * 2));
    }

    if (typeof document !== "undefined" && document.visibilityState === "hidden") {
        delay = Math.max(delay, telemetryHiddenIntervalMs);
    }

    return clampTelemetryDelay(delay);
}

function scheduleTelemetryUpdate(delayMs = refreshIntervalMs) {
    if (refreshTimer !== null) {
        window.clearTimeout(refreshTimer);
    }

    const normalizedDelay = clampTelemetryDelay(delayMs);
    refreshTimer = window.setTimeout(() => {
        refreshTimer = null;
        updateTelemetry();
    }, normalizedDelay);
}

function applyTelemetrySourceStatus(payload) {
    const source = payload?.source || null;
    const sourceType = source && typeof source.type === "string" ? source.type : "upstream";

    telemetryLastSourceType = sourceType;

    if (sourceType === "upstream") {
        telemetryConsecutiveFailures = 0;
        setConnectionState("Connected", "connected");
        return;
    }

    if (sourceType === "cache") {
        telemetryConsecutiveFailures = 0;
        setConnectionState("Cached", "cached");
        if (elements.heroSummary) {
            const current = elements.heroSummary.textContent || "";
            if (!current.includes("cached snapshot")) {
                elements.heroSummary.textContent = `${current} • Using cached snapshot`;
            }
        }
        return;
    }

    setConnectionState("Disconnected", "error");
}

function setActiveMapTarget(target) {
    if (target === "world" || target === "hero") {
        activeMapTarget = target;
        updateMapInteractionHints();
    }
}

function updateMapInteractionHints() {
    if (elements.ets2MapStage) {
        elements.ets2MapStage.dataset.active = activeMapTarget === "world" ? "true" : "false";
    }

    if (elements.heroMapStage) {
        elements.heroMapStage.dataset.active = activeMapTarget === "hero" ? "true" : "false";
    }

    if (elements.ets2MapShortcuts) {
        elements.ets2MapShortcuts.textContent = activeMapTarget === "world"
            ? "Active: +/- zoom, C center"
            : "Click map to target";
    }

    if (elements.heroMapShortcuts) {
        elements.heroMapShortcuts.textContent = activeMapTarget === "hero"
            ? "Active: +/- zoom, C center"
            : "Click map to target";
    }
}

function getLatestRenderableTelemetryData() {
    if (latestTelemetryData && typeof latestTelemetryData === "object") {
        return latestTelemetryData;
    }

    const initialData = config.initialPayload?.data;
    return initialData && typeof initialData === "object" ? initialData : null;
}

function flushScheduledMapRender() {
    mapRenderFrameHandle = null;

    const data = pendingMapRenderData || getLatestRenderableTelemetryData();
    const shouldRenderRoute = pendingRouteRender;

    pendingMapRenderData = null;
    pendingRouteRender = false;

    if (!data) {
        return;
    }

    if (shouldRenderRoute) {
        renderRoute(data);
    }

    renderMap(data);
}

function scheduleMapRender(data = null, includeRoute = false) {
    if (data && typeof data === "object") {
        pendingMapRenderData = data;
    } else if (!pendingMapRenderData) {
        pendingMapRenderData = getLatestRenderableTelemetryData();
    }

    pendingRouteRender = pendingRouteRender || includeRoute;

    if (mapRenderFrameHandle !== null) {
        return;
    }

    if (typeof window.requestAnimationFrame === "function") {
        mapRenderUsesAnimationFrame = true;
        mapRenderFrameHandle = window.requestAnimationFrame(() => {
            flushScheduledMapRender();
        });
        return;
    }

    mapRenderUsesAnimationFrame = false;
    mapRenderFrameHandle = window.setTimeout(() => {
        flushScheduledMapRender();
    }, 16);
}

function rerenderMapsFromLatestData(includeRoute = false) {
    scheduleMapRender(null, includeRoute);
}

function centerWorldMap() {
    setMapFollowTruck(true);
    rerenderMapsFromLatestData();
}

function centerHeroMap(resetZoom = true) {
    if (resetZoom) {
        heroMapState.zoom = heroMapState.defaultZoom;
    }

    setHeroMapFollowTruck(true);
    persistMapPreferences();
    rerenderMapsFromLatestData();
}

function isEditableTarget(target) {
    if (!(target instanceof Element)) {
        return false;
    }

    if (target.closest("input, textarea, select")) {
        return true;
    }

    return Boolean(target.closest("[contenteditable='true']"));
}

function handleGlobalMapShortcuts(event) {
    if (event.repeat || event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }

    if (isEditableTarget(event.target)) {
        return;
    }

    const key = String(event.key || "");
    const lowerKey = key.toLowerCase();
    const isZoomIn = key === "+" || key === "=" || event.code === "NumpadAdd";
    const isZoomOut = key === "-" || key === "_" || event.code === "NumpadSubtract";

    if (isZoomIn) {
        event.preventDefault();
        if (activeMapTarget === "hero") {
            applyHeroMapZoom(1);
        } else {
            applyWorldMapZoom(1);
        }
        return;
    }

    if (isZoomOut) {
        event.preventDefault();
        if (activeMapTarget === "hero") {
            applyHeroMapZoom(-1);
        } else {
            applyWorldMapZoom(-1);
        }
        return;
    }

    if (lowerKey === "c") {
        event.preventDefault();
        if (activeMapTarget === "hero") {
            centerHeroMap(false);
        } else {
            centerWorldMap();
        }
    }
}

function persistMapPreferences() {
    try {
        window.localStorage.setItem(mapPreferencesStorageKey, JSON.stringify({
            worldZoom: tileMapState.zoom,
            worldFollowTruck: tileMapState.followTruck,
            heroZoom: heroMapState.zoom,
            heroFollowTruck: heroMapState.followTruck,
        }));
    } catch (error) {
        // Ignore storage failures to keep runtime behavior stable.
    }
}

function loadMapPreferences() {
    try {
        const raw = window.localStorage.getItem(mapPreferencesStorageKey);
        if (!raw) {
            return;
        }

        const parsed = JSON.parse(raw);
        hasLoadedMapPreferences = true;
        const worldZoom = getNumber(parsed?.worldZoom);
        const heroZoom = getNumber(parsed?.heroZoom);

        if (worldZoom !== null) {
            tileMapState.zoom = Math.floor(worldZoom);
        }

        if (heroZoom !== null) {
            heroMapState.zoom = Math.floor(heroZoom);
        }

        if (typeof parsed?.worldFollowTruck === "boolean") {
            tileMapState.followTruck = parsed.worldFollowTruck;
        }

        if (typeof parsed?.heroFollowTruck === "boolean") {
            heroMapState.followTruck = parsed.heroFollowTruck;
        }
    } catch (error) {
        // Ignore malformed data and fall back to defaults.
    }
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function getNumber(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function formatNumber(value, digits = 0) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return "--";
    }

    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: digits,
        minimumFractionDigits: digits,
    }).format(parsed);
}

function formatTruckSpeed(speedKph) {
    const parsed = getNumber(speedKph);
    if (parsed === null) {
        return "--";
    }

    const kph = Math.max(0, parsed);
    return formatNumber(kph, kph < 10 ? 1 : 0);
}

function formatRoadSpeed(speedKph) {
    const parsed = getNumber(speedKph);
    if (parsed === null) {
        return "--";
    }

    const safe = Math.max(0, parsed);
    return formatNumber(safe, safe < 10 ? 1 : 0);
}

function normalizeRoadLimitKph(speedLimitValue) {
    const parsed = getNumber(speedLimitValue);
    if (parsed === null) {
        return null;
    }

    return Math.max(0, parsed);
}

function formatPercent(value, digits = 0, ratio = false) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return "--";
    }

    const percent = ratio ? parsed * 100 : parsed;
    return `${formatNumber(percent, digits)}%`;
}

function formatDistanceKm(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : `${formatNumber(parsed, parsed < 10 ? 1 : 0)} km`;
}

function formatLiters(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : `${formatNumber(parsed, parsed < 10 ? 1 : 0)} L`;
}

function formatRpm(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : `${formatNumber(parsed, 0)} rpm`;
}

function formatTemperature(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : `${formatNumber(parsed, 1)} C`;
}

function formatVoltage(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : `${formatNumber(parsed, 1)} V`;
}

function formatPressure(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : `${formatNumber(parsed, 1)} psi`;
}

function formatMass(value) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return "--";
    }

    const tonnes = parsed / 1000;
    const digits = tonnes < 10 ? 2 : 1;
    return `${formatNumber(tonnes, digits)} t`;
}

function formatIncome(value) {
    const parsed = getNumber(value);
    return parsed === null ? "--" : formatNumber(parsed, 0);
}

function formatIncomeLabel(value) {
    const formatted = formatIncome(value);
    return formatted === "--" ? "Income --" : `Income ${formatted}`;
}

function buildJobFinishedSignature(gameplay = {}, job = {}) {
    const delivery = gameplay.jobDeliveredDetails || {};
    const signatureParts = [
        delivery.revenue,
        delivery.earnedXp,
        delivery.distanceKm,
        delivery.deliveryTime,
        delivery.autoParked ? "auto" : "manual",
        job.cargo,
        job.sourceCity,
        job.destinationCity,
    ]
        .map((value) => String(value ?? "").trim())
        .filter((value) => value !== "");

    return signatureParts.join("|");
}

function formatCoordinate(value) {
    return formatNumber(value, 2);
}

function formatAngleDegrees(value) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return "--";
    }

    return `${formatNumber((parsed * 180) / Math.PI, 1)} deg`;
}

function normalizeDegrees(value) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return 0;
    }

    return ((parsed % 360) + 360) % 360;
}

function getMapProjectionPoint(x, z) {
    if (tileMapState.initialized && tileMapState.config) {
        return gameCoordsToTilePixels(x, z, tileMapState.config);
    }

    const width = ets2MapBounds.maxX - ets2MapBounds.minX;
    const height = ets2MapBounds.maxZ - ets2MapBounds.minZ;
    if (width <= 0 || height <= 0) {
        return null;
    }

    return {
        pixelX: clamp01((x - ets2MapBounds.minX) / width),
        pixelY: clamp01((ets2MapBounds.maxZ - z) / height),
    };
}

function getMarkerHeadingDegrees(headingRadians, x, z) {
    const heading = getNumber(headingRadians);
    const originX = getNumber(x);
    const originZ = getNumber(z);
    if (heading === null) {
        return 0;
    }

    if (originX === null || originZ === null) {
        return normalizeDegrees((heading * 180) / Math.PI);
    }

    const forwardSampleDistance = 32;
    const originPoint = getMapProjectionPoint(originX, originZ);
    const forwardPoint = getMapProjectionPoint(
        originX + (Math.sin(heading) * forwardSampleDistance),
        originZ + (Math.cos(heading) * forwardSampleDistance),
    );

    if (originPoint && forwardPoint) {
        const deltaX = forwardPoint.pixelX - originPoint.pixelX;
        const deltaY = forwardPoint.pixelY - originPoint.pixelY;
        if (Math.abs(deltaX) > 0.0001 || Math.abs(deltaY) > 0.0001) {
            return normalizeDegrees(((Math.atan2(deltaY, deltaX) * 180) / Math.PI) + 90);
        }
    }

    return normalizeDegrees((heading * 180) / Math.PI);
}

function formatGameClock(value) {
    if (!value) {
        return "--";
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return "--";
    }

    const day = parsed.getUTCDate();
    const hours = String(parsed.getUTCHours()).padStart(2, "0");
    const minutes = String(parsed.getUTCMinutes()).padStart(2, "0");
    return `Day ${day} • ${hours}:${minutes}`;
}

function formatGameEventTime(value) {
    if (!value || value.startsWith("0001-01-01T00:00:00")) {
        return "--";
    }

    return formatGameClock(value);
}

function formatLocalTime(value) {
    if (!value) {
        return "--";
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return "--";
    }

    return parsed.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    });
}

function formatDurationMinutes(value) {
    const parsed = getNumber(value);
    if (parsed === null || parsed <= 0) {
        return "--";
    }

    const hours = Math.floor(parsed / 60);
    const minutes = Math.round(parsed % 60);

    if (hours <= 0) {
        return `${minutes} min`;
    }

    return `${hours}h ${minutes}m`;
}

function formatDurationHoursMinutes(value) {
    const parsed = getNumber(value);
    if (parsed === null || parsed < 0) {
        return "--";
    }

    const totalMinutes = Math.max(0, Math.round(parsed));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours <= 0) {
        return `${minutes}m`;
    }

    return `${hours}h ${minutes}m`;
}

function getTelemetryDurationMinutes(value) {
    const numeric = getNumber(value);
    if (numeric !== null) {
        // Numeric ETA values are typically seconds.
        return numeric / 60;
    }

    if (typeof value !== "string" || !value) {
        return null;
    }

    const durationLikeMatch = value.match(/^0*(\d{1,4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?(Z)?$/);
    if (durationLikeMatch) {
        const [, yearText, monthText, dayText, hourText, minuteText, secondText] = durationLikeMatch;
        const year = Number(yearText);
        const month = Number(monthText);
        const day = Number(dayText);
        const hours = Number(hourText);
        const minutes = Number(minuteText);
        const seconds = Number(secondText || 0);

        // ETS2 uses year-1 timestamps as duration containers.
        if (year === 1 && month === 1) {
            const totalMinutes = ((day - 1) * 24 * 60) + (hours * 60) + minutes + (seconds / 60);
            return totalMinutes >= 0 ? totalMinutes : null;
        }
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.getTime() / 60000;
}

function formatDurationClockMinutes(value) {
    const parsed = getNumber(value);
    if (parsed === null || parsed < 0) {
        return "--:--";
    }

    const totalMinutes = Math.max(0, Math.round(parsed));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = String(totalMinutes % 60).padStart(2, "0");
    return `${hours}:${minutes}`;
}

function formatEstimatedRouteTime(value) {
    const durationMinutes = getTelemetryDurationMinutes(value);
    if (durationMinutes === null) {
        return "--:--";
    }

    return formatDurationClockMinutes(durationMinutes);
}

function formatEstimatedRealTime(value, timeScale) {
    const estimatedMinutes = getTelemetryDurationMinutes(value);
    const scale = getNumber(timeScale);

    if (estimatedMinutes === null || estimatedMinutes < 0) {
        return "";
    }

    if (scale === null || scale <= 0) {
        return "";
    }

    const realMinutes = estimatedMinutes / scale;
    const compact = formatDurationMinutes(realMinutes);
    return compact === "--" ? "" : `${compact} real`;
}

function getDistanceBasedRouteMinutes(distanceMeters) {
    const distanceKm = getNumber(distanceMeters);
    if (distanceKm === null || distanceKm <= 0) {
        return null;
    }

    return (distanceKm / 1000 / routePlannerAverageKph) * 60;
}

function getPreferredRouteMinutes(navigation = {}) {
    const telemetryMinutes = getTelemetryDurationMinutes(navigation.estimatedTime);
    const distanceMinutes = getDistanceBasedRouteMinutes(navigation.estimatedDistance);

    if (distanceMinutes !== null && telemetryMinutes !== null && Math.abs(distanceMinutes - telemetryMinutes) > 45) {
        return distanceMinutes;
    }

    return telemetryMinutes ?? distanceMinutes;
}

function formatRouteEtaFromNavigation(navigation = {}) {
    const routeMinutes = getPreferredRouteMinutes(navigation);
    return routeMinutes === null ? "--" : formatDurationHoursMinutes(routeMinutes);
}

function formatRouteRealTimeFromNavigation(navigation = {}) {
    const routeMinutes = getPreferredRouteMinutes(navigation);
    if (routeMinutes === null) {
        return "--:--";
    }

    const compact = formatDurationMinutes(routeMinutes / routePlannerRealTimeScale);
    return compact === "--" ? "--:--" : `${compact}`;
}

function getNavigationDistanceKm(navigation = {}) {
    const distanceMeters = getNumber(navigation.estimatedDistance);
    return distanceMeters === null ? null : distanceMeters / 1000;
}

function formatGear(truck = {}) {
    const displayedGear = getNumber(truck.displayedGear);
    const realGear = getNumber(truck.gear);

    if (displayedGear === null) {
        return "--";
    }

    if (realGear !== null && realGear < 0) {
        return `R${Math.abs(displayedGear) || 1}`;
    }

    if (displayedGear === 0 || realGear === 0) {
        return "N";
    }

    return `${displayedGear}`;
}

function boolLabel(value, activeLabel = "On", inactiveLabel = "Off") {
    return value ? activeLabel : inactiveLabel;
}

function clampProgress(value) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return 0;
    }

    return Math.max(0, Math.min(parsed, 100));
}

function clamp01(value) {
    const parsed = getNumber(value);
    if (parsed === null) {
        return 0;
    }

    return Math.max(0, Math.min(parsed, 1));
}

function createStatePill(label, active) {
    return `<span class="state-pill" data-active="${active ? "true" : "false"}">${escapeHtml(label)}</span>`;
}

function createStatCard(label, value, note = "") {
    return `
        <div class="stat-card">
            <span class="stat-label">${escapeHtml(label)}</span>
            <strong class="stat-value">${escapeHtml(value)}</strong>
            <span class="stat-note">${escapeHtml(note)}</span>
        </div>
    `;
}

function createGauge(label, value, note = "", tone = "teal") {
    const progress = clampProgress(value);
    return `
        <div class="gauge-card">
            <div class="gauge-copy">
                <span class="stat-label">${escapeHtml(label)}</span>
                <strong class="stat-value">${formatPercent(progress)}</strong>
                <span class="stat-note">${escapeHtml(note)}</span>
            </div>
            <div class="gauge-track" data-tone="${escapeHtml(tone)}">
                <span style="width: ${progress}%;"></span>
            </div>
        </div>
    `;
}

function createInputRow(label, value, note = "", tone = "sunset") {
    const progress = clampProgress(value);
    return `
        <div class="control-row">
            <div class="control-copy">
                <span class="stat-label">${escapeHtml(label)}</span>
                <strong class="stat-value">${formatPercent(progress)}</strong>
                <span class="stat-note">${escapeHtml(note)}</span>
            </div>
            <div class="gauge-track" data-tone="${escapeHtml(tone)}">
                <span style="width: ${progress}%;"></span>
            </div>
        </div>
    `;
}

function createSignalTile(label, active, note = "") {
    return `
        <div class="signal-tile" data-active="${active ? "true" : "false"}">
            <span class="signal-label">${escapeHtml(label)}</span>
            <strong class="signal-value">${escapeHtml(active ? "Active" : "Idle")}</strong>
            <span class="signal-note">${escapeHtml(note)}</span>
        </div>
    `;
}

function createAlertItem(title, detail, tone = "good") {
    return `
        <div class="alert-item" data-tone="${escapeHtml(tone)}">
            <strong>${escapeHtml(title)}</strong>
            <span>${escapeHtml(detail)}</span>
        </div>
    `;
}

function createMetaPill(label, value) {
    return `
        <div class="map-meta-pill">
            <span class="stat-label">${escapeHtml(label)}</span>
            <strong class="stat-value">${escapeHtml(value)}</strong>
        </div>
    `;
}

function joinUrlParts(baseUrl, path) {
    const normalizedBase = String(baseUrl || "").replace(/\/+$/, "");
    const normalizedPath = String(path || "").replace(/^\/+/, "");
    return normalizedBase ? `${normalizedBase}/${normalizedPath}` : normalizedPath;
}

function isAbsoluteHttpUrl(value) {
    return /^https?:\/\//i.test(String(value || "").trim());
}

function buildTileRequestUrl(baseUrl, path) {
    const resourceUrl = joinUrlParts(baseUrl, path);
    if (!resourceUrl) {
        return "";
    }

    if (!isAbsoluteHttpUrl(baseUrl)) {
        return resourceUrl;
    }

    return `${tileProxyEndpoint}?url=${encodeURIComponent(resourceUrl)}`;
}

function buildTileUrl(baseUrl, tileTemplate, zoom, x, y) {
    const path = String(tileTemplate || "")
        .replace("{z}", String(zoom))
        .replace("{x}", String(x))
        .replace("{y}", String(y));

    return buildTileRequestUrl(baseUrl, path);
}

async function tileExists(baseUrl, tileTemplate, zoom, x, y) {
    const tileUrl = buildTileUrl(baseUrl, tileTemplate, zoom, x, y);

    try {
        // Prefer HEAD so probes do not download tile bodies.
        const headResponse = await fetch(tileUrl, {
            method: "HEAD",
            cache: "no-store",
        });
        if (headResponse.ok) {
            return true;
        }

        // Some static servers do not implement HEAD correctly.
        if (headResponse.status === 405 || headResponse.status === 501) {
            const getResponse = await fetch(tileUrl, {
                method: "GET",
                cache: "no-store",
            });
            return getResponse.ok;
        }

        return false;
    } catch (error) {
        return false;
    }
}

async function detectAvailableMaxZoom(normalized) {
    if (!normalized || !normalized.tileTemplate || !normalized.tileTemplate.includes("{y}")) {
        return normalized?.maxZoom ?? 0;
    }

    for (let zoom = normalized.maxZoom; zoom >= normalized.minZoom; zoom -= 1) {
        const maxTileIndex = Math.max(0, (2 ** zoom) - 1);
        const sampleCoordinates = [
            [0, 0],
            [Math.min(1, maxTileIndex), Math.min(1, maxTileIndex)],
            [Math.floor(maxTileIndex / 2), Math.floor(maxTileIndex / 2)],
        ];

        let foundExistingTile = false;
        for (const [tileX, tileY] of sampleCoordinates) {
            const exists = await tileExists(normalized.baseUrl, normalized.tileTemplate, zoom, tileX, tileY);
            if (exists) {
                foundExistingTile = true;
                break;
            }
        }

        if (foundExistingTile) {
            return zoom;
        }
    }

    return normalized.minZoom;
}

async function tryFetchJson(url) {
    const response = await fetch(url, {
        headers: { Accept: "application/json" },
        cache: "no-store",
    });

    if (!response.ok) {
        throw new Error(`Map config request failed with status ${response.status}`);
    }

    return response.json();
}

function normalizeTileMapDefinition(rawConfig, baseUrl, configName) {
    const map = rawConfig?.map || rawConfig;
    const transposition = rawConfig?.transposition;
    const minZoom = getNumber(rawConfig.minZoom) ?? getNumber(map.minZoom) ?? 0;
    const maxZoom = getNumber(rawConfig.maxZoom) ?? getNumber(map.maxZoom) ?? 8;
    const tileSize = getNumber(map.tileSize) ?? getNumber(rawConfig.tileSize) ?? 256;
    const tileTemplate = rawConfig.tileTemplate
        || rawConfig.tiles
        || rawConfig.tilePath
        || (configName.toLowerCase() === "config.json" ? "tiles/{z}/{x}/{y}.png" : "Tiles/{z}/{x}/{y}.png");

    const x1 = getNumber(rawConfig.x1);
    const x2 = getNumber(rawConfig.x2);
    const y1 = getNumber(rawConfig.y1);
    const y2 = getNumber(rawConfig.y2);

    if ([x1, x2, y1, y2].every((value) => value !== null) && maxZoom !== null) {
        return {
            map: {
                maxX: tileSize * (2 ** maxZoom),
                maxY: tileSize * (2 ** maxZoom),
                tileSize,
            },
            bounds: { x1, x2, y1, y2 },
            minZoom,
            maxZoom,
            tileTemplate,
            baseUrl,
            sourceLabel: `Tiles • ${baseUrl}`,
        };
    }

    if (!map || !transposition?.x || !transposition?.y) {
        return null;
    }

    const maxX = getNumber(map.maxX);
    const maxY = getNumber(map.maxY);

    if (maxX === null || maxY === null) {
        return null;
    }

    const xFactor = getNumber(transposition.x.factor);
    const xOffset = getNumber(transposition.x.offset);
    const yFactor = getNumber(transposition.y.factor);
    const yOffset = getNumber(transposition.y.offset);

    if ([xFactor, xOffset, yFactor, yOffset].some((value) => value === null || value === 0)) {
        return null;
    }

    return {
        map: {
            maxX,
            maxY,
            tileSize,
        },
        transposition: {
            x: { factor: xFactor, offset: xOffset },
            y: { factor: yFactor, offset: yOffset },
        },
        minZoom,
        maxZoom,
        tileTemplate,
        baseUrl,
        sourceLabel: `Tiles • ${baseUrl}`,
    };
}

async function initializeTileMap() {
    if (tileMapState.initialized) {
        return;
    }

    const baseUrlCandidates = Array.isArray(tileMapConfig.baseUrlCandidates) && tileMapConfig.baseUrlCandidates.length > 0
        ? tileMapConfig.baseUrlCandidates
        : defaultTileBaseUrlCandidates;
    const configNames = Array.isArray(tileMapConfig.configNames) && tileMapConfig.configNames.length > 0
        ? tileMapConfig.configNames
        : ["config.json", "TileMapInfo.json"];

    for (const baseUrl of baseUrlCandidates) {
        for (const configName of configNames) {
            const configUrl = buildTileRequestUrl(baseUrl, configName);

            try {
                const rawConfig = await tryFetchJson(configUrl);
                const normalized = normalizeTileMapDefinition(rawConfig, baseUrl, configName);

                if (!normalized) {
                    continue;
                }

                tileMapState.initialized = true;
                tileMapState.config = normalized;
                tileMapState.baseUrl = baseUrl;
                tileMapState.configUrl = configUrl;
                tileMapState.tileTemplate = normalized.tileTemplate;
                tileMapState.minZoom = normalized.minZoom;
                tileMapState.nativeMaxZoom = normalized.maxZoom;
                tileMapState.availableTileMaxZoom = await detectAvailableMaxZoom(normalized);
                tileMapState.overzoomSteps = Math.max(0, Math.floor(getNumber(tileMapConfig.overzoomSteps) ?? 2));
                tileMapState.maxZoom = tileMapState.availableTileMaxZoom + tileMapState.overzoomSteps;
                const worldDefaultZoom = Math.floor((normalized.minZoom + tileMapState.availableTileMaxZoom) / 2);
                tileMapState.zoom = Math.max(normalized.minZoom, Math.min(tileMapState.maxZoom, hasLoadedMapPreferences ? tileMapState.zoom : worldDefaultZoom));
                if (tileMapState.zoom > tileMapState.maxZoom) {
                    tileMapState.zoom = tileMapState.maxZoom;
                }
                heroMapState.minZoom = tileMapState.minZoom;
                heroMapState.maxZoom = tileMapState.maxZoom;
                heroMapState.defaultZoom = Math.max(heroMapState.minZoom, Math.min(heroMapState.maxZoom, tileMapState.availableTileMaxZoom - 1));
                heroMapState.zoom = Math.max(heroMapState.minZoom, Math.min(heroMapState.maxZoom, hasLoadedMapPreferences ? heroMapState.zoom : heroMapState.defaultZoom));
                setHeroMapFollowTruck(heroMapState.followTruck);
                tileMapState.sourceLabel = normalized.sourceLabel;

                updateMapModeLabel();
                persistMapPreferences();
                rerenderMapsFromLatestData();
                return;
            } catch (error) {
                // Keep trying the next candidate.
            }
        }
    }

    updateMapModeLabel();
    tileMapRetryTimer = window.setTimeout(() => {
        tileMapRetryTimer = null;
        initializeTileMap();
    }, 8000);
}

function updateMapModeLabel() {
    if (elements.ets2MapMode) {
        if (!tileMapState.initialized) {
            elements.ets2MapMode.textContent = "Static preview";
        } else {
            elements.ets2MapMode.textContent = `${tileMapState.sourceLabel} • ${tileMapState.followTruck ? "Follow truck" : "Free pan"}`;
        }
    }

    if (elements.ets2MapCenter) {
        elements.ets2MapCenter.disabled = !tileMapState.initialized || tileMapState.followTruck;
    }
}

function gameCoordsToTilePixels(x, z, normalizedConfig) {
    if (!normalizedConfig) {
        return null;
    }

    if (normalizedConfig.bounds) {
        const pixelX = clamp01((x - normalizedConfig.bounds.x1) / (normalizedConfig.bounds.x2 - normalizedConfig.bounds.x1)) * normalizedConfig.map.maxX;
        const pixelY = clamp01((z - normalizedConfig.bounds.y1) / (normalizedConfig.bounds.y2 - normalizedConfig.bounds.y1)) * normalizedConfig.map.maxY;
        return { pixelX, pixelY };
    }

    const pixelX = (x / normalizedConfig.transposition.x.factor) + normalizedConfig.transposition.x.offset;
    const pixelY = normalizedConfig.map.maxY - ((z / normalizedConfig.transposition.y.factor) + normalizedConfig.transposition.y.offset);

    return { pixelX, pixelY };
}

function getTileMapResolution(zoom) {
    return 2 ** (tileMapState.nativeMaxZoom - zoom);
}

function clampTileMapCenterForViewport(centerX, centerY, viewportWidth, viewportHeight, zoom) {
    if (!tileMapState.config) {
        return null;
    }

    const resolution = getTileMapResolution(zoom);
    const halfWorldWidth = (viewportWidth * resolution) / 2;
    const halfWorldHeight = (viewportHeight * resolution) / 2;
    const clampedX = Math.max(halfWorldWidth, Math.min(centerX, tileMapState.config.map.maxX - halfWorldWidth));
    const clampedY = Math.max(halfWorldHeight, Math.min(centerY, tileMapState.config.map.maxY - halfWorldHeight));

    return { centerX: clampedX, centerY: clampedY };
}

function clampTileMapCenter(centerX, centerY) {
    if (!tileMapState.config || !elements.ets2MapStage) {
        return null;
    }

    const viewportWidth = Math.max(1, elements.ets2MapStage.clientWidth);
    const viewportHeight = Math.max(1, elements.ets2MapStage.clientHeight);
    return clampTileMapCenterForViewport(centerX, centerY, viewportWidth, viewportHeight, tileMapState.zoom);
}

function getTileUrl(zoom, x, y) {
    return buildTileUrl(tileMapState.baseUrl, tileMapState.tileTemplate, zoom, x, y);
}

function renderTileMap(centerX, centerY) {
    if (!tileMapState.initialized || !elements.ets2MapStage || !elements.ets2MapTiles || !tileMapState.config) {
        return null;
    }

    const viewportWidth = Math.max(1, elements.ets2MapStage.clientWidth);
    const viewportHeight = Math.max(1, elements.ets2MapStage.clientHeight);
    const requestedZoom = tileMapState.zoom;
    const fetchZoom = Math.min(requestedZoom, tileMapState.availableTileMaxZoom);
    const resolution = getTileMapResolution(requestedZoom);
    const fetchResolution = getTileMapResolution(fetchZoom);
    const tileSize = tileMapState.config.map.tileSize;
    const tileWorldSize = tileSize * fetchResolution;
    const maxFetchTileIndex = Math.max(0, (2 ** fetchZoom) - 1);
    const viewWorldWidth = viewportWidth * resolution;
    const viewWorldHeight = viewportHeight * resolution;
    const maxLeft = Math.max(0, tileMapState.config.map.maxX - viewWorldWidth);
    const maxTop = Math.max(0, tileMapState.config.map.maxY - viewWorldHeight);
    const clampedCenter = clampTileMapCenter(centerX, centerY) || { centerX, centerY };
    const viewLeft = Math.max(0, Math.min(clampedCenter.centerX - (viewWorldWidth / 2), maxLeft));
    const viewTop = Math.max(0, Math.min(clampedCenter.centerY - (viewWorldHeight / 2), maxTop));
    const startTileX = Math.max(0, Math.floor(viewLeft / tileWorldSize));
    const startTileY = Math.max(0, Math.floor(viewTop / tileWorldSize));
    const endTileX = Math.min(maxFetchTileIndex, Math.floor((viewLeft + viewWorldWidth) / tileWorldSize));
    const endTileY = Math.min(maxFetchTileIndex, Math.floor((viewTop + viewWorldHeight) / tileWorldSize));
    const tiles = [];

    for (let tileX = startTileX; tileX <= endTileX; tileX += 1) {
        for (let tileY = startTileY; tileY <= endTileY; tileY += 1) {
            const screenLeft = (tileX * tileWorldSize - viewLeft) / resolution;
            const screenTop = (tileY * tileWorldSize - viewTop) / resolution;

            tiles.push(`
                <img
                    class="ets2-map-tile"
                    src="${escapeHtml(getTileUrl(fetchZoom, tileX, tileY))}"
                    alt=""
                    loading="lazy"
                    style="left:${screenLeft}px;top:${screenTop}px;width:${tileWorldSize / resolution}px;height:${tileWorldSize / resolution}px;"
                >
            `);
        }
    }

    elements.ets2MapTiles.innerHTML = tiles.join("");
    if (elements.ets2MapFallback) {
        elements.ets2MapFallback.classList.remove("is-visible");
    }

    return {
        centerX: clampedCenter.centerX,
        centerY: clampedCenter.centerY,
        viewLeft,
        viewTop,
        resolution,
    };
}

function renderHeroTileMap(centerX, centerY, markerHeadingDeg) {
    if (!tileMapState.initialized || !tileMapState.config || !elements.heroMapStage || !elements.heroMapTiles || !elements.heroMapMarker) {
        if (elements.heroMapFallback) {
            elements.heroMapFallback.classList.add("is-visible");
        }
        return;
    }

    const viewportWidth = Math.max(1, elements.heroMapStage.clientWidth);
    const viewportHeight = Math.max(1, elements.heroMapStage.clientHeight);
    const targetCenter = heroMapState.followTruck || !heroMapState.manualCenter
        ? { centerX, centerY }
        : heroMapState.manualCenter;
    const requestedZoom = Math.max(heroMapState.minZoom, Math.min(heroMapState.maxZoom, heroMapState.zoom));
    const fetchZoom = Math.min(requestedZoom, tileMapState.availableTileMaxZoom);
    const resolution = getTileMapResolution(requestedZoom);
    const fetchResolution = getTileMapResolution(fetchZoom);
    const tileSize = tileMapState.config.map.tileSize;
    const tileWorldSize = tileSize * fetchResolution;
    const maxFetchTileIndex = Math.max(0, (2 ** fetchZoom) - 1);
    const viewWorldWidth = viewportWidth * resolution;
    const viewWorldHeight = viewportHeight * resolution;
    const maxLeft = Math.max(0, tileMapState.config.map.maxX - viewWorldWidth);
    const maxTop = Math.max(0, tileMapState.config.map.maxY - viewWorldHeight);
    const clampedCenter = clampTileMapCenterForViewport(targetCenter.centerX, targetCenter.centerY, viewportWidth, viewportHeight, requestedZoom)
        || targetCenter;
    const viewLeft = Math.max(0, Math.min(clampedCenter.centerX - (viewWorldWidth / 2), maxLeft));
    const viewTop = Math.max(0, Math.min(clampedCenter.centerY - (viewWorldHeight / 2), maxTop));
    const startTileX = Math.max(0, Math.floor(viewLeft / tileWorldSize));
    const startTileY = Math.max(0, Math.floor(viewTop / tileWorldSize));
    const endTileX = Math.min(maxFetchTileIndex, Math.floor((viewLeft + viewWorldWidth) / tileWorldSize));
    const endTileY = Math.min(maxFetchTileIndex, Math.floor((viewTop + viewWorldHeight) / tileWorldSize));
    const tiles = [];

    for (let tileX = startTileX; tileX <= endTileX; tileX += 1) {
        for (let tileY = startTileY; tileY <= endTileY; tileY += 1) {
            const screenLeft = (tileX * tileWorldSize - viewLeft) / resolution;
            const screenTop = (tileY * tileWorldSize - viewTop) / resolution;

            tiles.push(`
                <img
                    class="hero-map-tile"
                    src="${escapeHtml(getTileUrl(fetchZoom, tileX, tileY))}"
                    alt=""
                    loading="lazy"
                    style="left:${screenLeft}px;top:${screenTop}px;width:${tileWorldSize / resolution}px;height:${tileWorldSize / resolution}px;"
                >
            `);
        }
    }

    elements.heroMapTiles.innerHTML = tiles.join("");
    if (elements.heroMapFallback) {
        elements.heroMapFallback.classList.remove("is-visible");
    }

    heroMapState.lastView = {
        centerX: clampedCenter.centerX,
        centerY: clampedCenter.centerY,
        viewLeft,
        viewTop,
        resolution,
    };
    if (!heroMapState.followTruck) {
        heroMapState.manualCenter = { centerX: clampedCenter.centerX, centerY: clampedCenter.centerY };
    }

    const markerLeft = (centerX - viewLeft) / resolution;
    const markerTop = (centerY - viewTop) / resolution;
    elements.heroMapMarker.style.left = `${markerLeft}px`;
    elements.heroMapMarker.style.top = `${markerTop}px`;
    elements.heroMapMarker.style.setProperty("--hero-map-marker-heading", `${markerHeadingDeg}deg`);
}

function setHeroMapFollowTruck(shouldFollow, persistPreference = true) {
    heroMapState.followTruck = shouldFollow;
    if (shouldFollow) {
        heroMapState.manualCenter = null;
    }

    if (elements.heroMapCenter) {
        elements.heroMapCenter.disabled = !tileMapState.initialized || heroMapState.followTruck;
    }

    if (persistPreference) {
        persistMapPreferences();
    }
}

function handleHeroMapPointerDown(event) {
    if (!tileMapState.initialized || !heroMapState.lastView || !elements.heroMapStage) {
        return;
    }

    setActiveMapTarget("hero");
    heroMapState.drag.active = true;
    heroMapState.drag.pointerId = event.pointerId;
    heroMapState.drag.startClientX = event.clientX;
    heroMapState.drag.startClientY = event.clientY;
    heroMapState.drag.startCenterX = heroMapState.lastView.centerX;
    heroMapState.drag.startCenterY = heroMapState.lastView.centerY;
    elements.heroMapStage.dataset.dragging = "true";
    elements.heroMapStage.setPointerCapture(event.pointerId);
    setHeroMapFollowTruck(false);
}

function handleHeroMapPointerMove(event) {
    if (!heroMapState.drag.active || heroMapState.drag.pointerId !== event.pointerId || !elements.heroMapStage) {
        return;
    }

    const resolution = getTileMapResolution(heroMapState.zoom);
    const deltaX = event.clientX - heroMapState.drag.startClientX;
    const deltaY = event.clientY - heroMapState.drag.startClientY;
    const viewportWidth = Math.max(1, elements.heroMapStage.clientWidth);
    const viewportHeight = Math.max(1, elements.heroMapStage.clientHeight);
    const nextCenter = clampTileMapCenterForViewport(
        heroMapState.drag.startCenterX - (deltaX * resolution),
        heroMapState.drag.startCenterY - (deltaY * resolution),
        viewportWidth,
        viewportHeight,
        heroMapState.zoom,
    );

    if (!nextCenter) {
        return;
    }

    heroMapState.manualCenter = nextCenter;
    rerenderMapsFromLatestData();
}

function handleHeroMapPointerEnd(event) {
    if (!heroMapState.drag.active || heroMapState.drag.pointerId !== event.pointerId) {
        return;
    }

    if (elements.heroMapStage?.hasPointerCapture(event.pointerId)) {
        elements.heroMapStage.releasePointerCapture(event.pointerId);
    }

    heroMapState.drag.active = false;
    heroMapState.drag.pointerId = null;
    if (elements.heroMapStage) {
        elements.heroMapStage.dataset.dragging = "false";
    }
}

function setMapFollowTruck(shouldFollow, persistPreference = true) {
    tileMapState.followTruck = shouldFollow;
    if (shouldFollow) {
        tileMapState.manualCenter = null;
    } else if (!tileMapState.manualCenter && tileMapState.currentTruckPixel) {
        tileMapState.manualCenter = {
            centerX: tileMapState.currentTruckPixel.pixelX,
            centerY: tileMapState.currentTruckPixel.pixelY,
        };
    }

    updateMapModeLabel();

    if (persistPreference) {
        persistMapPreferences();
    }
}

function applyWorldMapZoom(delta) {
    if (!tileMapState.initialized) {
        return;
    }

    const nextZoom = Math.max(tileMapState.minZoom, Math.min(tileMapState.maxZoom, tileMapState.zoom + delta));
    if (nextZoom === tileMapState.zoom) {
        return;
    }

    tileMapState.zoom = nextZoom;
    persistMapPreferences();
    rerenderMapsFromLatestData();
}

function applyHeroMapZoom(delta) {
    if (!tileMapState.initialized) {
        return;
    }

    const nextZoom = Math.max(heroMapState.minZoom, Math.min(heroMapState.maxZoom, heroMapState.zoom + delta));
    if (nextZoom === heroMapState.zoom) {
        return;
    }

    heroMapState.zoom = nextZoom;
    persistMapPreferences();
    rerenderMapsFromLatestData();
}

function handleMapPointerDown(event) {
    if (!tileMapState.initialized || !tileMapState.lastView || !elements.ets2MapStage) {
        return;
    }

    setActiveMapTarget("world");
    tileMapState.drag.active = true;
    tileMapState.drag.pointerId = event.pointerId;
    tileMapState.drag.startClientX = event.clientX;
    tileMapState.drag.startClientY = event.clientY;
    tileMapState.drag.startCenterX = tileMapState.lastView.centerX;
    tileMapState.drag.startCenterY = tileMapState.lastView.centerY;
    elements.ets2MapStage.dataset.dragging = "true";
    elements.ets2MapStage.setPointerCapture(event.pointerId);
    setMapFollowTruck(false);
}

function handleMapPointerMove(event) {
    if (!tileMapState.drag.active || tileMapState.drag.pointerId !== event.pointerId) {
        return;
    }

    const resolution = getTileMapResolution(tileMapState.zoom);
    const deltaX = event.clientX - tileMapState.drag.startClientX;
    const deltaY = event.clientY - tileMapState.drag.startClientY;
    const nextCenter = clampTileMapCenter(
        tileMapState.drag.startCenterX - (deltaX * resolution),
        tileMapState.drag.startCenterY - (deltaY * resolution),
    );

    if (!nextCenter) {
        return;
    }

    tileMapState.manualCenter = nextCenter;
    rerenderMapsFromLatestData();
}

function handleMapPointerEnd(event) {
    if (!tileMapState.drag.active || tileMapState.drag.pointerId !== event.pointerId) {
        return;
    }

    if (elements.ets2MapStage?.hasPointerCapture(event.pointerId)) {
        elements.ets2MapStage.releasePointerCapture(event.pointerId);
    }

    tileMapState.drag.active = false;
    tileMapState.drag.pointerId = null;
    if (elements.ets2MapStage) {
        elements.ets2MapStage.dataset.dragging = "false";
    }
}

function setConnectionState(message, state = "pending") {
    if (!elements.connectionStatus) {
        return;
    }

    elements.connectionStatus.textContent = message;
    elements.connectionStatus.dataset.state = state;
}

function setActiveTab(tabName) {
    const panelExists = tabPanels.some((panel) => panel.dataset.tabPanel === tabName);
    if (!panelExists) {
        return;
    }

    tabButtons.forEach((button) => {
        const isActive = button.dataset.tab === tabName;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-pressed", isActive ? "true" : "false");
        button.setAttribute("aria-selected", isActive ? "true" : "false");
        button.setAttribute("tabindex", isActive ? "0" : "-1");
    });

    tabPanels.forEach((panel) => {
        const isActive = panel.dataset.tabPanel === tabName;
        panel.classList.toggle("is-active", isActive);
        panel.hidden = !isActive;
    });

    try {
        window.localStorage.setItem(activeTabStorageKey, tabName);
    } catch (error) {
        // Ignore storage failures so the dashboard keeps working in restricted contexts.
    }
}

function activateTabFromControl(control) {
    if (!(control instanceof HTMLElement)) {
        return;
    }

    const tabName = control.dataset.tab;
    if (!tabName) {
        return;
    }

    setActiveTab(tabName);
}

function truckLimitNote(truck = {}) {
    return truck.cruiseControlOn
        ? `Cruise ${formatTruckSpeed(truck.cruiseControlSpeed)} km/h`
        : "Cruise control inactive";
}

function renderHero(data) {
    const truck = data.truck || {};
    const game = data.game || {};
    const navigation = data.navigation || {};
    const gameplay = data.gameplay || {};

    const truckName = [truck.make, truck.model].filter(Boolean).join(" ");
    const speedKph = getNumber(truck.speed) === null ? 0 : Math.max(0, Number(truck.speed));
    const progress = Math.min(speedKph / speedRingMaxDisplayKph, 1) * 100;
    const roadLimitKph = normalizeRoadLimitKph(navigation.speedLimit);
    const isOverspeed = roadLimitKph !== null && speedKph > (roadLimitKph + speedRingOverspeedToleranceKph);
    const speedDelta = speedRingPreviousKph === null ? 0 : speedKph - speedRingPreviousKph;
    const trendState = speedDelta > speedRingTrendSensitivityKph
        ? "rising"
        : speedDelta < -speedRingTrendSensitivityKph
            ? "falling"
            : "steady";
    speedRingPreviousKph = speedKph;
    speedRingPeakKph = Math.max(speedRingPeakKph, speedKph);
    const plate = truck.licensePlate || "No plate";
    const gameClock = formatGameClock(game.time);

    if (elements.heroTitle) {
        elements.heroTitle.textContent = truckName || "Truck standing by";
    }

    if (elements.heroSummary) {
        elements.heroSummary.textContent = [
            plate,
            game.connected ? `Game clock ${gameClock}` : "Game disconnected",
            gameplay.onJob ? "Delivery active" : "Free drive",
            game.paused ? "Simulation paused" : "Simulation live",
        ].join(" • ");
    }

    if (elements.heroSpeedValue) {
        elements.heroSpeedValue.textContent = formatTruckSpeed(truck.speed);
        if (elements.roadSpeedLimit) {
            elements.roadSpeedLimit.textContent = roadLimitKph === null
                ? "--"
                : `${formatRoadSpeed(roadLimitKph)}`;
        }
        if (elements.cruiseControlLimit) {
            elements.cruiseControlLimit.textContent = truck.cruiseControlOn ? `${formatTruckSpeed(truck.cruiseControlSpeed)}` : "--";
        }
    }

    if (elements.speedPeak) {
        elements.speedPeak.textContent = `Peak ${formatNumber(speedRingPeakKph, speedRingPeakKph < 10 ? 1 : 0)} km/h`;
    }

    if (elements.speedTrend) {
        const trendLabel = trendState === "rising"
            ? `Rising +${formatNumber(Math.abs(speedDelta), 1)}`
            : trendState === "falling"
                ? `Falling -${formatNumber(Math.abs(speedDelta), 1)}`
                : "Steady";
        elements.speedTrend.textContent = trendLabel;
        elements.speedTrend.dataset.trend = trendState;
    }

    if (elements.speedAlert) {
        elements.speedAlert.textContent = isOverspeed
            ? `Overspeed +${formatNumber(Math.max(0, speedKph - (roadLimitKph ?? speedKph)), 1)} km/h`
            : "";
        elements.speedAlert.dataset.active = isOverspeed ? "true" : "false";
    }

    if (elements.speedLimitMarker) {
        if (roadLimitKph === null) {
            elements.speedLimitMarker.style.opacity = "0";
        } else {
            const limitProgress = Math.min(Math.max(roadLimitKph / speedRingMaxDisplayKph, 0), 1);
            const angleDeg = (limitProgress * 360) - 90;
            elements.speedLimitMarker.style.opacity = "1";
            elements.speedLimitMarker.style.setProperty("--speed-limit-angle", `${angleDeg}deg`);
        }
    }

    if (elements.speedRing) {
        const engineRunning = Boolean(truck.engineOn);

        elements.speedRing.style.setProperty("--speed-progress", `${progress}%`);
        elements.speedRing.dataset.engineState = engineRunning ? "on" : "off";
        elements.speedRing.dataset.overspeed = isOverspeed ? "true" : "false";
        elements.speedRing.dataset.trend = trendState;

        if (!engineRunning) {
            elements.speedRing.style.setProperty("--ring-color", "var(--ring-color-off)");
            elements.speedRing.style.setProperty("--ring-track-color", "rgba(255, 112, 80, 0.26)");
        } else {
            elements.speedRing.style.setProperty("--ring-color", "var(--teal)");
            elements.speedRing.style.setProperty("--ring-track-color", "rgba(255, 255, 255, 0.08)");
        }
    }

    if (elements.heroTags) {
        const heroTagItems = [];
        if (gameplay.onJob === true) {
            heroTagItems.push(createStatePill("On job", true));
        }
        if (truck.cruiseControlOn === true) {
            heroTagItems.push(createStatePill(`Cruise ${formatTruckSpeed(truck.cruiseControlSpeed)} km/h`, true));
        }

        elements.heroTags.innerHTML = heroTagItems.join("");
    }
}

function renderJobFinishedPopup(data) {
    if (!elements.jobFinishedPopup) {
        return;
    }

    const gameplay = data.gameplay || {};
    const job = data.job || {};
    const delivery = gameplay.jobDeliveredDetails || {};
    const jobFinished = Boolean(gameplay.jobFinished);
    const jobDelivered = Boolean(gameplay.jobDelivered);
    const deliverySignature = buildJobFinishedSignature(gameplay, job);
    const hasDeliveryDetails = deliverySignature !== "";
    if (elements.jobFinishedPopup.classList.contains("is-visible") && !jobFinishedPopupHydrated && hasDeliveryDetails) {
        jobFinishedPopupVisibleUntil = Date.now() + jobFinishedPopupDurationMs;
    }
    jobFinishedPopupHydrated = true;

    const hasFreshDeliveryEvent =
        (jobFinished && !previousJobFinishedState)
        || (jobDelivered && !previousJobDeliveredState)
        || (hasDeliveryDetails && deliverySignature !== previousJobFinishedSignature);

    if (hasFreshDeliveryEvent) {
        jobFinishedPopupVisibleUntil = Date.now() + jobFinishedPopupDurationMs;
    }
    previousJobFinishedState = jobFinished;
    previousJobDeliveredState = jobDelivered;
    if (hasDeliveryDetails) {
        previousJobFinishedSignature = deliverySignature;
    }

    const cargo = typeof job.cargo === "string" && job.cargo.trim() !== ""
        ? job.cargo.trim()
        : "Delivery completed";
    const routeParts = [job.sourceCity, job.destinationCity]
        .filter((value) => typeof value === "string" && value.trim() !== "")
        .map((value) => value.trim());
    const route = routeParts.length > 0 ? routeParts.join(" -> ") : "Route unavailable";
    const isVisible = hasDeliveryDetails && Date.now() < jobFinishedPopupVisibleUntil;

    if (elements.jobFinishedPopupBadge) {
        elements.jobFinishedPopupBadge.textContent = jobDelivered ? "Delivery complete" : "Job finished";
    }
    if (elements.jobFinishedPopupTitle) {
        elements.jobFinishedPopupTitle.textContent = cargo;
    }
    if (elements.jobFinishedPopupMeta) {
        elements.jobFinishedPopupMeta.textContent = route;
    }
    if (elements.jobFinishedPopupRevenue) {
        elements.jobFinishedPopupRevenue.textContent = formatIncomeLabel(delivery.revenue);
    }
    if (elements.jobFinishedPopupXp) {
        const xp = formatNumber(delivery.earnedXp, 0);
        elements.jobFinishedPopupXp.textContent = xp === "--" ? "XP --" : `XP ${xp}`;
    }
    if (elements.jobFinishedPopupDistance) {
        elements.jobFinishedPopupDistance.textContent = formatDistanceKm(delivery.distanceKm);
    }
    if (elements.jobFinishedPopupParking) {
        elements.jobFinishedPopupParking.textContent = delivery.autoParked ? "Auto parked" : "Manual parking";
    }

    elements.jobFinishedPopup.classList.toggle("is-visible", isVisible);
    elements.jobFinishedPopup.setAttribute("aria-hidden", isVisible ? "false" : "true");
}

function renderMetrics(data) {
    const truck = data.truck || {};
    const fuel = getNumber(truck.fuel);
    const fuelCapacity = getNumber(truck.fuelCapacity);
    const fuelRatio = fuel !== null && fuelCapacity ? fuel / fuelCapacity : null;

    if (elements.metricFuel) {
        elements.metricFuel.textContent = fuel !== null && fuelCapacity !== null
            ? `${formatLiters(fuel)} / ${formatLiters(fuelCapacity)}`
            : "--";
    }

    if (elements.metricFuelNote) {
        elements.metricFuelNote.textContent = fuelRatio === null
            ? "Fuel system unavailable"
            : `${formatPercent(fuelRatio, 0, true)} tank remaining`;
    }

    if (elements.metricRange) {
        elements.metricRange.textContent = formatDistanceKm(truck.fuelRange);
    }

    if (elements.fuelRange) {
        const rangeText = formatDistanceKm(truck.fuelRange);
        elements.fuelRange.textContent = rangeText === "--" ? "-- km range" : `${rangeText} range`;
    }

    if (elements.metricRangeNote) {
        elements.metricRangeNote.textContent = `Avg ${formatNumber(truck.fuelAverageConsumption, 2)} L/km`;
    }

    if (elements.metricRpm) {
        elements.metricRpm.textContent = formatRpm(truck.engineRpm);
    }

    if (elements.metricRpmNote) {
        elements.metricRpmNote.textContent = `Gear ${formatGear(truck)} • Max ${formatRpm(truck.engineRpmMax)}`;
    }

    if (elements.metricOdometer) {
        elements.metricOdometer.textContent = formatDistanceKm(truck.odometer);
    }

    if (elements.metricOdometerNote) {
        elements.metricOdometerNote.textContent = `${truck.make || "Truck"} • ${truck.model || "Unknown model"}`;
    }

}

function renderRoute(data) {
    const job = data.job || {};
    const navigation = data.navigation || {};
    const game = data.game || {};
    const gameplay = data.gameplay || {};
    const hasJob = Boolean(gameplay.onJob || job.sourceCity || job.destinationCity || job.cargo);
    const fromLocation = formatLocalizedRouteLocation(job.sourceCity, job.sourceCompany, "No active pickup");
    const toLocation = formatLocalizedRouteLocation(job.destinationCity, job.destinationCompany, "No destination");

    if (elements.routeBadge) {
        elements.routeBadge.textContent = hasJob ? "Active" : "Idle";
        elements.routeBadge.dataset.state = hasJob ? "active" : "idle";
    }

    if (elements.fromToValue) {
        elements.fromToValue.textContent = hasJob
            ? `${fromLocation} -> ${toLocation}`
            : "Not active";
    }

    if (elements.routeDistance) {
        elements.routeDistance.textContent = hasJob
            ? `${formatDistanceKm(getNavigationDistanceKm(navigation))} remaining`
            : "-- km";
    }

    if (elements.routeTime) {
        elements.routeTime.textContent = hasJob
            ? `ETA ${formatRouteEtaFromNavigation(navigation)}`
            : "ETA --:--";
    }

    if (elements.routeRealTime) {
        elements.routeRealTime.textContent = hasJob
            ? `REAL ${formatRouteRealTimeFromNavigation(navigation)}`
            : "REAL --:--";
    }

    if (elements.routeSource) {
        elements.routeSource.textContent = fromLocation;
    }

    if (elements.routeDestination) {
        elements.routeDestination.textContent = toLocation;
    }

    if (elements.routeStats) {
        elements.routeStats.innerHTML = [
            createStatCard("Cargo", job.cargo || "No cargo", job.jobMarket || "Market unavailable"),
            createStatCard("Planned distance", formatDistanceKm(job.plannedDistanceKm), `Remaining nav ${formatDistanceKm(getNavigationDistanceKm(navigation))}`),
            createStatCard("Speed limit", navigation.speedLimit ? `${formatRoadSpeed(navigation.speedLimit)} km/h` : "--", truckLimitNote(data.truck)),
            createStatCard("Deadline", formatGameEventTime(job.deadlineTime), formatGameEventTime(job.remainingTime)),
        ].join("");
    }
}

function renderTruckProfile(data) {
    const truck = data.truck || {};

    if (elements.truckStats) {
        elements.truckStats.innerHTML = [
            createStatCard("Truck ID", truck.id || "--", `${truck.make || "Unknown make"} ${truck.model || ""}`.trim() || "Unknown truck"),
            createStatCard("Plate", truck.licensePlate || "--", truck.licensePlateCountry || "No country"),
            createStatCard("Shifter", truck.shifterType || "--", `Slot ${formatNumber(truck.shifterSlot, 0)}`),
            createStatCard("Gearbox", `${formatNumber(truck.forwardGears, 0)}F / ${formatNumber(truck.reverseGears, 0)}R`, `Displayed gear ${formatGear(truck)}`),
        ].join("");
    }
}

function renderSystems(data) {
    const truck = data.truck || {};

    if (elements.systemsPills) {
        elements.systemsPills.innerHTML = [
            createStatePill(boolLabel(truck.engineOn, "Engine on", "Engine off"), truck.engineOn),
            createStatePill(boolLabel(truck.electricOn, "Electrics live", "Electrics down"), truck.electricOn),
            createStatePill(boolLabel(truck.oilPressureWarningOn, "Oil warning", "Oil stable"), !truck.oilPressureWarningOn),
            createStatePill(boolLabel(truck.batteryVoltageWarningOn, "Battery warning", "Battery stable"), !truck.batteryVoltageWarningOn),
            createStatePill(boolLabel(truck.airPressureWarningOn, "Air warning", "Air stable"), !truck.airPressureWarningOn),
            createStatePill(boolLabel(truck.waterTemperatureWarningOn, "Heat warning", "Cooling stable"), !truck.waterTemperatureWarningOn),
        ].join("");
    }

    if (elements.systemsGauges) {
        const fuelRatio = truck.fuelCapacity ? (Number(truck.fuel) / Number(truck.fuelCapacity)) * 100 : 0;
        const adblueRatio = truck.adblueCapacity ? (Number(truck.adblue) / Number(truck.adblueCapacity)) * 100 : 0;
        const batteryRatio = truck.batteryVoltageWarningValue ? (Number(truck.batteryVoltage) / Number(truck.batteryVoltageWarningValue)) * 100 : 0;
        const airRatio = truck.airPressureWarningValue ? (Number(truck.airPressure) / Number(truck.airPressureWarningValue)) * 100 : 0;

        elements.systemsGauges.innerHTML = [
            createGauge("Fuel reserve", fuelRatio, `${formatLiters(truck.fuel)} left`, "teal"),
            createGauge("AdBlue", adblueRatio, `${formatLiters(truck.adblue)} available`, "blue"),
            createGauge("Battery", batteryRatio, formatVoltage(truck.batteryVoltage), "rose"),
            createGauge("Air pressure", airRatio, formatPressure(truck.airPressure), "amber"),
            createGauge("Oil temperature", clampProgress((Number(truck.oilTemperature) / 140) * 100), formatTemperature(truck.oilTemperature), "orange"),
            createGauge("Water temperature", clampProgress((Number(truck.waterTemperature) / 120) * 100), formatTemperature(truck.waterTemperature), "sunset"),
        ].join("");
    }
}

function renderDrivetrain(data) {
    const truck = data.truck || {};

    if (elements.drivetrainStats) {
        elements.drivetrainStats.innerHTML = [
            createStatCard("Gear", formatGear(truck), `Real gear ${formatNumber(truck.gear, 0)}`),
            createStatCard("Retarder", `${formatNumber(truck.retarderBrake, 0)} / ${formatNumber(truck.retarderStepCount, 0)}`, "Brake step usage"),
            createStatCard("Cruise", truck.cruiseControlOn ? `${formatTruckSpeed(truck.cruiseControlSpeed)} km/h` : "--", boolLabel(truck.cruiseControlOn, "Holding speed", "Cruise inactive")),
            createStatCard("Brake temp", formatTemperature(truck.brakeTemperature), boolLabel(truck.parkBrakeOn, "Parking brake engaged", "Parking brake released")),
            createStatCard("Oil pressure", formatPressure(truck.oilPressure), truck.oilPressureWarningOn ? "Below safe threshold" : "Pressure healthy"),
            createStatCard("Motor brake", boolLabel(truck.motorBrakeOn, "Enabled", "Disabled"), boolLabel(truck.engineOn, "Engine running", "Engine off")),
        ].join("");
    }
}

function renderAlerts(data) {
    const truck = data.truck || {};
    const gameplay = data.gameplay || {};
    const game = data.game || {};
    const alerts = [];

    if (truck.oilPressureWarningOn) {
        alerts.push(createAlertItem("Oil pressure warning", `Threshold ${formatPressure(truck.oilPressureWarningValue)}`, "danger"));
    }
    if (truck.airPressureEmergencyOn) {
        alerts.push(createAlertItem("Air emergency", `Emergency value ${formatPressure(truck.airPressureEmergencyValue)}`, "danger"));
    }
    if (truck.batteryVoltageWarningOn) {
        alerts.push(createAlertItem("Battery warning", `Threshold ${formatVoltage(truck.batteryVoltageWarningValue)}`, "warning"));
    }
    if (truck.waterTemperatureWarningOn) {
        alerts.push(createAlertItem("Cooling warning", `Water threshold ${formatTemperature(truck.waterTemperatureWarningValue)}`, "warning"));
    }
    if (gameplay.fined) {
        alerts.push(createAlertItem("Fine issued", `${formatNumber(gameplay.finedDetails?.amount, 0)} • ${gameplay.finedDetails?.offence || "Unknown offence"}`, "warning"));
    }
    if (game.paused) {
        alerts.push(createAlertItem("Simulation paused", "Telemetry is live but the game is paused", "neutral"));
    }
    if (truck.parkBrakeOn) {
        alerts.push(createAlertItem("Parking brake engaged", "Truck is being held in place", "neutral"));
    }
    if (!truck.engineOn) {
        alerts.push(createAlertItem("Engine off", "Restart required for motion and powertrain response", "neutral"));
    }

    if (alerts.length === 0) {
        alerts.push(createAlertItem("No active warnings", "Truck systems look healthy right now", "good"));
    }

    if (elements.alertFeed) {
        elements.alertFeed.innerHTML = alerts.slice(0, 3).join("");
    }
}

function renderControls(data) {
    const truck = data.truck || {};
    const steer = getNumber(truck.userSteer) ?? 0;

    if (elements.controlsList) {
        elements.controlsList.innerHTML = [
            createInputRow("Throttle", Number(truck.userThrottle || 0) * 100, "Driver pedal pressure", "teal"),
            createInputRow("Brake", Number(truck.userBrake || 0) * 100, "Brake pedal input", "amber"),
            createInputRow("Clutch", Number(truck.userClutch || 0) * 100, "Clutch position", "rose"),
            createInputRow("Steering", Math.abs(steer) * 100, steer < 0 ? "Bias left" : steer > 0 ? "Bias right" : "Centered", "blue"),
            createInputRow("Game steer", Math.abs(Number(truck.gameSteer || 0)) * 100, "Steering after game assist", "blue"),
            createInputRow("Dashboard dim", Number(truck.lightsDashboardValue || 0) * 100, boolLabel(truck.lightsDashboardOn, "Cluster lit", "Cluster dark"), "sunset"),
        ].join("");
    }
}

function renderLighting(data) {
    const truck = data.truck || {};

    if (elements.lightGrid) {
        elements.lightGrid.innerHTML = [
            createSignalTile("Parking lights", !!truck.lightsParkingOn, "Marker lights"),
            createSignalTile("Low beam", !!truck.lightsBeamLowOn, "Main road lighting"),
            createSignalTile("High beam", !!truck.lightsBeamHighOn, "Long range light"),
            createSignalTile("Aux front", !!truck.lightsAuxFrontOn, "Bumper auxiliary"),
            createSignalTile("Aux roof", !!truck.lightsAuxRoofOn, "Roof auxiliary"),
            createSignalTile("Beacon", !!truck.lightsBeaconOn, "Hazard beacon"),
            createSignalTile("Brake lights", !!truck.lightsBrakeOn, "Rear brake signal"),
            createSignalTile("Reverse lights", !!truck.lightsReverseOn, "Rear reverse signal"),
            createSignalTile("Dash lighting", !!truck.lightsDashboardOn, "Instrument lighting"),
            createSignalTile("Left blinker", !!truck.blinkerLeftOn, boolLabel(truck.blinkerLeftActive, "Flash cycle active", "Not flashing")),
            createSignalTile("Right blinker", !!truck.blinkerRightOn, boolLabel(truck.blinkerRightActive, "Flash cycle active", "Not flashing")),
            createSignalTile("Wipers", !!truck.wipersOn, "Rain response"),
        ].join("");
    }
}

function renderTrailer(data) {
    const attachedTrailer = (data.trailers || []).find((trailer) => trailer && trailer.attached);

    if (elements.trailerBadge) {
        elements.trailerBadge.textContent = attachedTrailer ? "Attached" : "Detached";
        elements.trailerBadge.dataset.state = attachedTrailer ? "active" : "idle";
    }

    if (elements.trailerSummary) {
        if (!attachedTrailer) {
            elements.trailerSummary.innerHTML = `
                <div class="empty-state">
                    <strong>No trailer connected</strong>
                    <span>Hook up a trailer to surface trailer wear, placement, cargo damage, and unit identity here.</span>
                </div>
            `;
        } else {
            elements.trailerSummary.innerHTML = `
                <div class="stats-grid compact-grid">
                    ${createStatCard("Trailer", attachedTrailer.name || "Unnamed trailer", attachedTrailer.id || "No trailer id")}
                    ${createStatCard("Brand", attachedTrailer.brand || "Generic", attachedTrailer.brandId || "No brand id")}
                    ${createStatCard("Plate", attachedTrailer.licensePlate || "--", attachedTrailer.licensePlateCountry || "No country")}
                    ${createStatCard("Placement", `${formatCoordinate(attachedTrailer.placement?.x)}, ${formatCoordinate(attachedTrailer.placement?.z)}`, `Heading ${formatAngleDegrees(attachedTrailer.placement?.heading)}`)}
                </div>
            `;
        }
    }

    if (elements.trailerGauges) {
        if (!attachedTrailer) {
            elements.trailerGauges.innerHTML = "";
            return;
        }

        elements.trailerGauges.innerHTML = [
            createGauge("Chassis wear", Number(attachedTrailer.wearChassis || 0) * 100, "Frame condition", "rose"),
            createGauge("Wheel wear", Number(attachedTrailer.wearWheels || 0) * 100, "Tyres and hubs", "amber"),
            createGauge("Body wear", Number(attachedTrailer.wearBody || 0) * 100, "Shell condition", "orange"),
            createGauge("Cargo damage", Number(attachedTrailer.cargoDamage || 0) * 100, "Delivery integrity", "sunset"),
        ].join("");
    }
}

function renderWorld(data) {
    const truck = data.truck || {};
    const game = data.game || {};
    const navigation = data.navigation || {};

    if (elements.worldStats) {
        elements.worldStats.innerHTML = [
            createStatCard("Game clock", formatGameClock(game.time), `Time scale ${formatNumber(game.timeScale, 1)}`),
            createStatCard("Rest stop", formatGameEventTime(game.nextRestStopTime), "In-game rest planning"),
            createStatCard("Coordinates", `${formatCoordinate(truck.placement?.x)}, ${formatCoordinate(truck.placement?.z)}`, `Height ${formatCoordinate(truck.placement?.y)}`),
            createStatCard("Orientation", formatAngleDegrees(truck.placement?.heading), `Pitch ${formatAngleDegrees(truck.placement?.pitch)} • Roll ${formatAngleDegrees(truck.placement?.roll)}`),
            createStatCard("Acceleration", `${formatNumber(truck.acceleration?.x, 3)}, ${formatNumber(truck.acceleration?.z, 3)}`, `Vertical ${formatNumber(truck.acceleration?.y, 3)}`),
            createStatCard("Cabin offset", `${formatCoordinate(truck.cabin?.x)}, ${formatCoordinate(truck.cabin?.z)}`, `Head ${formatCoordinate(truck.head?.x)}, ${formatCoordinate(truck.head?.z)}`),
            createStatCard("Hook point", `${formatCoordinate(truck.hook?.x)}, ${formatCoordinate(truck.hook?.z)}`, `Y ${formatCoordinate(truck.hook?.y)}`),
            createStatCard("Navigation", formatDistanceKm(getNavigationDistanceKm(navigation)), navigation.speedLimit ? `Speed limit ${formatRoadSpeed(navigation.speedLimit)} km/h` : "No limit data"),
        ].join("");
    }
}

function renderMap(data) {
    const truck = data.truck || {};
    const job = data.job || {};
    const gameplay = data.gameplay || {};
    const hasWorldMapElements = Boolean(elements.ets2MapMarker && elements.ets2MapLabel && elements.mapMeta);
    const hasHeroMapElements = Boolean(elements.heroMapStage && elements.heroMapTiles && elements.heroMapMarker);
    const x = getNumber(truck.placement?.x);
    const z = getNumber(truck.placement?.z);
    const heading = getNumber(truck.placement?.heading) ?? 0;
    const hasPosition = x !== null && z !== null;
    const hasJob = Boolean(gameplay.onJob || job.cargo || job.sourceCity || job.destinationCity);
    const incomeText = hasJob ? `Income €${formatIncome(job.income)}` : "Income --";
    const cargoText = hasJob ? `Job ${job.cargo || "Unknown cargo"}` : "Job --";
    const weightText = hasJob ? `Weight ${formatMass(job.cargoMass)}` : "Weight --";

    if (elements.mapJobIncome) {
        elements.mapJobIncome.textContent = incomeText;
    }

    if (elements.mapJobCargo) {
        elements.mapJobCargo.textContent = cargoText;
    }

    if (elements.mapJobWeight) {
        elements.mapJobWeight.textContent = weightText;
    }

    if (elements.heroMapJobIncome) {
        elements.heroMapJobIncome.textContent = incomeText;
    }

    if (elements.heroMapJobCargo) {
        elements.heroMapJobCargo.textContent = cargoText;
    }

    if (elements.heroMapJobWeight) {
        elements.heroMapJobWeight.textContent = weightText;
    }

    if (elements.mapBadge) {
        elements.mapBadge.textContent = hasPosition ? (tileMapState.initialized ? "Tiles live" : "Tracking") : "Waiting";
        elements.mapBadge.dataset.state = hasPosition ? "active" : "idle";
    }

    if (!hasWorldMapElements && !hasHeroMapElements) {
        return;
    }

    if (!hasPosition) {
        tileMapState.currentTruckPixel = null;
        heroMapState.lastView = null;
        setHeroMapFollowTruck(true, false);
        if (elements.heroMapTiles) {
            elements.heroMapTiles.innerHTML = "";
        }
        if (elements.heroMapFallback) {
            elements.heroMapFallback.classList.add("is-visible");
        }
        if (elements.heroMapMarker) {
            elements.heroMapMarker.style.left = "50%";
            elements.heroMapMarker.style.top = "50%";
            elements.heroMapMarker.style.setProperty("--hero-map-marker-heading", "0deg");
        }
        if (hasWorldMapElements) {
            elements.ets2MapMarker.style.left = "50%";
            elements.ets2MapMarker.style.top = "50%";
            elements.ets2MapMarker.style.setProperty("--map-marker-heading", "0deg");
            elements.ets2MapLabel.textContent = "Awaiting truck position";
            if (elements.ets2MapFallback) {
                elements.ets2MapFallback.classList.add("is-visible");
            }
            if (elements.ets2MapTiles) {
                elements.ets2MapTiles.innerHTML = "";
            }
            elements.mapMeta.innerHTML = [
                createMetaPill("X", "--"),
                createMetaPill("Z", "--"),
                createMetaPill("Heading", "--"),
            ].join("");
        }
        return;
    }

    const xRatio = clamp01((x - ets2MapBounds.minX) / (ets2MapBounds.maxX - ets2MapBounds.minX));
    const zRatio = clamp01((ets2MapBounds.maxZ - z) / (ets2MapBounds.maxZ - ets2MapBounds.minZ));
    const markerHeadingDeg = getMarkerHeadingDegrees(heading, x, z);
    const labelParts = [truck.licensePlate || "Truck"];

    if (job.destinationCity) {
        labelParts.push(`to ${localizeCityName(job.destinationCity)}`);
    }

    if (tileMapState.initialized && tileMapState.config) {
        const mapPixels = gameCoordsToTilePixels(x, z, tileMapState.config);
        if (mapPixels) {
            tileMapState.currentTruckPixel = mapPixels;
            if (hasHeroMapElements) {
                renderHeroTileMap(mapPixels.pixelX, mapPixels.pixelY, markerHeadingDeg);
            }
            if (hasWorldMapElements && elements.ets2MapStage) {
                const targetCenter = tileMapState.followTruck || !tileMapState.manualCenter
                    ? { centerX: mapPixels.pixelX, centerY: mapPixels.pixelY }
                    : tileMapState.manualCenter;
                const tileView = renderTileMap(targetCenter.centerX, targetCenter.centerY);
                if (tileView) {
                    tileMapState.lastView = tileView;
                    if (!tileMapState.followTruck) {
                        tileMapState.manualCenter = { centerX: tileView.centerX, centerY: tileView.centerY };
                    }
                    const markerLeft = (mapPixels.pixelX - tileView.viewLeft) / tileView.resolution;
                    const markerTop = (mapPixels.pixelY - tileView.viewTop) / tileView.resolution;

                    elements.ets2MapMarker.style.left = `${markerLeft}px`;
                    elements.ets2MapMarker.style.top = `${markerTop}px`;
                } else {
                    elements.ets2MapMarker.style.left = `${xRatio * 100}%`;
                    elements.ets2MapMarker.style.top = `${zRatio * 100}%`;
                }
            }
        }
    }

    if (hasWorldMapElements) {
        if (!(tileMapState.initialized && tileMapState.config && elements.ets2MapStage)) {
            if (elements.ets2MapFallback) {
                elements.ets2MapFallback.classList.add("is-visible");
            }
            elements.ets2MapMarker.style.left = `${xRatio * 100}%`;
            elements.ets2MapMarker.style.top = `${zRatio * 100}%`;
        }
        elements.ets2MapMarker.style.setProperty("--map-marker-heading", `${markerHeadingDeg}deg`);
        elements.ets2MapLabel.textContent = labelParts.join(" ");
        elements.mapMeta.innerHTML = [
            createMetaPill("X", formatCoordinate(x)),
            createMetaPill("Z", formatCoordinate(z)),
            createMetaPill("Heading", formatAngleDegrees(heading)),
            createMetaPill("Map", tileMapState.initialized ? `z${tileMapState.zoom}` : "Preview"),
        ].join("");
    }

    if (!tileMapState.initialized && hasHeroMapElements) {
        if (elements.heroMapFallback) {
            elements.heroMapFallback.classList.add("is-visible");
        }
        elements.heroMapMarker.style.left = `${xRatio * 100}%`;
        elements.heroMapMarker.style.top = `${zRatio * 100}%`;
        elements.heroMapMarker.style.setProperty("--hero-map-marker-heading", `${markerHeadingDeg}deg`);
    }
}

function renderEvents(data) {
    const gameplay = data.gameplay || {};

    if (elements.eventStats) {
        elements.eventStats.innerHTML = [
            createStatCard("Last delivery", formatNumber(gameplay.jobDeliveredDetails?.revenue, 0), gameplay.jobDelivered ? "Just delivered" : `XP ${formatNumber(gameplay.jobDeliveredDetails?.earnedXp, 0)}`),
            createStatCard("Delivery time", formatDurationMinutes(gameplay.jobDeliveredDetails?.deliveryTime), gameplay.jobDeliveredDetails?.autoParked ? "Auto parked" : "Manual parking"),
            createStatCard("Tollgate", gameplay.tollgate ? `${formatNumber(gameplay.tollgateDetails?.payAmount, 0)} paid` : "Inactive", boolLabel(gameplay.tollgate, "Toll event active", "No toll event")),
            createStatCard("Refuel", formatLiters(gameplay.refuelDetails?.amount), gameplay.refuelPayed ? "Fuel payment registered" : "Awaiting payment"),
            createStatCard("Ferry", gameplay.ferry ? `${formatNumber(gameplay.ferryDetails?.payAmount, 0)} paid` : "Inactive", `${gameplay.ferryDetails?.sourceName || "--"} -> ${gameplay.ferryDetails?.targetName || "--"}`),
            createStatCard("Train", gameplay.train ? `${formatNumber(gameplay.trainDetails?.payAmount, 0)} paid` : "Inactive", `${gameplay.trainDetails?.sourceName || "--"} -> ${gameplay.trainDetails?.targetName || "--"}`),
        ].join("");
    }
}

function renderRawPayload(payload) {
    if (elements.telemetryOutput) {
        elements.telemetryOutput.textContent = JSON.stringify(payload.data ?? {}, null, 2);
    }
}

function renderTelemetry(payload) {
    const data = payload.data || {};
    latestTelemetryData = data;

    if (elements.refreshInterval) {
        elements.refreshInterval.textContent = `${payload.refreshIntervalMs ?? refreshIntervalMs} ms`;
    }

    if (elements.lastUpdated) {
        elements.lastUpdated.textContent = formatLocalTime(payload.fetchedAt);
    }

    renderHero(data);
    renderJobFinishedPopup(data);
    renderMetrics(data);
    renderRoute(data);
    renderTruckProfile(data);
    renderSystems(data);
    renderDrivetrain(data);
    renderAlerts(data);
    renderControls(data);
    renderLighting(data);
    renderTrailer(data);
    renderWorld(data);
    scheduleMapRender(data);
    renderEvents(data);
    renderRawPayload(payload);

    applyTelemetrySourceStatus(payload);
}

async function updateTelemetry() {
    if (telemetryRequestInFlight) {
        scheduleTelemetryUpdate(refreshIntervalMs);
        return;
    }

    telemetryRequestInFlight = true;

    try {
        telemetryAbortController = typeof AbortController === "function" ? new AbortController() : null;
        if (telemetryTimeoutHandle !== null) {
            window.clearTimeout(telemetryTimeoutHandle);
        }

        telemetryTimeoutHandle = window.setTimeout(() => {
            telemetryAbortController?.abort();
        }, telemetryRequestTimeoutMs);

        const response = await fetch(telemetryEndpoint, {
            headers: { Accept: "application/json" },
            cache: "no-store",
            signal: telemetryAbortController?.signal,
        });

        if (!response.ok) {
            throw new Error(`Telemetry request failed with status ${response.status}`);
        }

        const payload = await response.json();
        renderTelemetry(payload);
        scheduleTelemetryUpdate(getNextTelemetryDelayMs());
    } catch (error) {
        const isAbort = error && typeof error === "object" && error.name === "AbortError";
        telemetryConsecutiveFailures += 1;
        telemetryLastSourceType = "none";
        setConnectionState("Connection failed", "error");

        if (elements.lastUpdated) {
            elements.lastUpdated.textContent = "Update failed";
        }

        if (elements.heroSummary) {
            elements.heroSummary.textContent = isAbort
                ? "Telemetry request timed out before the endpoint responded."
                : "The dashboard could not fetch a fresh telemetry snapshot from the local endpoint.";
        }

        if (elements.telemetryOutput) {
            elements.telemetryOutput.textContent = error?.message || "Unknown telemetry error";
        }

        scheduleTelemetryUpdate(getNextTelemetryDelayMs());
    } finally {
        telemetryRequestInFlight = false;
        telemetryAbortController = null;
        if (telemetryTimeoutHandle !== null) {
            window.clearTimeout(telemetryTimeoutHandle);
            telemetryTimeoutHandle = null;
        }
    }
}

function startTelemetryPolling() {
    if (config.initialPayload) {
        renderTelemetry(config.initialPayload);
        telemetryLastSourceType = config.initialPayload?.source?.type || "upstream";
    }

    updateTelemetry();
}

if (tabsRoot) {
    tabsRoot.addEventListener("click", (event) => {
        const control = event.target instanceof Element ? event.target.closest("[data-tab]") : null;
        if (!control) {
            return;
        }

        activateTabFromControl(control);
    });

    tabsRoot.addEventListener("pointerup", (event) => {
        const control = event.target instanceof Element ? event.target.closest("[data-tab]") : null;
        if (!control) {
            return;
        }

        activateTabFromControl(control);
    });

    tabsRoot.addEventListener("keydown", (event) => {
        const control = event.target instanceof Element ? event.target.closest("[data-tab]") : null;
        if (!control) {
            return;
        }

        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            activateTabFromControl(control);
        }
    });
}

mapZoomButtons.forEach((button) => {
    button.addEventListener("click", () => {
        setActiveMapTarget("world");
        const delta = button.dataset.mapZoom === "in" ? 1 : -1;
        applyWorldMapZoom(delta);
    });
});

heroMapZoomButtons.forEach((button) => {
    button.addEventListener("click", () => {
        setActiveMapTarget("hero");
        const delta = button.dataset.heroMapZoom === "in" ? 1 : -1;
        applyHeroMapZoom(delta);
    });
});

if (elements.ets2MapStage) {
    elements.ets2MapStage.addEventListener("pointerdown", handleMapPointerDown);
    elements.ets2MapStage.addEventListener("pointermove", handleMapPointerMove);
    elements.ets2MapStage.addEventListener("pointerup", handleMapPointerEnd);
    elements.ets2MapStage.addEventListener("pointercancel", handleMapPointerEnd);
    elements.ets2MapStage.addEventListener("focusin", () => {
        setActiveMapTarget("world");
    });
    elements.ets2MapStage.addEventListener("wheel", (event) => {
        if (!tileMapState.initialized) {
            return;
        }

        setActiveMapTarget("world");
        event.preventDefault();
        applyWorldMapZoom(event.deltaY < 0 ? 1 : -1);
    }, { passive: false });
}

if (elements.heroMapStage) {
    elements.heroMapStage.addEventListener("pointerdown", handleHeroMapPointerDown);
    elements.heroMapStage.addEventListener("pointermove", handleHeroMapPointerMove);
    elements.heroMapStage.addEventListener("pointerup", handleHeroMapPointerEnd);
    elements.heroMapStage.addEventListener("pointercancel", handleHeroMapPointerEnd);
    elements.heroMapStage.addEventListener("focusin", () => {
        setActiveMapTarget("hero");
    });
    elements.heroMapStage.addEventListener("wheel", (event) => {
        if (!tileMapState.initialized) {
            return;
        }

        setActiveMapTarget("hero");
        event.preventDefault();
        applyHeroMapZoom(event.deltaY < 0 ? 1 : -1);
    }, { passive: false });
}

if (elements.ets2MapCenter) {
    elements.ets2MapCenter.addEventListener("click", () => {
        setActiveMapTarget("world");
        centerWorldMap();
    });
}

if (elements.heroMapCenter) {
    elements.heroMapCenter.addEventListener("click", () => {
        setActiveMapTarget("hero");
        centerHeroMap(true);
    });
}

window.addEventListener("keydown", handleGlobalMapShortcuts);

window.addEventListener("beforeunload", () => {
    if (refreshTimer !== null) {
        window.clearTimeout(refreshTimer);
    }

    if (tileMapRetryTimer !== null) {
        window.clearTimeout(tileMapRetryTimer);
    }

    if (telemetryTimeoutHandle !== null) {
        window.clearTimeout(telemetryTimeoutHandle);
    }

    if (mapRenderFrameHandle !== null) {
        if (mapRenderUsesAnimationFrame && typeof window.cancelAnimationFrame === "function") {
            window.cancelAnimationFrame(mapRenderFrameHandle);
        } else {
            window.clearTimeout(mapRenderFrameHandle);
        }
    }

    telemetryAbortController?.abort();
});

if (typeof document !== "undefined") {
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
            scheduleTelemetryUpdate(refreshIntervalMs);
            return;
        }

        scheduleTelemetryUpdate(getNextTelemetryDelayMs());
    });
}

try {
    const storedTab = window.localStorage.getItem(activeTabStorageKey);
    if (storedTab) {
        setActiveTab(storedTab);
    } else {
        setActiveTab("overview");
    }
} catch (error) {
    // Ignore storage failures so the default tab stays available.
    setActiveTab("overview");
}

loadMapPreferences();
loadCityLocalizations();

startTelemetryPolling();

updateMapModeLabel();
initializeTileMap();
updateMapInteractionHints();
