<?php
/**
 * Phase 0.5 — Docker config bootstrap.
 *
 * Reads `include/config/global.inc.dist.php`, swaps in the docker
 * service hostnames + credentials from the container env, generates
 * fresh random SALT + SALTY values, and writes the result to
 * `include/config/global.inc.php` ONLY if that file does not already
 * exist.
 *
 * Idempotent and safe to re-run. Will refuse to overwrite an existing
 * config; pass `--force` to overwrite (the old file is renamed
 * `global.inc.php.bak.<timestamp>`).
 *
 * Run via:  make bootstrap-config
 */

declare(strict_types=1);

$root      = dirname(__DIR__);
$distPath  = $root . '/include/config/global.inc.dist.php';
$realPath  = $root . '/include/config/global.inc.php';
$force     = in_array('--force', $argv, true);

if (!is_file($distPath)) {
    fwrite(STDERR, "FATAL: dist config not found at {$distPath}\n");
    exit(2);
}

if (is_file($realPath) && !$force) {
    fwrite(STDOUT, "OK: config already exists at {$realPath} (use --force to regenerate)\n");
    exit(0);
}

if (is_file($realPath) && $force) {
    $bak = $realPath . '.bak.' . date('Ymd-His');
    rename($realPath, $bak);
    fwrite(STDOUT, "Backed up existing config to {$bak}\n");
}

// Pull values from the container env, with sensible fallbacks that match
// the docker-compose defaults.
$env = static function (string $key, string $default): string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
};

$dbHost      = $env('MPOS_DB_HOST',         'mysql');
$dbName      = $env('MPOS_DB_NAME',         'mpos');
$dbUser      = $env('MPOS_DB_USER',         'mpos');
$dbPass      = $env('MPOS_DB_PASS',         'mpos');
$mcHost      = $env('MPOS_MEMCACHED_HOST',  'memcached');

// 32 random bytes → 64 hex chars, enough entropy for password salting.
// Upstream uses two salts ($config['SALT'] and $config['SALTY']);
// both get fresh values here.
$salt  = bin2hex(random_bytes(32));
$salty = bin2hex(random_bytes(32));

$source = file_get_contents($distPath);
if ($source === false) {
    fwrite(STDERR, "FATAL: could not read {$distPath}\n");
    exit(2);
}

// Targeted line-level replacements. Using full-line keys avoids accidentally
// hitting the word `localhost` inside the wallet RPC config.
$phpEscape = static fn(string $v): string => addcslashes($v, "'\\");

// Upstream moved the salts from `define('SALT', ...)` constants onto two
// $config[] keys, so we swap both. Other lines match the dist verbatim.
$replacements = [
    "\$config['db']['host'] = 'localhost';"              => "\$config['db']['host'] = '" . $phpEscape($dbHost) . "';",
    "\$config['db']['user'] = 'someuser';"               => "\$config['db']['user'] = '" . $phpEscape($dbUser) . "';",
    "\$config['db']['pass'] = 'somepass';"               => "\$config['db']['pass'] = '" . $phpEscape($dbPass) . "';",
    "\$config['db']['name'] = 'mpos';"                   => "\$config['db']['name'] = '" . $phpEscape($dbName) . "';",
    "\$config['memcache']['host'] = 'localhost';"        => "\$config['memcache']['host'] = '" . $phpEscape($mcHost) . "';",
    "\$config['SALT'] = 'PLEASEMAKEMESOMETHINGRANDOM';"  => "\$config['SALT'] = '" . $phpEscape($salt)  . "';",
    "\$config['SALTY'] = 'THISSHOULDALSOBERRAANNDDOOM';" => "\$config['SALTY'] = '" . $phpEscape($salty) . "';",
];

$patched = $source;
$missed  = [];
foreach ($replacements as $needle => $replacement) {
    $count = 0;
    $patched = str_replace($needle, $replacement, $patched, $count);
    if ($count === 0) {
        $missed[] = $needle;
    }
}

if ($missed) {
    fwrite(STDERR, "WARNING: the following lines were not found in the dist config (the file format may have drifted):\n");
    foreach ($missed as $m) {
        fwrite(STDERR, "  - {$m}\n");
    }
}

// Sanity check: lint the result before writing it out.
$tmp = tempnam(sys_get_temp_dir(), 'mpos-cfg-');
file_put_contents($tmp, $patched);
$lintCmd = escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1';
$lintOut = shell_exec($lintCmd);
unlink($tmp);
if (!is_string($lintOut) || stripos($lintOut, 'No syntax errors detected') === false) {
    fwrite(STDERR, "FATAL: generated config failed PHP lint:\n{$lintOut}\n");
    exit(3);
}

if (file_put_contents($realPath, $patched) === false) {
    fwrite(STDERR, "FATAL: could not write {$realPath}\n");
    exit(4);
}
@chmod($realPath, 0640);

fwrite(STDOUT, "Wrote {$realPath}\n");
fwrite(STDOUT, "  db.host       = {$dbHost}\n");
fwrite(STDOUT, "  db.name       = {$dbName}\n");
fwrite(STDOUT, "  db.user       = {$dbUser}\n");
fwrite(STDOUT, "  db.pass       = " . str_repeat('*', strlen($dbPass)) . "\n");
fwrite(STDOUT, "  memcache.host = {$mcHost}\n");
fwrite(STDOUT, "  SALT          = (32 bytes random hex)\n");
fwrite(STDOUT, "  SALTY         = (32 bytes random hex)\n");
exit(0);
