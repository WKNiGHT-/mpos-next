<?php
/**
 * Phase 0.5 — health endpoint.
 *
 * Verifies the docker dev stack:
 *   - PHP is running (trivially true if you got here)
 *   - mysqli extension loaded
 *   - memcached extension loaded
 *   - MySQL is reachable with the configured credentials
 *   - Memcached is reachable
 *
 * Works in two modes:
 *   - HTTP   : returns JSON, sets HTTP 200 if all healthy else 503
 *   - CLI    : prints text, exits 0 if all healthy else 1
 *
 * Reads connection details from container env vars (MPOS_DB_*,
 * MPOS_MEMCACHED_*) seeded by docker-compose. We deliberately do NOT
 * include upstream's include/config/global.inc.php here — it pulls in
 * cfip()/SECHASH gating from public/index.php and isn't safe to load in
 * isolation. The env values match what bootstrap-config.php writes.
 *
 * NOTE: this file is intentionally simple and exposes very small amounts
 * of diagnostic info. It is appropriate for a local docker dev stack.
 * Remove it (or restrict it via a .htaccess block) before deploying to
 * a public host.
 */

declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');

// ---------------------------------------------------------------------------
// Resolve connection info from container env.
// ---------------------------------------------------------------------------
$envFallback = static function (string $key, string $default): string {
    $v = getenv($key);
    if (($v === false || $v === '') && isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        $v = (string)$_SERVER[$key];
    }
    return ($v === false || $v === '') ? $default : $v;
};

$dbHost = $envFallback('MPOS_DB_HOST', 'mysql');
$dbPort = (int)$envFallback('MPOS_DB_PORT', '3306');
$dbName = $envFallback('MPOS_DB_NAME', 'mpos');
$dbUser = $envFallback('MPOS_DB_USER', 'mpos');
$dbPass = $envFallback('MPOS_DB_PASS', 'mpos');
$mcHost = $envFallback('MPOS_MEMCACHED_HOST', 'memcached');
$mcPort = (int)$envFallback('MPOS_MEMCACHED_PORT', '11211');

// ---------------------------------------------------------------------------
// Run checks.
// ---------------------------------------------------------------------------
$checks = [];

$checks[] = [
    'name'    => 'php',
    'ok'      => true,
    'detail'  => 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
];

$checks[] = [
    'name'    => 'ext.mysqli',
    'ok'      => extension_loaded('mysqli'),
    'detail'  => extension_loaded('mysqli') ? 'loaded' : 'missing',
];

$checks[] = [
    'name'    => 'ext.memcached',
    'ok'      => extension_loaded('memcached'),
    'detail'  => extension_loaded('memcached') ? 'loaded' : 'missing',
];

// MySQL connectivity ---------------------------------------------------------
$mysqlCheck = ['name' => 'mysql', 'ok' => false, 'detail' => ''];
if (!extension_loaded('mysqli')) {
    $mysqlCheck['detail'] = 'mysqli extension not loaded';
} else {
    // PHP 8.1+ defaults to throwing on mysqli errors; turn that off so
    // we can format failures ourselves.
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    if ($conn instanceof mysqli) {
        $mysqlCheck['ok']     = true;
        $mysqlCheck['detail'] = "connected to {$dbHost}:{$dbPort}/{$dbName} (server " . mysqli_get_server_info($conn) . ')';
        mysqli_close($conn);
    } else {
        $mysqlCheck['detail'] = 'connect failed: ' . mysqli_connect_error();
    }
}
$checks[] = $mysqlCheck;

// Memcached connectivity -----------------------------------------------------
$mcCheck = ['name' => 'memcached', 'ok' => false, 'detail' => ''];
if (!extension_loaded('memcached')) {
    $mcCheck['detail'] = 'memcached extension not loaded';
} else {
    try {
        $mc = new Memcached();
        $mc->addServer($mcHost, $mcPort);
        // getStats() returns false on connection failure, array on success.
        $stats = @$mc->getStats();
        $key   = "{$mcHost}:{$mcPort}";
        if (is_array($stats) && isset($stats[$key]) && is_array($stats[$key]) && !empty($stats[$key]['pid'])) {
            $mcCheck['ok']     = true;
            $mcCheck['detail'] = "connected to {$key} (pid {$stats[$key]['pid']}, version " . ($stats[$key]['version'] ?? '?') . ')';
        } else {
            $mcCheck['detail'] = "could not reach {$key}";
        }
    } catch (Throwable $e) {
        $mcCheck['detail'] = 'exception: ' . $e->getMessage();
    }
}
$checks[] = $mcCheck;

// ---------------------------------------------------------------------------
// Compile + emit response.
// ---------------------------------------------------------------------------
$ok = array_reduce($checks, static fn(bool $carry, array $c): bool => $carry && (bool)$c['ok'], true);

if ($isCli) {
    foreach ($checks as $c) {
        $mark = $c['ok'] ? '[ OK ]' : '[FAIL]';
        printf("%s  %-16s  %s\n", $mark, $c['name'], $c['detail']);
    }
    echo $ok ? "\nhealthy\n" : "\nUNHEALTHY\n";
    exit($ok ? 0 : 1);
}

http_response_code($ok ? 200 : 503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'status' => $ok ? 'healthy' : 'unhealthy',
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
