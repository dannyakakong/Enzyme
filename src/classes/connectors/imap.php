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


class Imap extends Connector {
  public function __construct($repo) {
    // setup summary, repo details
    parent::__construct($repo);
  }


  public function setupInsertRevisions() {
    // setup summary
    parent::setupInsertRevisions();
  }


  public function insertRevisions() {
    // check we have initialised
    if ($this->initialised != 'insertRevisions') {
      throw new Exception('Call setupInsertRevisions() on this object first');
    }

    $hostname = '{' . $this->repo['hostname'] . ':' . $this->repo['port'] . '/imap/ssl}';

    // connect to inbox
    $inbox = imap_open($hostname, $this->repo['username'], $this->repo['password'], null, 1) or die(imap_last_error());

    // get all emails
    $emails = imap_search($inbox, 'ALL');

    if ($emails) {
      // get date of last published issue (+ 14 day safety margin) for commit comparison?
      if (Config::getSetting('enzyme', 'AUTO_REVIEW_COMMITS')) {
        $lastPublishedIssue = strtotime(Digest::getLastIssueDate(null, true, true)) - 1209600;
      }

      foreach ($emails as $emailNumber) {
        // initialise
        $parsed['commit']       = array();
        $parsed['commitFiles']  = array();
        $parsed['tmp']          = array();
        $parsed['tmp']['paths'] = array();


        // get message header
        $header = imap_fetch_overview($inbox, $emailNumber, 0);
        $header = reset($header);


        // check if we should include this commit
        // (kde-commits mailing list prefixes subjects with [ when it is a Git commit...)
        if (!isset($header->subject) || trim($header->subject[0]) != '[') {
          // delete email message
          imap_delete($inbox, $emailNumber);

          // increment summary counter
          ++$this->summary['skipped']['value'];
          continue;

        } else {
          // set repository name and type
          $tmp                            = explode(']', $header->subject);
          $parsed['commit']['repository'] = ltrim(reset($tmp), '[');
          $parsed['commit']['format']     = 'git';
        }


        // get message body
        $bodyText = trim(imap_fetchbody($inbox, $emailNumber, 1));
        $body     = explode("\n", quoted_printable_decode($bodyText));


        // use parser based on inferred format
        $delimiter = strpos($bodyText, 'diff ');
        if ($delimiter === false) {
          $delimiter = strlen($bodyText);
        }

        if (strpos(substr($bodyText, 0, $delimiter), 'Date: ') !== false) {
          $parseSuccess = $this->parseFormat1($inbox, $emailNumber, $body, $parsed);

        } else {
          $parseSuccess = $this->parseFormat2($inbox, $emailNumber, $body, $parsed);
        }


        // determine base commit path
        $parsed['commit']['basepath'] = Enzyme::getBasePath($parsed['tmp']['paths']);


        // insert modified/added/deleted files?
        if ($parseSuccess && $parsed['commitFiles']) {
          foreach ($parsed['commitFiles'] as $commitFile) {
            // add revision ID
            $commitFile['revision'] = $parsed['commit']['revision'];

            // insert into db
            Db::insert('commit_files', $commitFile, true);
          }
        }


        if ($parseSuccess) {
          // check for issues which should be logged
          if (strpos($parsed['commit']['developer'], 'Committed on') !== false) {
            Log::error('Could not extract developer account for ' . $parsed['commit']['revision'], false, $body);
          }

          // insert commit into database
          Db::insert('commits', $parsed['commit'], true);

          // report successful process/insertion
          Ui::displayMsg(sprintf(_('Processed revision %s'), $parsed['commit']['revision']));

          // auto-mark as reviewed?
          Enzyme::autoReview($parsed, $lastPublishedIssue);

          // delete email message
          imap_delete($inbox, $emailNumber);

          // increment summary counter
          ++$this->summary['processed']['value'];

        } else {
          // report failed process/insertion
          Ui::displayMsg(sprintf(_('Failed to process revision %s'), $parsed['commit']['revision']), 'error');

          // increment summary counter
          ++$this->summary['failed']['value'];
        }
      }
    }


    // close connection to inbox
    imap_close($inbox);
  }


  private function parseFormat1(&$inbox, $emailNumber, $body, &$parsed) {
    // initialise line counter
    $i = 0;

    // check for added/deleted files at start of message
    while (isset($body[$i]) && (substr($body[$i], 0, 6) != 'commit') && (substr($body[$i], 0, 10) != 'Git commit')) {
      $body[$i] = trim($body[$i]);

      if (!empty($body[$i])) {
        // extract path and operation
        $tmp = preg_split('/\s+/', $body[$i], -1, PREG_SPLIT_NO_EMPTY);

        if (!empty($tmp[1])) {
          $tmpPath = '/' . $tmp[1];

          $parsed['commitFiles'][$tmpPath]  = array('operation' => $tmp[0],
                                                    'path'      => $tmpPath);

          // remember path so we can calculate basepath later
          $parsed['tmp']['paths'][] = $tmpPath;
        }
      }

      ++$i;
    }


    // revision
    if (!isset($body[$i])) {
      // log
      return false;
    }


    // extract revision
    preg_match('/[a-z0-9]{40}/', $body[$i], $matches);
    $parsed['commit']['revision'] = reset($matches);


    // extract branch?
    if (substr($body[$i + 1], 0, 6) == 'branch') {
      $parsed['commit']['branch'] = trim(str_replace('branch ', null, $body[++$i]));
    }


    // account for merges
    if (substr($body[$i + 1], 0, 5) == 'Merge') {
      $fileDelimiter = 'diff --cc';
      ++$i;

    } else {
      // no merge found
      $fileDelimiter = 'diff --git';
    }


    // developer
    $tmp = explode('<', $body[$i + 1]);
    $tmp = rtrim(str_replace('>', null, end($tmp)));

    $parsed['commit']['developer'] = Enzyme::getDeveloperInfo('account', $tmp, 'email');

    if (empty($parsed['commit']['developer'])) {
      // cannot find email => username, log and set as email address
      $parsed['commit']['developer'] = $tmp;
    }


    // extract date
    $parsed['tmp']['date']    = strtotime(trim(str_replace('Date:', null, $body[$i + 2])));
    $parsed['commit']['date'] = date('Y-m-d H:i:s', $parsed['tmp']['date']);


    // extract message text
    $i            = $i + 3;
    $totalLines   = count($body);

    $parsed['commit']['msg'] = null;

    while (isset($body[$i]) &&
           (strpos($body[$i], $fileDelimiter) === false) &&
           ($i < $totalLines)) {

      $parsed['commit']['msg'] .= $body[$i] . "\n";
      ++$i;
    }

    $parsed['commit']['msg'] = Enzyme::processCommitMsg($parsed['commit']['revision'], trim($parsed['commit']['msg']));


    // get modified files
    while ($i < $totalLines) {
      if (strpos($body[$i], 'diff --git') !== FALSE) {
        $tmp         = preg_split('/\s+/', $body[$i]);
        $tmp         = ltrim($tmp[2], 'a');

        // add to files list?
        if (!isset($parsed['commitFiles'][$tmp])) {
          $parsed['commitFiles'][$tmp]  = array('operation' => 'M',
                                                'path'      => $tmp);

          // remember path so we can calculate basepath later
          $parsed['tmp']['paths'][] = $tmp;
        }
      }

      ++$i;
    }


    return true;
  }


  private function parseFormat2(&$inbox, $emailNumber, $body, &$parsed) {
    // initialise line counter
    $i = 0;


    // extract revision
    preg_match('/[a-z0-9]{40}/', $body[$i], $matches);
    $parsed['commit']['revision'] = reset($matches);


    // extract date
    if (isset($body[$i + 1]) && (strpos($body[$i + 1], 'Committed on ') !== false)) {
      $pattern        = array('Committed on ', 'at');
      $replace        = null;

      $extractedDate  = preg_split('/[ \/:]/', trim(str_replace($pattern, $replace, $body[$i + 1]), '.'), null, PREG_SPLIT_NO_EMPTY);

      if (count($extractedDate) != 5) {
        // protect against errors in date parsing
        return false;

      } else if (strlen($extractedDate[2]) == 2) {
        // convert into 4 digit year format
        if (intval($extractedDate[2]) > 30) {
          $extractedDate[2] = intval('19' . $extractedDate[2]);
        } else {
          $extractedDate[2] = intval('20' . $extractedDate[2]);
        }
      }

      $parsed['commit']['date'] = date('Y-m-d H:i:s', mktime($extractedDate[3],
                                                      intval($extractedDate[4]),
                                                      0,
                                                      $extractedDate[1],
                                                      $extractedDate[0],
                                                      $extractedDate[2]));

      $parsed['tmp']['date'] = strtotime($parsed['commit']['date']);

    } else {
      // get date from headers
      $header                   = imap_header($inbox, $emailNumber);

      $parsed['tmp']['date']    = strtotime($header->date);
      $parsed['commit']['date'] = date('Y-m-d H:i:s', $parsed['tmp']['date']);
    }


    // protect against errors in date parsing
    if (!$parsed['tmp']['date']) {
      return false;
    }


    // extract developer and branch
    while (isset($body[$i + 1]) && (substr($body[$i + 1], 0, 6) !== 'Pushed')) {
      // handle text breaking onto next line
      ++$i;
    }

    if (isset($body[$i + 1])) {
      $tmp = preg_split('/\s+/', $body[$i + 1]);

      $parsed['commit']['developer']  = $tmp[2];
      $parsed['commit']['branch']     = end($tmp);

    } else {
      // cannot find developer / branch, return error
      return false;
    }


    // pattern for file diff listings
    $filePattern  = '/[MADI]{1}\s+[\+\-][0-9]{0,4}\s+[\+\-][0-9]{0,4}/';


    // extract message text
    $i += 3;
    $totalLines = count($body);

    $parsed['commit']['msg']  = null;

    while (isset($body[$i]) &&
           (preg_match($filePattern, $body[$i]) != 1) &&
           (substr($body[$i], 0, 7) != 'http://')) {

      $parsed['commit']['msg'] .= rtrim($body[$i], '=') . "\n";
      ++$i;
    }

    $parsed['commit']['msg'] = Enzyme::processCommitMsg($parsed['commit']['revision'], trim($parsed['commit']['msg']));


    // get modified files
    while ($i < $totalLines) {
      if (preg_match($filePattern, $body[$i]) == 1) {
        $tmp      = preg_split('/\s+/', $body[$i]);
        $tmpFile  = trim($tmp[3]);

        if (substr($tmpFile, -1) == '=') {
          // filename has split onto 2 lines, handle this
          $tmpFile = rtrim($tmpFile, '=') . rtrim(rtrim(rtrim($body[++$i]), '='));
        }

        // add to files list?
        if (!isset($parsed['commitFiles'][$tmpFile])) {
          $parsed['commitFiles'][$tmpFile]  = array('operation' => $tmp[0],
                                                    'path'      => $tmpFile);

          // remember path so we can calculate basepath later
          $parsed['tmp']['paths'][] = $tmpFile;
        }

      } else if (substr($body[$i], 0, 7) == 'http://') {
        // no more files
        break;
      }

      ++$i;
    }

    return true;
  }
}

?>