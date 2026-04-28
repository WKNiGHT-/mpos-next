<?php
$defflip = (!cfip()) ? exit(header('HTTP/1.1 401 Unauthorized')) : 1;

$debug->append('Loading Smarty libraries', 2);
define('SMARTY_DIR', INCLUDE_DIR . '/smarty/libs/');

// Phase 3A: prefer Composer-installed Smarty 5 (namespaced
// \Smarty\Smarty), fall back to the bundled legacy Smarty 3 if
// composer/vendor isn't available. The legacy bundled tree under
// include/smarty/ stays in place untouched as a safety net — only one
// Smarty class is loaded per request, and the namespaced V5 class
// cannot collide with the global-namespace V3 class.
if (class_exists('\Smarty\Smarty')) {
  $debug->append('Instantiating Smarty 5 (Composer)', 3);
  $smarty = new \Smarty\Smarty();
} else {
  $debug->append('Falling back to legacy bundled Smarty 3', 3);
  include(SMARTY_DIR . 'Smarty.class.php');
  $smarty = new Smarty();
}

// Assign our local paths. setTemplateDir/setCompileDir/setCacheDir are
// supported in both V3 and V5, so this works for either branch above.
// Template tree is theme-nested (templates/<theme>/master.tpl), but the
// compile + cache dirs are flat top-level dirs scoped to this app —
// V5's compiled output is incompatible with V3's, so a fresh location
// keeps the two from colliding if the fallback path ever fires.
$debug->append('Define Smarty Paths', 3);
$smarty->setTemplateDir(TEMPLATE_DIR . '/' . THEME . '/');
$smarty->setCompileDir(BASEPATH . '../templates_c/');
$smarty->setConfigDir(BASEPATH . '../configs/');
$smarty_cache_key = md5(serialize($_REQUEST) . serialize(@$_SESSION['USERDATA']['id']));

// Phase 3A: Smarty 5 dropped V3's auto-resolution of arbitrary PHP
// functions inside {if ...} expressions and |modifier chains, and
// removed the plugins_dir auto-discovery that V3 used to find custom
// plugins. We restore both behaviors explicitly here for the V5 path.
// Skipped for the legacy V3 fallback because V3 already handles these.
if ($smarty instanceof \Smarty\Smarty) {
  // PHP built-ins that the bootstrap-theme templates use as modifiers
  // or in {if ...} expressions. Add to this list as new fatals appear.
  foreach (['file_exists', 'count', 'strlen', 'round', 'explode'] as $bcFn) {
    $smarty->registerPlugin('modifier', $bcFn, $bcFn);
  }
  // MPOS custom modifiers historically dropped into the bundled V3
  // plugins dir (their function names follow V3's smarty_modifier_X
  // naming convention). Auto-discover and register them.
  foreach (glob(SMARTY_DIR . 'plugins/modifier.*.php') as $file) {
    $name = preg_replace('/^modifier\.(.+)\.php$/', '$1', basename($file));
    $fn   = "smarty_modifier_{$name}";
    if (!function_exists($fn)) include_once $file;
    if (function_exists($fn)) {
      $smarty->registerPlugin('modifier', $name, $fn);
    }
  }
}

// Optional smarty caching, check Smarty documentation for details
if ($config['smarty']['cache']) {
  $debug->append('Enable smarty cache');
  // Use the variable-class form so this works for both V3's `Smarty`
  // and V5's `\Smarty\Smarty` without referencing a hard class name.
  $smarty->setCaching($smarty::CACHING_LIFETIME_SAVED);
  $smarty->cache_lifetime = $config['smarty']['cache_lifetime'];
  $smarty->setCacheDir(BASEPATH . '../cache/');
  $smarty->escape_html = true;
  $smarty->use_sub_dirs = true;
}

// Load custom smarty plugins
require_once(INCLUDE_DIR . '/lib/smarty_plugins/function.acl.php');
