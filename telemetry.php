<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

define("TELEMETRY_URL", (string) dashboard_config_value("telemetry.upstreamUrl", "http://127.0.0.1:31377/api/ets2/telemetry"));
define("TELEMETRY_REFRESH_INTERVAL_MS", (int) dashboard_config_value("telemetry.refreshIntervalMs", 250));
define("AREA_URL", "https://tracker.ets2map.com/v3/area");

function telemetry_http_status_code(array $headers): ?int
{
    if (!isset($headers[0]) || !is_string($headers[0])) {
        return null;
    }

    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headers[0], $matches) === 1) {
        return (int) $matches[1];
    }

    return null;
}

function telemetry_cache_read(string $cachePath, int $cacheTtlMs): ?array
{
    if (!is_file($cachePath)) {
        return null;
    }

    $raw = @file_get_contents($cachePath);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $cachedAtMs = (int) ($decoded['cachedAtMs'] ?? 0);
    $nowMs = (int) floor(microtime(true) * 1000);
    if ($cachedAtMs <= 0 || ($nowMs - $cachedAtMs) > max(0, $cacheTtlMs)) {
        return null;
    }

    $data = $decoded['data'] ?? null;
    if (!is_array($data)) {
        return null;
    }

    return [
        'cachedAtMs' => $cachedAtMs,
        'data' => $data,
    ];
}

function telemetry_cache_write(string $cachePath, array $data, ?int $cachedAtMs = null): int
{
    $cacheDir = dirname($cachePath);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
        return (int) ($cachedAtMs ?? floor(microtime(true) * 1000));
    }

    $effectiveCachedAtMs = (int) ($cachedAtMs ?? floor(microtime(true) * 1000));
    $payload = [
        'cachedAtMs' => $effectiveCachedAtMs,
        'data' => $data,
    ];

    @file_put_contents($cachePath, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $effectiveCachedAtMs;
}

function telemetry_snapshot_read_state(string $statePath): array
{
    if (!is_file($statePath)) {
        return [];
    }

    $raw = @file_get_contents($statePath);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function telemetry_snapshot_write_state(string $statePath, array $state): void
{
    $stateDir = dirname($statePath);
    if (!is_dir($stateDir)) {
        @mkdir($stateDir, 0777, true);
    }

    if (!is_dir($stateDir) || !is_writable($stateDir)) {
        return;
    }

    @file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function telemetry_snapshot_build_filename(int $timestampMs): string
{
    $seconds = sprintf('%.6F', $timestampMs / 1000);
    $date = \DateTimeImmutable::createFromFormat('U.u', $seconds, new \DateTimeZone('UTC'));
    if (!$date instanceof \DateTimeImmutable) {
        return 'telemetry-' . gmdate('Y-m-d\TH-i-s\Z') . '.json';
    }

    $prefix = trim((string) dashboard_config_value('snapshots.filenamePrefix', 'telemetry-'));
    $pattern = trim((string) dashboard_config_value('snapshots.filenamePattern', '{prefix}{date}-{ms}Z.{ext}'));
    $timestampFormat = trim((string) dashboard_config_value('snapshots.timestampFormat', 'Y-m-d\TH-i-s'));

    if ($pattern === '') {
        $pattern = '{prefix}{date}-{ms}Z.{ext}';
    }

    if ($timestampFormat === '') {
        $timestampFormat = 'Y-m-d\TH-i-s';
    }

    $filename = strtr($pattern, [
        '{prefix}' => $prefix,
        '{date}' => $date->format($timestampFormat),
        '{ms}' => str_pad((string) ($timestampMs % 1000), 3, '0', STR_PAD_LEFT),
        '{ext}' => 'json',
    ]);
    $sanitized = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/', '-', $filename);
    $sanitized = is_string($sanitized) ? trim($sanitized, ". \t\n\r\0\x0B") : '';

    return $sanitized !== '' ? $sanitized : sprintf(
        'telemetry-%s-%03dZ.json',
        $date->format('Y-m-d\TH-i-s'),
        $timestampMs % 1000
    );
}

function telemetry_snapshot_capture_if_due(array $data, array $source): void
{
    if (!(bool) dashboard_config_value('snapshots.enabled', false)) {
        return;
    }

    if (!in_array((string) ($source['type'] ?? 'none'), ['upstream', 'cache'], true)) {
        return;
    }

    $intervalMs = max(1000, (int) dashboard_config_value('snapshots.intervalMs', 60000));
    $snapshotDir = (string) dashboard_config_value('snapshots.directory', __DIR__ . '/snapshots');
    $statePath = (string) dashboard_config_value('snapshots.stateFile', __DIR__ . '/tmp/snapshot-state.json');
    $prettyPrint = (bool) dashboard_config_value('snapshots.prettyPrint', true);
    $sourceCachedAtMs = (int) ($source['cachedAtMs'] ?? 0);
    $nowMs = (int) floor(microtime(true) * 1000);
    $state = telemetry_snapshot_read_state($statePath);
    $lastSnapshotAtMs = (int) ($state['lastSnapshotAtMs'] ?? 0);
    $lastSourceCachedAtMs = (int) ($state['lastSourceCachedAtMs'] ?? 0);

    if ($sourceCachedAtMs > 0 && $sourceCachedAtMs === $lastSourceCachedAtMs) {
        return;
    }

    if ($lastSnapshotAtMs > 0 && ($nowMs - $lastSnapshotAtMs) < $intervalMs) {
        return;
    }

    if (!is_dir($snapshotDir)) {
        @mkdir($snapshotDir, 0777, true);
    }

    if (!is_dir($snapshotDir) || !is_writable($snapshotDir)) {
        return;
    }

    $snapshotTakenAtMs = $nowMs;
    $snapshotPayload = [
        'snapshotTakenAt' => gmdate('c', (int) floor($snapshotTakenAtMs / 1000)),
        'snapshotTakenAtMs' => $snapshotTakenAtMs,
        'source' => [
            'type' => (string) ($source['type'] ?? 'none'),
            'statusCode' => $source['statusCode'] ?? null,
            'cachedAtMs' => $sourceCachedAtMs > 0 ? $sourceCachedAtMs : null,
            'error' => $source['error'] ?? null,
        ],
        'data' => $data,
    ];
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if ($prettyPrint) {
        $flags |= JSON_PRETTY_PRINT;
    }

    $snapshotPath = rtrim($snapshotDir, '/\\') . DIRECTORY_SEPARATOR . telemetry_snapshot_build_filename($snapshotTakenAtMs);
    $encodedPayload = json_encode($snapshotPayload, $flags);
    if ($encodedPayload === false) {
        return;
    }

    if (@file_put_contents($snapshotPath, $encodedPayload . PHP_EOL, LOCK_EX) === false) {
        return;
    }

    telemetry_snapshot_write_state($statePath, [
        'lastSnapshotAtMs' => $snapshotTakenAtMs,
        'lastSourceCachedAtMs' => $sourceCachedAtMs,
        'lastSnapshotFile' => basename($snapshotPath),
    ]);
}

function fetch_telemetry_data($telemetry_url = TELEMETRY_URL, &$source = null)
{
    $source = [
        'type' => 'none',
        'statusCode' => null,
        'error' => null,
        'cachedAtMs' => null,
    ];

    $timeoutMs = (int) dashboard_config_value('telemetry.requestTimeoutMs', 4500);
    $timeoutSeconds = max(1.0, $timeoutMs / 1000);
    $cacheEnabled = (bool) dashboard_config_value('telemetry.cacheEnabled', true);
    $cacheTtlMs = (int) dashboard_config_value('telemetry.cacheTtlMs', 10000);
    $cachePath = (string) dashboard_config_value('telemetry.cacheFile', __DIR__ . '/tmp/telemetry-cache.json');
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeoutSeconds,
            'header' => "Accept: application/json\r\nUser-Agent: ETS2WebDashboard/1.0\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $telemetry_data = @file_get_contents($telemetry_url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = telemetry_http_status_code(is_array($responseHeaders) ? $responseHeaders : []);
    $source['statusCode'] = $statusCode;

    if ($telemetry_data !== false && $statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
        $json_data = json_decode($telemetry_data, true);
        if (is_array($json_data)) {
            $cachedAtMs = (int) floor(microtime(true) * 1000);
            if ($cacheEnabled) {
                $cachedAtMs = telemetry_cache_write($cachePath, $json_data, $cachedAtMs);
            }

            $source['type'] = 'upstream';
            $source['cachedAtMs'] = $cachedAtMs;
            telemetry_snapshot_capture_if_due($json_data, $source);
            return $json_data;
        }

        $source['error'] = 'Invalid JSON in upstream response';
    } else {
        $source['error'] = $telemetry_data === false
            ? 'Unable to reach telemetry upstream'
            : 'Telemetry upstream returned non-success status';
    }

    if ($cacheEnabled) {
        $cachedRecord = telemetry_cache_read($cachePath, $cacheTtlMs);
        if (is_array($cachedRecord) && is_array($cachedRecord['data'] ?? null)) {
            $source['type'] = 'cache';
            $source['cachedAtMs'] = (int) ($cachedRecord['cachedAtMs'] ?? 0);
            telemetry_snapshot_capture_if_due($cachedRecord['data'], $source);
            return $cachedRecord['data'];
        }
    }

    $source['type'] = 'none';
    return [];
}

function get_telemetry_refresh_interval_ms()
{
    return TELEMETRY_REFRESH_INTERVAL_MS;
}

function get_telemetry_value($json_data, $path, $default = null)
{
    $segments = is_array($path) ? $path : explode(".", $path);
    $value = $json_data;

    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
            continue;
        }

        return $default;
    }

    return $value;
}

function get_telemetry_endpoints()
{
    return [
        "serverVersion",
        "game",
        "game.connected",
        "game.gameName",
        "game.time",
        "game.paused",
        "game.version",
        "game.telemetryPluginVersion",
        "game.timeScale",
        "game.nextRestStopTime",
        "truck",
        "truck.id",
        "truck.make",
        "truck.model",
        "truck.licensePlate",
        "truck.licensePlateCountryId",
        "truck.licensePlateCountry",
        "truck.speed",
        "truck.cruiseControlOn",
        "truck.cruiseControlSpeed",
        "truck.odometer",
        "truck.gear",
        "truck.displayedGear",
        "truck.forwardGears",
        "truck.reverseGears",
        "truck.engineRpm",
        "truck.engineRpmMax",
        "truck.fuel",
        "truck.fuelCapacity",
        "truck.fuelAverageConsumption",
        "truck.fuelRange",
        "truck.userSteer",
        "truck.userThrottle",
        "truck.userBrake",
        "truck.userClutch",
        "truck.gameSteer",
        "truck.gameThrottle",
        "truck.gameBrake",
        "truck.gameClutch",
        "truck.retarderBrake",
        "truck.retarderStepCount",
        "truck.shifterSlot",
        "truck.shifterType",
        "truck.engineOn",
        "truck.electricOn",
        "truck.wipersOn",
        "truck.parkBrakeOn",
        "truck.motorBrakeOn",
        "truck.airPressure",
        "truck.airPressureWarningOn",
        "truck.airPressureWarningValue",
        "truck.airPressureEmergencyOn",
        "truck.airPressureEmergencyValue",
        "truck.brakeTemperature",
        "truck.adblue",
        "truck.adblueCapacity",
        "truck.oilTemperature",
        "truck.oilPressure",
        "truck.oilPressureWarningOn",
        "truck.oilPressureWarningValue",
        "truck.waterTemperature",
        "truck.waterTemperatureWarningOn",
        "truck.waterTemperatureWarningValue",
        "truck.batteryVoltage",
        "truck.batteryVoltageWarningOn",
        "truck.batteryVoltageWarningValue",
        "truck.lightsDashboardValue",
        "truck.lightsDashboardOn",
        "truck.blinkerLeftActive",
        "truck.blinkerRightActive",
        "truck.blinkerLeftOn",
        "truck.blinkerRightOn",
        "truck.lightsParkingOn",
        "truck.lightsBeamLowOn",
        "truck.lightsBeamHighOn",
        "truck.lightsAuxFrontOn",
        "truck.lightsAuxRoofOn",
        "truck.lightsBeaconOn",
        "truck.lightsBrakeOn",
        "truck.lightsReverseOn",
        "truck.placement",
        "truck.placement.x",
        "truck.placement.y",
        "truck.placement.z",
        "truck.placement.heading",
        "truck.placement.pitch",
        "truck.placement.roll",
        "truck.acceleration",
        "truck.acceleration.x",
        "truck.acceleration.y",
        "truck.acceleration.z",
        "truck.head",
        "truck.head.x",
        "truck.head.y",
        "truck.head.z",
        "truck.cabin",
        "truck.cabin.x",
        "truck.cabin.y",
        "truck.cabin.z",
        "truck.hook",
        "truck.hook.x",
        "truck.hook.y",
        "truck.hook.z",
        "trailers",
        "trailers.{index}",
        "trailers.{index}.attached",
        "trailers.{index}.id",
        "trailers.{index}.name",
        "trailers.{index}.brandId",
        "trailers.{index}.brand",
        "trailers.{index}.licensePlate",
        "trailers.{index}.licensePlateCountryId",
        "trailers.{index}.licensePlateCountry",
        "trailers.{index}.wearChassis",
        "trailers.{index}.wearWheels",
        "trailers.{index}.wearBody",
        "trailers.{index}.cargoDamage",
        "trailers.{index}.placement",
        "trailers.{index}.placement.x",
        "trailers.{index}.placement.y",
        "trailers.{index}.placement.z",
        "trailers.{index}.placement.heading",
        "trailers.{index}.placement.pitch",
        "trailers.{index}.placement.roll",
        "job",
        "job.income",
        "job.deadlineTime",
        "job.remainingTime",
        "job.plannedDistanceKm",
        "job.sourceCityId",
        "job.sourceCity",
        "job.sourceCompanyId",
        "job.sourceCompany",
        "job.destinationCityId",
        "job.destinationCity",
        "job.destinationCompanyId",
        "job.destinationCompany",
        "job.cargoId",
        "job.cargo",
        "job.cargoMass",
        "job.unitCount",
        "job.jobMarket",
        "navigation",
        "navigation.estimatedTime",
        "navigation.estimatedDistance",
        "navigation.speedLimit",
        "gameplay",
        "gameplay.onJob",
        "gameplay.jobFinished",
        "gameplay.jobCancelled",
        "gameplay.jobDelivered",
        "gameplay.fined",
        "gameplay.tollgate",
        "gameplay.ferry",
        "gameplay.train",
        "gameplay.refuel",
        "gameplay.refuelPayed",
        "gameplay.jobDeliveredDetails",
        "gameplay.jobDeliveredDetails.revenue",
        "gameplay.jobDeliveredDetails.earnedXp",
        "gameplay.jobDeliveredDetails.cargoDamage",
        "gameplay.jobDeliveredDetails.distanceKm",
        "gameplay.jobDeliveredDetails.deliveryTime",
        "gameplay.jobDeliveredDetails.autoParked",
        "gameplay.jobDeliveredDetails.autoLoaded",
        "gameplay.jobCancelledDetails",
        "gameplay.jobCancelledDetails.penalty",
        "gameplay.finedDetails",
        "gameplay.finedDetails.amount",
        "gameplay.finedDetails.offence",
        "gameplay.tollgateDetails",
        "gameplay.tollgateDetails.payAmount",
        "gameplay.ferryDetails",
        "gameplay.ferryDetails.payAmount",
        "gameplay.ferryDetails.sourceName",
        "gameplay.ferryDetails.targetName",
        "gameplay.trainDetails",
        "gameplay.trainDetails.payAmount",
        "gameplay.trainDetails.sourceName",
        "gameplay.trainDetails.targetName",
        "gameplay.refuelDetails",
        "gameplay.refuelDetails.amount",
    ];
}

function get_server_version($json_data)
{
    return get_telemetry_value($json_data, "serverVersion");
}

function get_game_data($json_data)
{
    return get_telemetry_value($json_data, "game");
}
function get_game_connected($json_data)
{
    return get_telemetry_value($json_data, "game.connected");
}
function get_game_name($json_data)
{
    return get_telemetry_value($json_data, "game.gameName");
}
function get_game_time($json_data)
{
    return get_telemetry_value($json_data, "game.time");
}
function get_game_paused($json_data)
{
    return get_telemetry_value($json_data, "game.paused");
}
function get_game_version($json_data)
{
    return get_telemetry_value($json_data, "game.version");
}
function get_telemetry_plugin_version($json_data)
{
    return get_telemetry_value($json_data, "game.telemetryPluginVersion");
}
function get_game_time_scale($json_data)
{
    return get_telemetry_value($json_data, "game.timeScale");
}
function get_next_rest_stop_time($json_data)
{
    return get_telemetry_value($json_data, "game.nextRestStopTime");
}

function get_truck_data($json_data)
{
    return get_telemetry_value($json_data, "truck");
}
function get_truck_id($json_data)
{
    return get_telemetry_value($json_data, "truck.id");
}
function get_truck_make($json_data)
{
    return get_telemetry_value($json_data, "truck.make");
}
function get_truck_model($json_data)
{
    return get_telemetry_value($json_data, "truck.model");
}
function get_license_plate($json_data)
{
    return get_telemetry_value($json_data, "truck.licensePlate");
}
function get_license_plate_country_id($json_data)
{
    return get_telemetry_value($json_data, "truck.licensePlateCountryId");
}
function get_license_plate_country($json_data)
{
    return get_telemetry_value($json_data, "truck.licensePlateCountry");
}
function get_truck_speed($json_data)
{
    return get_telemetry_value($json_data, "truck.speed");
}
function get_cruise_control_on($json_data)
{
    return get_telemetry_value($json_data, "truck.cruiseControlOn");
}
function get_cruise_control_speed($json_data)
{
    return get_telemetry_value($json_data, "truck.cruiseControlSpeed");
}
function get_odometer($json_data)
{
    return get_telemetry_value($json_data, "truck.odometer");
}
function get_gear($json_data)
{
    return get_telemetry_value($json_data, "truck.gear");
}
function get_displayed_gear($json_data)
{
    return get_telemetry_value($json_data, "truck.displayedGear");
}
function get_forward_gears($json_data)
{
    return get_telemetry_value($json_data, "truck.forwardGears");
}
function get_reverse_gears($json_data)
{
    return get_telemetry_value($json_data, "truck.reverseGears");
}
function get_engine_rpm($json_data)
{
    return get_telemetry_value($json_data, "truck.engineRpm");
}
function get_engine_rpm_max($json_data)
{
    return get_telemetry_value($json_data, "truck.engineRpmMax");
}
function get_fuel($json_data)
{
    return get_telemetry_value($json_data, "truck.fuel");
}
function get_fuel_capacity($json_data)
{
    return get_telemetry_value($json_data, "truck.fuelCapacity");
}
function get_fuel_average_consumption($json_data)
{
    return get_telemetry_value($json_data, "truck.fuelAverageConsumption");
}
function get_fuel_range($json_data)
{
    return get_telemetry_value($json_data, "truck.fuelRange");
}
function get_user_steer($json_data)
{
    return get_telemetry_value($json_data, "truck.userSteer");
}
function get_user_throttle($json_data)
{
    return get_telemetry_value($json_data, "truck.userThrottle");
}
function get_user_brake($json_data)
{
    return get_telemetry_value($json_data, "truck.userBrake");
}
function get_user_clutch($json_data)
{
    return get_telemetry_value($json_data, "truck.userClutch");
}
function get_game_steer($json_data)
{
    return get_telemetry_value($json_data, "truck.gameSteer");
}
function get_game_throttle($json_data)
{
    return get_telemetry_value($json_data, "truck.gameThrottle");
}
function get_game_brake($json_data)
{
    return get_telemetry_value($json_data, "truck.gameBrake");
}
function get_game_clutch($json_data)
{
    return get_telemetry_value($json_data, "truck.gameClutch");
}
function get_retarder_brake($json_data)
{
    return get_telemetry_value($json_data, "truck.retarderBrake");
}
function get_retarder_step_count($json_data)
{
    return get_telemetry_value($json_data, "truck.retarderStepCount");
}
function get_shifter_slot($json_data)
{
    return get_telemetry_value($json_data, "truck.shifterSlot");
}
function get_shifter_type($json_data)
{
    return get_telemetry_value($json_data, "truck.shifterType");
}
function get_engine_on($json_data)
{
    return get_telemetry_value($json_data, "truck.engineOn");
}
function get_electric_on($json_data)
{
    return get_telemetry_value($json_data, "truck.electricOn");
}
function get_wipers_on($json_data)
{
    return get_telemetry_value($json_data, "truck.wipersOn");
}
function get_park_brake_on($json_data)
{
    return get_telemetry_value($json_data, "truck.parkBrakeOn");
}
function get_motor_brake_on($json_data)
{
    return get_telemetry_value($json_data, "truck.motorBrakeOn");
}
function get_air_pressure($json_data)
{
    return get_telemetry_value($json_data, "truck.airPressure");
}
function get_air_pressure_warning_on($json_data)
{
    return get_telemetry_value($json_data, "truck.airPressureWarningOn");
}
function get_air_pressure_warning_value($json_data)
{
    return get_telemetry_value($json_data, "truck.airPressureWarningValue");
}
function get_air_pressure_emergency_on($json_data)
{
    return get_telemetry_value($json_data, "truck.airPressureEmergencyOn");
}
function get_air_pressure_emergency_value($json_data)
{
    return get_telemetry_value($json_data, "truck.airPressureEmergencyValue");
}
function get_brake_temperature($json_data)
{
    return get_telemetry_value($json_data, "truck.brakeTemperature");
}
function get_adblue($json_data)
{
    return get_telemetry_value($json_data, "truck.adblue");
}
function get_adblue_capacity($json_data)
{
    return get_telemetry_value($json_data, "truck.adblueCapacity");
}
function get_oil_temperature($json_data)
{
    return get_telemetry_value($json_data, "truck.oilTemperature");
}
function get_oil_pressure($json_data)
{
    return get_telemetry_value($json_data, "truck.oilPressure");
}
function get_oil_pressure_warning_on($json_data)
{
    return get_telemetry_value($json_data, "truck.oilPressureWarningOn");
}
function get_oil_pressure_warning_value($json_data)
{
    return get_telemetry_value($json_data, "truck.oilPressureWarningValue");
}
function get_water_temperature($json_data)
{
    return get_telemetry_value($json_data, "truck.waterTemperature");
}
function get_water_temperature_warning_on($json_data)
{
    return get_telemetry_value($json_data, "truck.waterTemperatureWarningOn");
}
function get_water_temperature_warning_value($json_data)
{
    return get_telemetry_value($json_data, "truck.waterTemperatureWarningValue");
}
function get_battery_voltage($json_data)
{
    return get_telemetry_value($json_data, "truck.batteryVoltage");
}
function get_battery_voltage_warning_on($json_data)
{
    return get_telemetry_value($json_data, "truck.batteryVoltageWarningOn");
}
function get_battery_voltage_warning_value($json_data)
{
    return get_telemetry_value($json_data, "truck.batteryVoltageWarningValue");
}
function get_lights_dashboard_value($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsDashboardValue");
}
function get_lights_dashboard_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsDashboardOn");
}
function get_blinker_left_active($json_data)
{
    return get_telemetry_value($json_data, "truck.blinkerLeftActive");
}
function get_blinker_right_active($json_data)
{
    return get_telemetry_value($json_data, "truck.blinkerRightActive");
}
function get_blinker_left_on($json_data)
{
    return get_telemetry_value($json_data, "truck.blinkerLeftOn");
}
function get_blinker_right_on($json_data)
{
    return get_telemetry_value($json_data, "truck.blinkerRightOn");
}
function get_lights_parking_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsParkingOn");
}
function get_lights_beam_low_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsBeamLowOn");
}
function get_lights_beam_high_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsBeamHighOn");
}
function get_lights_aux_front_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsAuxFrontOn");
}
function get_lights_aux_roof_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsAuxRoofOn");
}
function get_lights_beacon_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsBeaconOn");
}
function get_lights_brake_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsBrakeOn");
}
function get_lights_reverse_on($json_data)
{
    return get_telemetry_value($json_data, "truck.lightsReverseOn");
}

function get_truck_placement($json_data)
{
    return get_telemetry_value($json_data, "truck.placement");
}
function get_truck_position_x($json_data)
{
    return get_telemetry_value($json_data, "truck.placement.x");
}
function get_truck_position_y($json_data)
{
    return get_telemetry_value($json_data, "truck.placement.y");
}
function get_truck_position_z($json_data)
{
    return get_telemetry_value($json_data, "truck.placement.z");
}
function get_truck_heading($json_data)
{
    return get_telemetry_value($json_data, "truck.placement.heading");
}
function get_truck_pitch($json_data)
{
    return get_telemetry_value($json_data, "truck.placement.pitch");
}
function get_truck_roll($json_data)
{
    return get_telemetry_value($json_data, "truck.placement.roll");
}

function get_truck_acceleration($json_data)
{
    return get_telemetry_value($json_data, "truck.acceleration");
}
function get_truck_acceleration_x($json_data)
{
    return get_telemetry_value($json_data, "truck.acceleration.x");
}
function get_truck_acceleration_y($json_data)
{
    return get_telemetry_value($json_data, "truck.acceleration.y");
}
function get_truck_acceleration_z($json_data)
{
    return get_telemetry_value($json_data, "truck.acceleration.z");
}

function get_truck_head($json_data)
{
    return get_telemetry_value($json_data, "truck.head");
}
function get_truck_head_x($json_data)
{
    return get_telemetry_value($json_data, "truck.head.x");
}
function get_truck_head_y($json_data)
{
    return get_telemetry_value($json_data, "truck.head.y");
}
function get_truck_head_z($json_data)
{
    return get_telemetry_value($json_data, "truck.head.z");
}

function get_truck_cabin($json_data)
{
    return get_telemetry_value($json_data, "truck.cabin");
}
function get_truck_cabin_x($json_data)
{
    return get_telemetry_value($json_data, "truck.cabin.x");
}
function get_truck_cabin_y($json_data)
{
    return get_telemetry_value($json_data, "truck.cabin.y");
}
function get_truck_cabin_z($json_data)
{
    return get_telemetry_value($json_data, "truck.cabin.z");
}

function get_truck_hook($json_data)
{
    return get_telemetry_value($json_data, "truck.hook");
}
function get_truck_hook_x($json_data)
{
    return get_telemetry_value($json_data, "truck.hook.x");
}
function get_truck_hook_y($json_data)
{
    return get_telemetry_value($json_data, "truck.hook.y");
}
function get_truck_hook_z($json_data)
{
    return get_telemetry_value($json_data, "truck.hook.z");
}

function get_trailers_data($json_data)
{
    return get_telemetry_value($json_data, "trailers", []);
}
function get_trailer_data($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index], []);
}
function get_trailer_attached($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "attached"]);
}
function get_trailer_id($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "id"]);
}
function get_trailer_name($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "name"]);
}
function get_trailer_brand_id($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "brandId"]);
}
function get_trailer_brand($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "brand"]);
}
function get_trailer_license_plate($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "licensePlate"]);
}
function get_trailer_license_plate_country_id($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "licensePlateCountryId"]);
}
function get_trailer_license_plate_country($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "licensePlateCountry"]);
}
function get_trailer_wear_chassis($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "wearChassis"]);
}
function get_trailer_wear_wheels($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "wearWheels"]);
}
function get_trailer_wear_body($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "wearBody"]);
}
function get_trailer_cargo_damage($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "cargoDamage"]);
}
function get_trailer_placement($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement"]);
}
function get_trailer_position_x($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement", "x"]);
}
function get_trailer_position_y($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement", "y"]);
}
function get_trailer_position_z($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement", "z"]);
}
function get_trailer_heading($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement", "heading"]);
}
function get_trailer_pitch($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement", "pitch"]);
}
function get_trailer_roll($json_data, $index = 0)
{
    return get_telemetry_value($json_data, ["trailers", $index, "placement", "roll"]);
}

function get_job_data($json_data)
{
    return get_telemetry_value($json_data, "job");
}
function get_job_income($json_data)
{
    return get_telemetry_value($json_data, "job.income");
}
function get_job_deadline_time($json_data)
{
    return get_telemetry_value($json_data, "job.deadlineTime");
}
function get_job_remaining_time($json_data)
{
    return get_telemetry_value($json_data, "job.remainingTime");
}
function get_job_planned_distance_km($json_data)
{
    return get_telemetry_value($json_data, "job.plannedDistanceKm");
}
function get_job_source_city_id($json_data)
{
    return get_telemetry_value($json_data, "job.sourceCityId");
}
function get_job_source_city($json_data)
{
    return get_telemetry_value($json_data, "job.sourceCity");
}
function get_job_source_company_id($json_data)
{
    return get_telemetry_value($json_data, "job.sourceCompanyId");
}
function get_job_source_company($json_data)
{
    return get_telemetry_value($json_data, "job.sourceCompany");
}
function get_job_destination_city_id($json_data)
{
    return get_telemetry_value($json_data, "job.destinationCityId");
}
function get_job_destination_city($json_data)
{
    return get_telemetry_value($json_data, "job.destinationCity");
}
function get_job_destination_company_id($json_data)
{
    return get_telemetry_value($json_data, "job.destinationCompanyId");
}
function get_job_destination_company($json_data)
{
    return get_telemetry_value($json_data, "job.destinationCompany");
}
function get_job_cargo_id($json_data)
{
    return get_telemetry_value($json_data, "job.cargoId");
}
function get_job_cargo($json_data)
{
    return get_telemetry_value($json_data, "job.cargo");
}
function get_job_cargo_mass($json_data)
{
    return get_telemetry_value($json_data, "job.cargoMass");
}
function get_job_unit_count($json_data)
{
    return get_telemetry_value($json_data, "job.unitCount");
}
function get_job_market($json_data)
{
    return get_telemetry_value($json_data, "job.jobMarket");
}

function get_navigation_data($json_data)
{
    return get_telemetry_value($json_data, "navigation");
}
function get_navigation_estimated_time($json_data)
{
    return get_telemetry_value($json_data, "navigation.estimatedTime");
}
function get_navigation_estimated_distance($json_data)
{
    return get_telemetry_value($json_data, "navigation.estimatedDistance");
}
function get_navigation_speed_limit($json_data)
{
    return get_telemetry_value($json_data, "navigation.speedLimit");
}

function get_gameplay_data($json_data)
{
    return get_telemetry_value($json_data, "gameplay");
}
function get_on_job($json_data)
{
    return get_telemetry_value($json_data, "gameplay.onJob");
}
function get_job_finished($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobFinished");
}
function get_job_cancelled($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobCancelled");
}
function get_job_delivered($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDelivered");
}
function get_fined($json_data)
{
    return get_telemetry_value($json_data, "gameplay.fined");
}
function get_tollgate($json_data)
{
    return get_telemetry_value($json_data, "gameplay.tollgate");
}
function get_ferry($json_data)
{
    return get_telemetry_value($json_data, "gameplay.ferry");
}
function get_train($json_data)
{
    return get_telemetry_value($json_data, "gameplay.train");
}
function get_refuel($json_data)
{
    return get_telemetry_value($json_data, "gameplay.refuel");
}
function get_refuel_payed($json_data)
{
    return get_telemetry_value($json_data, "gameplay.refuelPayed");
}

function get_job_delivered_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails");
}
function get_job_delivered_revenue($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.revenue");
}
function get_job_delivered_earned_xp($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.earnedXp");
}
function get_job_delivered_cargo_damage($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.cargoDamage");
}
function get_job_delivered_distance_km($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.distanceKm");
}
function get_job_delivered_delivery_time($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.deliveryTime");
}
function get_job_delivered_auto_parked($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.autoParked");
}
function get_job_delivered_auto_loaded($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobDeliveredDetails.autoLoaded");
}

function get_job_cancelled_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobCancelledDetails");
}
function get_job_cancelled_penalty($json_data)
{
    return get_telemetry_value($json_data, "gameplay.jobCancelledDetails.penalty");
}

function get_fined_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.finedDetails");
}
function get_fined_amount($json_data)
{
    return get_telemetry_value($json_data, "gameplay.finedDetails.amount");
}
function get_fined_offence($json_data)
{
    return get_telemetry_value($json_data, "gameplay.finedDetails.offence");
}

function get_tollgate_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.tollgateDetails");
}
function get_tollgate_pay_amount($json_data)
{
    return get_telemetry_value($json_data, "gameplay.tollgateDetails.payAmount");
}

function get_ferry_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.ferryDetails");
}
function get_ferry_pay_amount($json_data)
{
    return get_telemetry_value($json_data, "gameplay.ferryDetails.payAmount");
}
function get_ferry_source_name($json_data)
{
    return get_telemetry_value($json_data, "gameplay.ferryDetails.sourceName");
}
function get_ferry_target_name($json_data)
{
    return get_telemetry_value($json_data, "gameplay.ferryDetails.targetName");
}

function get_train_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.trainDetails");
}
function get_train_pay_amount($json_data)
{
    return get_telemetry_value($json_data, "gameplay.trainDetails.payAmount");
}
function get_train_source_name($json_data)
{
    return get_telemetry_value($json_data, "gameplay.trainDetails.sourceName");
}
function get_train_target_name($json_data)
{
    return get_telemetry_value($json_data, "gameplay.trainDetails.targetName");
}

function get_refuel_details($json_data)
{
    return get_telemetry_value($json_data, "gameplay.refuelDetails");
}
function get_refuel_amount($json_data)
{
    return get_telemetry_value($json_data, "gameplay.refuelDetails.amount");
}

$telemetry_source = null;
$json_data = fetch_telemetry_data(TELEMETRY_URL, $telemetry_source);

function fetchPlayersData($meX, $meY, $radiusInput = 5500, $serverInput = 50)
{
    $radius = (int)($radiusInput ?: 5500);
    $server = (int)($serverInput ?: 50);

    $x1 = round($meX - $radius);
    $x2 = round($meX + $radius);
    $y1 = round($meY + $radius);
    $y2 = round($meY - $radius);

    $url = AREA_URL . "?x1=$x1&y1=$y1&x2=$x2&y2=$y2&server=$server";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'ETS2WebDashboard/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    unset($ch);

    if ($response === false || $errno !== 0) {
        throw new Exception("Spieler-Feed nicht erreichbar: $error");
    }

    $json = json_decode($response, true);

    if (!isset($json['Success']) || !$json['Success'] || !isset($json['Data']) || !is_array($json['Data'])) {
        throw new Exception("Ungültige Spieler-Daten");
    }

    $players = $json['Data'];

    return [
        "players" => $players,
        "count" => count($players)
    ];
}

function fetchPlayers()
{
    try {
        $telemetry_data = fetch_telemetry_data(TELEMETRY_URL, $source);
        $truck_position_x = get_truck_position_x($telemetry_data);
        $truck_position_y = get_truck_position_y($telemetry_data);

        return fetchPlayersData($truck_position_x, $truck_position_y);
    } catch (Exception $e) {
        error_log("Fehler beim Abrufen der Spieler-Daten: " . $e->getMessage());
        return [
            "players" => [],
            "count" => 0,
            "error" => $e->getMessage()
        ];
    }
}

if (
    basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"] ?? "") &&
    (($_GET["format"] ?? "") === "json")
) {
    $jsonFlags = JSON_UNESCAPED_SLASHES;
    if ((bool) dashboard_config_value('telemetry.jsonPrettyPrint', true)) {
        $jsonFlags |= JSON_PRETTY_PRINT;
    }

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    echo json_encode([
        "refreshIntervalMs" => get_telemetry_refresh_interval_ms(),
        "fetchedAt" => gmdate("c"),
        "source" => $telemetry_source,
        "data" => $json_data,
    ], $jsonFlags);
    exit;
}

if (
    basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"] ?? "") &&
    (($_GET["format"] ?? "") === "players")
) {
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    $x1 = isset($_GET['x1']) ? (int) $_GET['x1'] : 0;
    $y1 = isset($_GET['y1']) ? (int) $_GET['y1'] : 0;
    $x2 = isset($_GET['x2']) ? (int) $_GET['x2'] : 0;
    $y2 = isset($_GET['y2']) ? (int) $_GET['y2'] : 0;
    $server = isset($_GET['server']) ? (int) $_GET['server'] : 50;

    $url = AREA_URL . '?' . http_build_query([
        'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 'server' => $server,
    ], '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'ETS2WebDashboard/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    unset($ch);

    if ($response === false || $errno !== 0) {
        http_response_code(502);
        echo json_encode(['Success' => false, 'error' => 'Player feed unreachable: ' . $error], JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo $response;
    exit;
}
