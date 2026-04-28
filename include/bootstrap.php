<?php
$defflip = (!cfip()) ? exit(header('HTTP/1.1 401 Unauthorized')) : 1;

// Phase 3A: Composer autoload. Loaded here at the very top of the
// runtime bootstrap (before any class is referenced) so modern Composer
// packages — most importantly the namespaced \Smarty\Smarty 5 used by
// include/smarty.inc.php — are resolvable. Wrapped in a file_exists
// check so the app still parses if `composer install` has not been run
// (you'll just get a "missing dependencies" failure later instead of a
// silent fatal here). Note: include/autoloader.inc.php also calls this
// file, but require_once makes the second call a no-op.
$mposComposerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($mposComposerAutoload)) {
    require_once $mposComposerAutoload;
}
unset($mposComposerAutoload);

// Used for performance calculations
$dStartTime = microtime(true);

define('INCLUDE_DIR', BASEPATH . '../include');
define('CLASS_DIR', INCLUDE_DIR . '/classes');
define('PAGES_DIR', INCLUDE_DIR . '/pages');
define('TEMPLATE_DIR', BASEPATH . '../templates');

$quickstartlink = "<a href='https://github.com/MPOS/php-mpos/wiki/Quick-Start-Guide' title='MPOS Quick Start Guide'>Quick Start Guide</a>";

// Include our configuration (holding defines for the requires)
if (!include_once(INCLUDE_DIR . '/config/global.inc.dist.php')) die('Unable to load base global config from ['.INCLUDE_DIR. '/config/global.inc.dist.php' . '] - '.$quickstartlink);
if (!@include_once(INCLUDE_DIR . '/config/global.inc.php')) die('Unable to load your global config from ['.INCLUDE_DIR. '/config/global.inc.php' . '] - '.$quickstartlink);

// Check for a shared account database and set to default DB if unset
if (!isset($config['db']['shared']['accounts']))
  $config['db']['shared']['accounts'] = $config['db']['name'];
// Check for a shared worker database and set to default DB if unset
if (!isset($config['db']['shared']['workers']))
  $config['db']['shared']['workers'] = $config['db']['name'];
// Check for a shared news database and set to default DB if unset
if (!isset($config['db']['shared']['news']))
  $config['db']['shared']['news'] = $config['db']['name'];

// load our security configs
if (!include_once(INCLUDE_DIR . '/config/security.inc.dist.php')) die('Unable to load base security config from ['.INCLUDE_DIR. '/config/security.inc.dist.php' . '] - '.$quickstartlink);
if (@file_exists(INCLUDE_DIR . '/config/security.inc.php')) include_once(INCLUDE_DIR . '/config/security.inc.php');

// start our session, we need it for smarty caching
session_set_cookie_params(time()+$config['cookie']['duration'], $config['cookie']['path'], $config['cookie']['domain'], $config['cookie']['secure'], $config['cookie']['httponly']);
$session_start = @session_start();
if (!$session_start) {
  session_destroy();
  session_regenerate_id(true);
  session_start();
}
@setcookie(session_name(), session_id(), time()+$config['cookie']['duration'], $config['cookie']['path'], $config['cookie']['domain'], $config['cookie']['secure'], $config['cookie']['httponly']);

// Set the timezone if a user has it set, default UTC
if (isset($_SESSION['USERDATA']['timezone'])) {
  $aTimezones = DateTimeZone::listIdentifiers();
  date_default_timezone_set($aTimezones[$_SESSION['USERDATA']['timezone']]);
} else {
  date_default_timezone_set('UTC');
}

// Our default template to load, pages can overwrite this later
$master_template = 'master.tpl';

// Load Classes, they name defines the $ variable used
// We include all needed files here, even though our templates could load them themself
require_once(INCLUDE_DIR . '/autoloader.inc.php');
