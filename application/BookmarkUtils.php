<?php
/**
 * Bookmark import utilities
 */

/**
 * Generates a Javascript alert call to display the import status
 *
 * @param string $filename    name of the file to import
 * @param int    $filesize    size of the file to import
 * @param int    $importCount how many links were imported
 */
function generate_import_notification($filename, $filesize, $importCount=0)
{
    $alert = '<script>alert("File '.$filename;
    $alert .= ' ('.$filesize.' bytes) ';
    if ($importCount == 0) {
        $alert .= 'has an unknown file format. Nothing was imported.';
    } else {
        $alert .= 'was successfully processed: '.$importCount.' links imported.';
    }
    $alert .= '");document.location=\'?\';</script>';
    return $alert;
}

/**
 * Imports bookmark from an uploaded file
 *
 * @param array $globals the $GLOBALS array
 * @param array $post    the $_POST array
 * @param array $files   the $_FILES array
 */
function import_bookmark_file($globals, $post, $files)
{
    $linkDb = new LinkDB(
        $globals['config']['DATASTORE'],
        isLoggedIn(),
        $globals['config']['HIDE_PUBLIC_LINKS'],
        $globals['redirector']
    );

    $filename = $files['filetoupload']['name'];
    $filesize = $files['filetoupload']['size'];
    $data = file_get_contents($files['filetoupload']['tmp_name']);

    // Sniff file type
    if (! startsWith($data, '<!DOCTYPE NETSCAPE-Bookmark-file-1>')) {
        echo generate_import_notification($filename, $filesize);
        return;
    }

    // Should the links be imported as private?
    $private = (empty($post['private']) ? 0 : 1);

    // Should the imported links overwrite existing ones?
    $overwrite = !empty($post['overwrite']);

    $importCount = 0;

    // Standard Netscape-style bookmark file
    foreach (explode('<DT>', $data) as $html) {
        $link = array(
            'linkdate' => '',
            'title' => '',
            'url' => '',
            'description' => '',
            'tags' => '',
            'private' => 0
        );

        $d = explode('<DD>', $html);

        if (startswith($d[0], '<A ')) {
            if (isset($d[1])) {
                // Get description (optional)
                $link['description'] = html_entity_decode(trim($d[1]), ENT_QUOTES, 'UTF-8');
            }

            // Get title
            preg_match('!<A .*?>(.*?)</A>!i',$d[0],$matches);
            $link['title'] = (isset($matches[1]) ? trim($matches[1]) : '');
            $link['title'] = html_entity_decode($link['title'], ENT_QUOTES, 'UTF-8');

            // Get all other attributes
            preg_match_all('! ([A-Z_]+)=\"(.*?)"!i', $html, $matches, PREG_SET_ORDER);

            $raw_add_date = 0;

            foreach ($matches as $m) {
                $attr = $m[1];
                $value = $m[2];
                if ($attr == 'HREF') {
                    $link['url'] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                } else if ($attr == 'ADD_DATE') {
                    $raw_add_date = intval($value);
                    if ($raw_add_date > 30000000000) {
                        // If larger than year 2920, then was likely stored in milliseconds
                        // instead of seconds
                        $raw_add_date /= 1000;
                    }
                } elseif ($attr == 'PRIVATE') {
                    $link['private'] = ($value == '0' ? 0 : 1);
                } elseif ($attr == 'TAGS') {
                    $link['tags'] = html_entity_decode(
                        str_replace(',', ' ', $value),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                }
            }
            if ($link['url'] != '') {
                if ($private == 1) {
                    $link['private'] = 1;
                }
                // Check if the link is already in the datastore
                $dblink = $linkDb->getLinkFromUrl($link['url']);

                if ($dblink == false) {
                   // Link not in database, let's import it...
                   if (empty($raw_add_date)) {
                       // Bookmark file with no ADD_DATE
                       $raw_add_date = time();
                   }

                   /* Make sure date/time is not already used by another link.
                    * Some bookmark files have several different links with the same ADD_DATE
                    *
                    * We increment date by 1 second until we find a date which is not used in DB,
                    * so that links that have the same date/time are more or less kept grouped
                    * by date, but do not conflict.
                    */
                   while (! empty($linkDb[date('Ymd_His', $raw_add_date)])) {
                       $raw_add_date++;
                   }
                   $link['linkdate'] = date('Ymd_His', $raw_add_date);
                   $linkDb[$link['linkdate']] = $link;
                   $importCount++;
                } else if ($overwrite) {
                    // If overwrite is required, we import link data, except date/time.
                    $link['linkdate'] = $dblink['linkdate'];
                    $linkDb[$link['linkdate']] = $link;
                    $importCount++;
                }
            }
        }
    }
    $linkDb->savedb($globals['config']['PAGECACHE']);
    echo generate_import_notification($filename, $filesize, $importCount);
}
