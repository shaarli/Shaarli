###Download CSS styles for shaarlis listed in an opml file
Example php script:

```
<!---- ?php -->
<!---- Copyright (c) 2014 Nicolas Delsaux (https://github.com/Riduidel) -->
<!---- License: zlib (http://www.gzip.org/zlib/zlib_license.html) -->

/**
 * Source: https://github.com/Riduidel
 * Download css styles for shaarlis listed in an opml file
 */
define("SHAARLI_RSS_OPML", "https://www.ecirtam.net/shaarlirss/custom/people.opml");

define("THEMES_TEMP_FOLDER", "new_themes");

if(!file_exists(THEMES_TEMP_FOLDER)) {
	mkdir(THEMES_TEMP_FOLDER);
}

function siteUrl($pathInSite) {
	$indexPos = strpos($pathInSite, "index.php");
	if(!$indexPos) {
		return $pathInSite;
	} else {
		return substr($pathInSite, 0, $indexPos);
	}
}

function createShaarliHashFromOPMLL($opmlFile) {
	$result = array();
	$opml = file_get_contents($opmlFile);
	$opmlXml = simplexml_load_string($opml);
	$outlineElements = $opmlXml->xpath("body/outline");
	foreach($outlineElements as $site) {
		$siteUrl = siteUrl((string) $site['htmlUrl']);
		$result[$siteUrl]=((string) $site['text']);
	}
	return $result;
}

function getSiteFolder($url) {
	$domain = parse_url($url,  PHP_URL_HOST);
	return THEMES_TEMP_FOLDER."/".str_replace(".", "_", $domain);
}

function get_http_response_code($theURL) {
     $headers = get_headers($theURL);
     return substr($headers[0], 9, 3);
}

/**
 * This makes the code PHP-5 only (particularly the call to "get_headers")
 */
function copyUserStyleFrom($url, $name, $knownStyles) {
	$userStyle = $url."assets/user.css";
	if(in_array($url, $knownStyles)) {
		// TODO add log message
	} else {
		$statusCode = get_http_response_code($userStyle);
		if(intval($statusCode)<300) {
			$styleSheet = file_get_contents($userStyle);
			$siteFolder = getSiteFolder($url);
			if(!file_exists($siteFolder)) {
				mkdir($siteFolder);
			}
			if(!file_exists($siteFolder.'/user.css')) {
				// Copy stylesheet
				file_put_contents($siteFolder.'/user.css', $styleSheet);
			}
			if(!file_exists($siteFolder.'/README.md')) {
				// Then write a readme.md file
				file_put_contents($siteFolder.'/README.md', 
					"User style from ".$name."\n"
					."============================="
					."\n\n"
					."This stylesheet was downloaded from ".$userStyle." on ".date(DATE_RFC822)
					);
			}
			if(!file_exists($siteFolder.'/config.ini')) {
				// Write a config file containing useful informations
				file_put_contents($siteFolder.'/config.ini', 
					"site_url=".$url."\n"
					."site_name=".$name."\n"
					);
			}
			if(!file_exists($siteFolder.'/home.png')) {
				// And finally copy generated thumbnail
				$homeThumb = $siteFolder.'/home.png';
				file_put_contents($siteFolder.'/home.png', file_get_contents(getThumbnailUrl($url)));
			}
			echo 'Theme have been downloaded from  <a href="'.$url.'">'.$url.'</a> into '.$siteFolder
				.'. It looks like <img src="'.$homeThumb.'"><br/>';
		}
	}
}

function getThumbnailUrl($url) {
	return 'http://api.webthumbnail.org/?url='.$url;
}

function copyUserStylesFrom($urlToNames, $knownStyles) {
	foreach($urlToNames as $url => $name) {
		copyUserStyleFrom($url, $name, $knownStyles);
	}
}

/**
 * Reading directory list, courtesy of http://www.laughing-buddha.net/php/dirlist/
 * @param directory the directory we want to list files of
 * @return a simple array containing the list of absolute file paths. Notice that current file (".") and parent one("..")
 * are not listed here
 */
function getDirectoryList ($directory)  {
    $realPath = realpath($directory);
    // create an array to hold directory list
    $results = array();
    // create a handler for the directory
    $handler = opendir($directory);
    // open directory and walk through the filenames
    while ($file = readdir($handler)) {
        // if file isn't this directory or its parent, add it to the results
        if ($file != "." && $file != "..") {
			$results[] = realpath($realPath . "/" . $file);
        }
    }
    // tidy up: close the handler
    closedir($handler);
    // done!
    return $results;
}

/**
 * Start in themes folder and look in all subfolders for config.ini files. 
 * These config.ini files allow us not to download styles again and again
 */
function findKnownStyles() {
	$result = array();
	$subFolders = getDirectoryList("themes");
	foreach($subFolders as $folder) {
		$configFile = $folder."/config.ini";
		if(file_exists($configFile)) {
			$iniParameters = parse_ini_file($configFile);
			array_push($result, $iniParameters['site_url']);
		}
	}
	return $result;
}

$knownStyles = findKnownStyles();
copyUserStylesFrom(createShaarliHashFromOPMLL(SHAARLI_RSS_OPML), $knownStyles);

<!--- ? ---->
```
