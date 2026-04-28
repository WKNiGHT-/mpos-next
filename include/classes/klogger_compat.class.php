<?php
$defflip = (!cfip()) ? exit(header('HTTP/1.1 401 Unauthorized')) : 1;

/**
 * Phase 3 — KLogger compatibility shim.
 *
 * The bundled include/lib/KLogger.php was the original 2012 release of
 * Kenny Katzgrau's logger (v0.2.0). Its current Composer-managed
 * descendant — katzgrau/klogger ^1.2 — is a PSR-3 rewrite living under
 * the namespace `Katzgrau\KLogger\Logger`, so its constructor, level
 * constants, and method names all differ from the v0.2 API that
 * MPOS's 195 callsites depend on.
 *
 * Rather than mass-rename `$log->logInfo()` → `$log->info()` across
 * a dozen cronjobs (a wide blast radius for a "replace one lib"
 * step), this shim re-exposes the v0.2 API on top of the v1.2
 * implementation:
 *
 *   - Static factory `KLogger::instance($dir, $severity)`.
 *   - Constructor that accepts MPOS's integer log level (0-8) and
 *     translates it to PSR-3 string log levels for the parent.
 *   - Integer level constants (EMERG, ALERT, CRIT, ERR, WARN,
 *     NOTICE, INFO, DEBUG, OFF, plus FATAL alias).
 *   - logEmerg/logAlert/logCrit/logError/logWarn/logNotice/logInfo/
 *     logDebug/logFatal forwarders to their PSR-3 method counterparts.
 *
 * This file is required by include/autoloader.inc.php in place of
 * the deleted include/lib/KLogger.php. When all callsites have been
 * migrated to PSR-3 in a future phase, this shim and that require
 * line can be removed in one shot.
 */

class KLogger extends \Katzgrau\KLogger\Logger
{
    // -- v0.2 integer severity constants --------------------------------
    const EMERG  = 0;
    const ALERT  = 1;
    const CRIT   = 2;
    const ERR    = 3;
    const WARN   = 4;
    const NOTICE = 5;
    const INFO   = 6;
    const DEBUG  = 7;
    const OFF    = 8;
    const FATAL  = 2;  // v0.2 alias for CRIT

    /**
     * MPOS code calls KLogger::instance($dir, $integerLevel). The Composer
     * lib has no static factory, so re-create it here.
     */
    public static function instance($logDirectory, $severity = self::INFO)
    {
        return new self($logDirectory, $severity);
    }

    /**
     * Translate v0.2's integer severity into the PSR-3 string the
     * parent constructor expects.
     */
    public function __construct($logDirectory, $severity = self::INFO, array $options = [])
    {
        parent::__construct($logDirectory, self::severityToPsr($severity), $options);
    }

    private static function severityToPsr($severity): string
    {
        $map = [
            self::EMERG  => \Psr\Log\LogLevel::EMERGENCY,
            self::ALERT  => \Psr\Log\LogLevel::ALERT,
            self::CRIT   => \Psr\Log\LogLevel::CRITICAL,
            self::ERR    => \Psr\Log\LogLevel::ERROR,
            self::WARN   => \Psr\Log\LogLevel::WARNING,
            self::NOTICE => \Psr\Log\LogLevel::NOTICE,
            self::INFO   => \Psr\Log\LogLevel::INFO,
            self::DEBUG  => \Psr\Log\LogLevel::DEBUG,
            // OFF (8) has no PSR-3 equivalent; let only emergencies through.
            self::OFF    => \Psr\Log\LogLevel::EMERGENCY,
        ];
        return $map[(int)$severity] ?? \Psr\Log\LogLevel::INFO;
    }

    // -- v0.2 method-name forwarders ------------------------------------
    // PHP method names are case-insensitive, so a single lower-case
    // declaration here covers both `logInfo` and `LogInfo` callers.
    public function logEmerg($message)  { $this->emergency($message); }
    public function logAlert($message)  { $this->alert($message); }
    public function logCrit($message)   { $this->critical($message); }
    public function logError($message)  { $this->error($message); }
    public function logWarn($message)   { $this->warning($message); }
    public function logNotice($message) { $this->notice($message); }
    public function logInfo($message)   { $this->info($message); }
    public function logDebug($message)  { $this->debug($message); }
    public function logFatal($message)  { $this->critical($message); }
}
