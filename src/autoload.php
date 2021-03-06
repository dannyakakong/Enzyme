<?php

/*-------------------------------------------------------+
 | Enzyme
 | Copyright 2010-2011 Danny Allen <danny@enzyme-project.org>
 | http://www.enzyme-project.org/
 +--------------------------------------------------------+
 | This program is released as free software under the
 | Affero GPL license. You can redistribute it and/or
 | modify it under the terms of this license which you
 | can read by viewing the included agpl.txt or online
 | at www.gnu.org/licenses/agpl.html. Removal of this
 | copyright header is strictly prohibited without
 | written permission from the original author(s).
 +--------------------------------------------------------*/


// set initial values
if (empty($_SERVER['DOCUMENT_ROOT'])) {
  define('COMMAND_LINE',      true);
  define('BASE_DIR',          dirname(__FILE__));

} else {
  define('COMMAND_LINE',      false);
  define('BASE_DIR',          rtrim($_SERVER['DOCUMENT_ROOT'], '/'));

  // set protocol
  if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) {
    define('PROTOCOL',        'https://');
  } else {
    define('PROTOCOL',        'http://');
  }

  define('BASE_URL',          PROTOCOL . $_SERVER['HTTP_HOST']);
  define('BASE_URL_HTTP',     'http://' . $_SERVER['HTTP_HOST']);
  define('BASE_URL_HTTPS',    'https://' . $_SERVER['HTTP_HOST']);
}


// add class dirs to include path
$classDirs = array(BASE_DIR . '/classes/db/',
                   BASE_DIR . '/classes/shared/',
                   BASE_DIR . '/classes/specific/',
                   BASE_DIR . '/classes/ui/',
                   BASE_DIR . '/classes/connectors/',
                   BASE_DIR . '/classes/ext/');


// setup autoloader
if (COMMAND_LINE) {
  ini_set('display_errors', true);

  function __autoload($class) {
    global $classDirs;

    // assist case-sensitive filesystems
    $class = strtolower($class);

    foreach ($classDirs as $dir) {
      if (is_file($dir . $class . '.php')) {
        include($dir . $class . '.php');
        break;
      }
    }
  }

} else {
  set_include_path(get_include_path() . PATH_SEPARATOR . implode(PATH_SEPARATOR, $classDirs));

  // define autoloader
  spl_autoload_register();
}


// make APP_ID's consistently available
define('DIGEST_APP_ID',       'commit-digest');
define('ENZYME_APP_ID',       Config::$app['id']);

define('JAVASCRIPT_LIBRARY',  'prototype');


// stop APC cache slam errors
ini_set('apc.slam_defense', 'Off');


// ensure get variables can always be accessed, even when provided in q= format
if (isset($_GET['q'])) {
  $tmp = explode('=', $_GET['q']);

  if (isset($tmp[1])) {
    $_GET[$tmp[0]] = $tmp[1];
  } else {
    $_GET[$tmp[0]] = true;
  }
}


// connect to database
$databaseExists = Db::connect();

if (($_SERVER['SCRIPT_NAME'] == '/js/index.php') ||
    ($_SERVER['SCRIPT_NAME'] == '/get/setup.php') ||
    ($_SERVER['SCRIPT_NAME'] == '/get/setup-database.php') ||
    ($_SERVER['SCRIPT_NAME'] == '/get/setup-user.php')) {

  // common script and setup handler need to bypass database check (used in setup!)
  define('LANGUAGE', 'en_US');
  define('TIMEZONE', 'Europe/London');

} else {
  if (!$databaseExists) {
    // database doesn't exist, run setup (with database creation stage)
    $setup = new SetupUi(true);

    echo Ui::drawHtmlPage($setup->drawPage(),
                          Config::$app['name'] . ' - ' . _('Setup'),
                          array('/css/includes/common.css', '/css/frame/setupui.css'),
                          array_merge(array('/js/prototype.js', '/js/effects.js', '/js/index.php?script=common'), $setup->getScript()));
    exit;

  } else {
    // load Enzyme settings
    $settings = Enzyme::loadSettings(true);

    if (!$settings) {
      // run setup
      $setup = new SetupUi();

      echo Ui::drawHtmlPage($setup->drawPage(),
                            Config::$app['name'] . ' - ' . _('Setup'),
                            array('/css/includes/common.css', '/css/frame/setupui.css'),
                            array_merge(array('/js/prototype.js', '/js/effects.js', '/js/index.php?script=common'), $setup->getScript()));
      exit;

    }
  }
}


// define environment settings
if (COMMAND_LINE) {
  // set command line vars (error reporting, etc)
  error_reporting(E_ALL);
  define('LIVE_SITE', null);

} else {
  // set general site vars
  error_reporting(E_ALL|E_STRICT);

  // start user session
  session_start();

  // set environment (live / development)
  if ($_SERVER['HTTP_HOST'] == Config::$app['id']) {
    define('LIVE_SITE', false);
  } else {
    define('LIVE_SITE', true);
  }
}


// set live site vars
if (LIVE_SITE) {
  ini_set('display_errors', false);
  ini_set('log_errors',     true);
} else {
  ini_set('display_errors', true);
  ini_set('log_errors',     false);
}


// set timezone
date_default_timezone_set(Config::getSetting('locale', 'TIMEZONE'));

?>
