#!/usr/bin/php -q
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


include(dirname(__FILE__) . '/../autoload.php');


// ensure script can only be run from the command-line
if (!COMMAND_LINE) {
  exit;
}


// allow parameters to be passed via command-line
$params = getopt('a:b:');

if (!empty($params['a'])) {
  $date = $params['a'];

} else {
  // set date
  $date = Digest::getLastIssueDate(null, false);
}


// attempt to load digest data
$issue = Digest::loadDigest($date);


// create digest issue?
if (!$issue) {
  // choose appropriate editor
  $editors = Digest::getUsersByPermission('editor');

  if (isset($editors['team'])) {
    $preselectEditor = 'team';
  } else {
    $preselectEditor = key($editors);
  }

  // set initial data for issue
  $data    = array('id'        => Db::count('digests', array('type' => 'issue')) + 1,
                   'date'      => $date,
                   'type'      => 'issue',
                   'language'  => 'en_US',
                   'author'    => $preselectEditor);

  // insert new digest
  $success = Db::insert('digests', $data, true);
}


// generate stats?
if (empty($issue['stats'])) {
  Enzyme::generateStatsFromDb($date, date('Y-m-d', strtotime($date . ' -1 week')));
}

?>