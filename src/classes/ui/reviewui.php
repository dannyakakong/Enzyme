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


class ReviewUi extends BaseUi {
  public $id      = 'review';

  private $user   = null;


  public function __construct($user = null) {
    if ($user) {
      $this->user = $user;
    } else {
      // load user
      $this->user = new User();
    }

    // set title
    $this->title = _('Review');
  }


  public function draw() {
    // check permission
    if ($buf = App::checkPermission($this->user, 'reviewer')) {
      return $buf;
    }


    // get revision data
    $revisions = Enzyme::getProcessedRevisions('unreviewed', true, null, ' LIMIT 100');

    // attach bug data to revisions
    Enzyme::getBugs($revisions);

    // get developer data
    $developers = Enzyme::getDevelopers($revisions);


    // display revisions
    if (!$revisions) {
      // no 'marked' revisions
      $buf = '<p class="prompt">' .
                _('No revisions available') .
             '</p>';

    } else {
      $buf          = null;
      $counter      = 1;

      foreach ($revisions as $revision) {
        if ($this->filterCommit($revision)) {
          continue;
        }

        $key = 'commit-item-' . $counter++;
        $buf .= Enzyme::displayRevision('review', $key, $revision, $developers, $this->user);
      }
    }

    return $buf;
  }


  public function getScript() {
    return array('/js/frame/reviewui.js');
  }


  public function getStyle() {
    return array('/css/frame/reviewui.css');
  }


  public function drawFooter() {
    // draw status/action area
    $buf = Enzyme::statusArea($this->id);

    return $buf;
  }


  private function filterCommit($revision) {
    // filter by path?
    if ($revision['format'] == 'svn') {
      if (!empty($revision['basepath'])) {
        // filter commits by user review areas?
        if (!empty($this->user->paths)) {
          foreach ($this->user->paths as $path) {
            if (strpos($revision['basepath'], $path) !== false) {
              // don't show this commit if in global ignore path, or not in user path
              return false;
            }
          }

          return true;
        }
      }

      return false;

    } else if ($revision['format'] == 'git') {
      if (!empty($this->user->repos)) {
        foreach ($this->user->repos as $repo) {
          if (strpos($revision['repository'], $repo) !== false) {
            // don't show this commit if in global ignore path, or not in user path
            return false;
          }
        }

        return true;
      }
    }
  }
}

?>