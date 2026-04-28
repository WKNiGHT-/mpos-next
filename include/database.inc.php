<?php
$defflip = (!cfip()) ? exit(header('HTTP/1.1 401 Unauthorized')) : 1;

/**
 * Phase 5: clearer DB initialization with proper error ordering,
 * explicit charset enforcement, and a readable failure message.
 *
 * Note on the order of operations:
 *   - mysqlims (extends mysqli) THROWS on connection failure (see its
 *     ctor); the global mysqli_connect_errno() that the prior code
 *     checked is never set on this path. So an unhandled Exception
 *     was previously leaking a stack trace to the user on DB failure.
 *     Wrap in try/catch and emit a clear message instead.
 *   - The read-only check that runs a query MUST come AFTER the
 *     connection is verified, never before. Likewise, the chained
 *     ->fetch_object()->read_only on a possibly-failed query is
 *     replaced with null-safe access.
 *   - SET NAMES is issued through the wrapper's overridden query()
 *     so it routes to the master connection (mysqlims doesn't fully
 *     initialize the parent mysqli, so $mysqli->set_charset() would
 *     not work directly).
 */

try {
    $mysqli = new mysqlims($config['db'], $config['db-ro'], (bool)$config['mysql_filter']);
} catch (\Throwable $e) {
    error_log('mpos: database connection failed: ' . $e->getMessage());
    die(
        'Database connection failed (host: '
        . htmlspecialchars((string)$config['db']['host'], ENT_QUOTES) . ':'
        . (int)$config['db']['port']
        . '). Check include/config/global.inc.php credentials and that the '
        . 'DB server is reachable.'
    );
}

// Force utf8mb4 on the active session, defensively. The docker dev
// MySQL already defaults to utf8mb4, but legacy hosts may not — and
// our base SQL still has stray `SET NAMES utf8` (utf8mb3) lines that
// could otherwise leave the connection on a narrower charset for the
// rest of the request.
@$mysqli->query("SET NAMES 'utf8mb4'");

// Read-only protection: refuse to serve write-path requests if the
// master MySQL is in `read_only` mode and no slave is configured.
$roResult = $mysqli->query('/* MYSQLND_MS_MASTER_SWITCH */SELECT @@global.read_only AS read_only');
$roRow    = $roResult ? $roResult->fetch_object() : null;
if ($roRow && (int)$roRow->read_only === 1 && (($config['db-ro']['enabled'] ?? false) === false)) {
    die('Database is in READ-ONLY mode');
}
