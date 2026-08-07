<?php
require 'vendor/autoload.php';

// Replicates BookmarkIO.write() at https://github.com/shaarli/Shaarli/blob/master/application/bookmark/BookmarkIO.php#L114
// and BookmarkFileService.add() at https://github.com/shaarli/Shaarli/blob/master/application/bookmark/BookmarkFileService.php

require_once 'application/bookmark/LinkUtils.php'; // imports tags_str2array()
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkArray;

if ($argv && $argv[0] && realpath($argv[0]) === __FILE__) {
    // Code below will only be executed when this script is invoked as a CLI,
    // not when served as a web page:
    $phpPrefix = '<?php /* ';
    $phpSuffix = ' */ ?>';
    $json_filepath = $argv[1];
    $json_links = json_decode(file_get_contents($json_filepath), true);
    $bookmarks = new BookmarkArray();
    foreach($json_links as &$json_link) {
        $json_link['created'] = DateTime::createFromFormat(DateTime::ISO8601, $json_link['created']);
        if ($json_link['updated']) {
          $json_link['updated'] = DateTime::createFromFormat(DateTime::ISO8601, $json_link['updated']);
        }
        $bookmark = new Bookmark();
        $bookmark->fromArray($json_link, ' ');
        $bookmark->setId($bookmarks->getNextId());
        $bookmark->validate();
        $bookmarks[$bookmark->getId()] = $bookmark;
    }
    $bookmarks->reorder();
    $data = base64_encode(gzdeflate(serialize($bookmarks)));
    print($data = $phpPrefix . $data . $phpSuffix);
}
