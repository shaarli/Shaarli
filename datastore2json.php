<?php
require 'vendor/autoload.php';

// Replicates https://github.com/shaarli/Shaarli/blob/master/application/bookmark/BookmarkIO.php#L72 :

use Shaarli\Bookmark\BookmarkArray;

if ($argv && $argv[0] && realpath($argv[0]) === __FILE__) {
    // Code below will only be executed when this script is invoked as a CLI,
    // not when served as a web page:
    $phpPrefix = '<?php /* ';
    $phpSuffix = ' */ ?>';
    $datastore_filepath = $argc > 1 ? $argv[1] : "data/datastore.php";
    $content = file_get_contents($datastore_filepath);
    $links = unserialize(gzinflate(base64_decode(
        substr($content, strlen($phpPrefix), -strlen($phpSuffix))
    )));
    print(json_encode($links).PHP_EOL);
}
