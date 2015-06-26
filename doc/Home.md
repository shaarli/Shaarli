# Shaarli wiki

Welcome to the [Shaarli](https://github.com/shaarli/Shaarli/) wiki! Here you can find some info on how to use, configure, tweak and solve problems with your Shaarli. For general info, read the [README](https://github.com/shaarli/Shaarli/blob/master/README.md).

If you have any questions or ideas, please join the [chat](https://gitter.im/shaarli/Shaarli) (also reachable via [IRC](https://irc.gitter.im/)), post them in our [general discussion](https://github.com/shaarli/Shaarli/issues/44) or read the current [issues](https://github.com/shaarli/Shaarli/issues). If you've found a bug, please create a [new issue](https://github.com/shaarli/Shaarli/issues/new).

If you'd like a feature added, see if it fits in the list of [Ideas for Plugins](Ideas-for-plugins) and update the corresponding bug report.

_Note: This documentation is available online at https://github.com/shaarli/Shaarli/wiki, and locally in the `doc/` directory of your Shaarli installation._

----------------------------------------------------------------------------------

<!-- MarkdownTOC depth=3 -->

- [Basic Usage](#basic-usage)
	- [Add the sharing button (_bookmarklet_) to your browser](#add-the-sharing-button-_bookmarklet_-to-your-browser)
	- [Share links using the _bookmarklet_](#share-links-using-the-_bookmarklet_)
- [Other usage examples](#other-usage-examples)
	- [Using Shaarli as a blog, notepad, pastebin...](#using-shaarli-as-a-blog-notepad-pastebin)
	- [RSS Feeds or Picture Wall for a specific search/tag](#rss-feeds-or-picture-wall-for-a-specific-searchtag)
- [Configuration](#configuration)
	- [Main data/options.php file](#main-dataoptionsphp-file)
	- [Changing theme](#changing-theme)
	- [Changing template](#changing-template)
- [Backup](#backup)
- [Troubleshooting](#troubleshooting)
	- [I forgot my password !](#i-forgot-my-password-)
	- [I'm locked out - Login bruteforce protection](#im-locked-out---login-bruteforce-protection)
	- [List of all login attempts](#list-of-all-login-attempts)
	- [Exporting from Diigo](#exporting-from-diigo)
	- [Importing from SemanticScuttle](#importing-from-semanticscuttle)
	- [Importing from Mister Wong](#importing-from-mister-wong)
	- [Hosting problems](#hosting-problems)
	- [Dates are not properly formatted](#dates-are-not-properly-formatted)
	- [Problems on CentOS servers](#problems-on-centos-servers)
	- [My session expires ! I can't stay logged in](#my-session-expires--i-cant-stay-logged-in)
	- [`Sessions do not seem to work correctly on your server`](#sessions-do-not-seem-to-work-correctly-on-your-server)
	- [pubsubhubbub support](#pubsubhubbub-support)
- [Notes](#notes)
	- [Various hacks](#various-hacks)
	- [Changing timestamp for a link](#changing-timestamp-for-a-link)
- [Related software](#related-software)
- [Other links](#other-links)
- [FAQ](#faq)
	- [Why did you create Shaarli ?](#why-did-you-create-shaarli-)
	- [Why use Shaarli and not Delicious/Diigo ?](#why-use-shaarli-and-not-deliciousdiigo-)
	- [What does Shaarli mean ?](#what-does-shaarli-mean-)
- [Technical details](#technical-details)
	- [Directory structure](#directory-structure)
	- [Why not use a real database ? Files are slow !](#why-not-use-a-real-database--files-are-slow-)
- [Wiki - TODO](#wiki---todo)

<!-- /MarkdownTOC -->



------------------------------------------------------------------

# Basic Usage

### Add the sharing button (_bookmarklet_) to your browser

 * Open your Shaarli and `Login`
 * Click the `Tools` button in the top bar
 * Drag the **`✚Shaare link` button**, and drop it to your browser's bookmarks bar.

_This bookmarklet button in compatible with Firefox, Opera, Chrome and Safari. Under Opera, you can't drag'n drop the button: You have to right-click on it and add a bookmark to your personal toolbar._

![](images/bookmarklet.png)

### Share links using the _bookmarklet_

 * When you are visiting a webpage you would like to share with Shaarli, click the _bookmarklet_ you just added.
 * A window opens.
  * You can freely edit title, description, tags... to find it later using the text search or tag filtering.
  * You will be able to edit this link later using the ![](https://raw.githubusercontent.com/shaarli/Shaarli/master/images/edit_icon.png) edit button.
  * You can also check the “Private” box so that the link is saved but only visible to you. 
 * Click `Save`.**Voila! Your link is now shared.**




# Other usage examples
Shaarli can be used:

 * to share, comment and save interesting links and news
 * to bookmark useful/frequent personal links (as private links) and share them between computers
 * as a minimal blog/microblog/writing platform (no character limit)
 * as a read-it-later list (for example items tagged `readlater`)
 * to draft and save articles/ideas
 * to keep code snippets
 * to keep notes and documentation
 * as a shared clipboard between machines
 * as a todo list
 * to store playlists (e.g. with the `music` or `video` tags)
 * to keep extracts/comments from webpages that may disappear
 * to keep track of ongoing discussions (for example items tagged `discussion`)
 * [to feed RSS aggregators](http://shaarli.chassegnouf.net/?9Efeiw) (planets) with specific tags
 * to feed other social networks, blogs... using RSS feeds and external services (dlvr.it, ifttt.com ...)

### Using Shaarli as a blog, notepad, pastebin...

 * Go to your Shaarli setup and log in
 * Click the `Add Link` button
 * To share text only, do not enter any URL in the corresponding input field and click `Add Link`
 * Pick a title and enter your article, or note, in the description field; add a few tags; optionally check `Private` then click `Save`
 * Voilà!  Your article is now published (privately if you selected that option) and accessible using its permalink.


### RSS Feeds or Picture Wall for a specific search/tag
It is possible to filter RSS/ATOM feeds and Picture Wall on a Shaarli to **only display results of a specific search, or for a specific tag**. For example, if you want to subscribe only to links tagged `photography`:
 * Go to the desired Shaarli instance.
 * Search for the `photography` tag in the _Filter by tag_ box. Links tagged `photography` are displayed.
 * Click on the `RSS Feed` button.
 * You are presented with an RSS feed showing only these links. Subscribe to it to receive only updates with this tag.
 * The same method **also works for a full-text search** (_Search_ box) **and for the Picture Wall** (want to only see pictures about `nature`?)
 * You can also build the URL manually: `https://my.shaarli.domain/?do=rss&searchtags=nature`, `https://my.shaarli.domain/links/?do=picwall&searchterm=poney`

![](rss-filter-1.png) ![](rss-filter-2.png)

# Configuration

### Main data/options.php file

To change the configuration, create the file `data/options.php`, example: 
```
    <?php
    $GLOBALS['config']['LINKS_PER_PAGE'] = 30;
    $GLOBALS['config']['HIDE_TIMESTAMPS'] = true;
    $GLOBALS['config']['ENABLE_THUMBNAILS'] = false;  
    ?>
```

**Do not edit config options in index.php! Your changes would be lost when you upgrade.** The following parameters are available (parameters (default value)):

 * `DATADIR ('data')` : This is the name of the subdirectory where Shaarli stores is data file. You can change it for better security.
 * `CONFIG_FILE ($GLOBALS['config']['DATADIR'].'/config.php')` : Name of file which is used to store login/password.
 * `DATASTORE ($GLOBALS['config']['DATADIR'].'/datastore.php')` : Name of file which contains the link database.
 *  `LINKS_PER_PAGE (20)` : Default number of links per page displayed.
 *  `IPBANS_FILENAME ($GLOBALS['config']['DATADIR'].'/ipbans.php')` : Name of file which records login attempts and IP bans.
 *  `BAN_AFTER (4)` : An IP address will be banned after this many failed login attempts.
 *  `BAN_DURATION (1800)` : Duration of ban (in seconds). (1800 seconds = 30 minutes)
 *  `OPEN_SHAARLI (false)` : If you set this option to true, anyone will be able to add/modify/delete/import/exports links without having to login.
 *   `HIDE_TIMESTAMPS (false)` : If you set this option to true, the date/time of each link will not be displayed (including in RSS Feed).
 *   `ENABLE_THUMBNAILS (true)` : Enable/disable thumbnails.
 *   `RAINTPL_TMP (tmp/)` : Raintpl cache directory  (keep the trailing slash!)
 *   `RAINTPL_TPL (tpl/) : Raintpl template directory (keep the trailing slash!). Edit this option if you want to change the rendering template (page structure) used by Shaarli. See [Changing template](#changing-template)
 *   `CACHEDIR ('cache')` : Directory where the thumbnails are stored.
 *   `ENABLE_LOCALCACHE (true)` : If you have a limited quota on your webspace, you can set this option to false: Shaarli will not generate thumbnails which need to be cached locally (vimeo, flickr, etc.). Thumbnails will still be visible for the services which do not use the local cache (youtube.com, imgur.com, dailymotion.com, imageshack.us)
 *   `UPDATECHECK_FILENAME ($GLOBALS['config']['DATADIR'].'/lastupdatecheck.txt')` : name of the file used to store available shaarli version.
 *   `UPDATECHECK_INTERVAL (86400)` : Delay between new Shaarli version check. 86400 seconds = 24 hours. Note that if you do not login for a week, Shaarli will not check for new version for a week.
 * `ENABLE_UPDATECHECK`: Determines whether Shaarli check for new releases at https://github.com/shaarli/Shaarli
 * `SHOW_ATOM (false)` : Show an `ATOM Feed` button next to the `Subscribe` (RSS) button. ATOM feeds are available at the address `?do=atom` regardless of this option.
 * `ARCHIVE_ORG (false)` : For each link, display a link to an archived version on archive.org
 * `ENABLE_RSS_PERMALINKS (true)`: choose whether the RSS item title link points directly to the link, or to the entry on Shaarli (permalink). `true` is the original Shaarli bahevior (point directly to the link)


### Changing theme
 * Shaarli's apparence can be modified by editing CSS rules in `assets/user.css`. This file allows to override rules defined in the main `assets/shaarli.css` (only add changed rules), or define a whole new theme.
 * Do not edit `assets/shaarli.css`! Your changes would be overriden when updating Shaarli.
 * Some themes are available at https://github.com/shaarli/shaarli-themes.

See also:
 * [Download CSS styles for shaarlis listed in an opml file](https://github.com/shaarli/Shaarli/wiki/Download-CSS-styles-for-shaarlis-listed-in-an-opml-file)

### Changing template

| 💥 |  This feature is currently being worked on and will be improved in the next releases. Experimental.         |
|---------|---------|

 * Find the template you'd like to install. See the list of available templates (TODO). Find it's git clone URL or download the zip archive for the template.
 * In your Shaarli `tpl/` directory, run `git clone https://url/of/my-template/` or unpack the zip archive. There should now be a `my-template/` directory under the `tpl/` dir, containing directly all the template files.
 * Edit `data/options.php` to have Shaarli use this template. Eg.

`$GLOBALS['config']['RAINTPL_TPL'] = 'tpl/my-template/' ;`

You can find a list of compatible templates in [Related Software](#Related-software)

# Backup

You have two ways of backing up your database:
* **Backup the file `data/datastore.php`** (by FTP or SSH). Restore by putting the file back in place.
 * Example command: `rsync -avzP my.server.com:/var/www/shaarli/data/datastore.php datastore-$(date +%Y-%m-%d_%H%M).php`
* **Export your links as HTML** (Menu `Tools` > `Export`). Restore by using the `Import` feature.
 * This can be done using the [shaarchiver](https://github.com/nodiscc/shaarchiver) tool. Example command: `./export-bookmarks.py --url=https://my.server.com/shaarli --username=myusername --password=mysupersecretpassword --download-dir=./ --type=all`


# Troubleshooting

### I forgot my password !

Delete the file data/config.php and display the page again. You will be asked for a new login/password.



### I'm locked out - Login bruteforce protection
Login form is protected against brute force attacks: 4 failed logins will ban the IP address from login for 30 minutes. Banned IPs can still browse links.

To remove the current IP bans, delete the file `data/ipbans.php`

### List of all login attempts

The file `data/log.txt` shows all logins (successful or failed) and bans/lifted bans.
Search for `failed` in this file to look for unauthorized login attempts.


### Exporting from Diigo

If you export your bookmark from Diigo, make sure you use the Delicious export, not the Netscape export. (Their Netscape export is broken, and they don't seem to be interested in fixing it.)

### Importing from SemanticScuttle

To correctly import the tags from a [SemanticScuttle](http://semanticscuttle.sourceforge.net/) HTML export, edit the HTML file before importing and replace all occurences of `tags=` (lowercase) to `TAGS=` (uppercase).

### Importing from Mister Wong
See [this issue](https://github.com/sebsauvage/Shaarli/issues/146) for import tweaks.


### Hosting problems
 * On **free.fr** : Please note that free uses php 5.1 and thus you will not have autocomplete in tag editing.  Don't forget to create a `sessions` directory at the root of your webspace. Change the file extension to `.php5` or create a `.htaccess` file in the directory where Shaarli is located containing:

```
php 1
SetEnv PHP_VER 5
```

 * If you have an error such as: `Parse error: syntax error, unexpected '=', expecting '(' in /links/index.php on line xxx`, it means that your host is using php4, not php5. Shaarli requires php 5.1. Try changing the file extension to `.php5`
 * On **1and1** : If you add the link from the page (and not from the bookmarklet), Shaarli will no be able to get the title of the page. You will have to enter it manually. (Because they have disabled the ability to download a file through HTTP).
 * If you have the error `Warning: file_get_contents() [function.file-get-contents]: URL file-access is disabled in the server configuration in /…/index.php on line xxx`, it means that your host has disabled the ability to fetch a file by HTTP in the php config (Typically in 1and1 hosting). Bad host. Change host. Or comment the following lines:

```
//list($status,$headers,$data) = getHTTP($url,4); // Short timeout to keep the application responsive.
// FIXME: Decode charset according to charset specified in either 1) HTTP response headers or 2) <head> in html 
//if (strpos($status,'200 OK')) $title=html_extract_title($data);
```

 * On hosts which forbid outgoing HTTP requests (such as free.fr), some thumbnails will not work.
 * On **lost-oasis**, RSS doesn't work correctly, because of this message at the begining of the RSS/ATOM feed : `<? // tout ce qui est charge ici (generalement des includes et require) est charge en permanence. ?>`. To fix this, remove this message from `php-include/prepend.php`

### Dates are not properly formatted
Shaarli tries to sniff the language of the browser (using HTTP_ACCEPT_LANGUAGE headers) and choose a date format accordingly. But Shaarli can only use the date formats (and more generaly speaking, the locales) provided by the webserver. So even if you have a browser in French, you may end up with dates in US format (it's the case on sebsauvage.net :-( )

### Problems on CentOS servers
On **CentOS**/RedHat derivatives, you may need to install the `php-mbstring` package.


### My session expires ! I can't stay logged in
This can be caused by several things:

* Your php installation may not have a proper directory setup for session files. (eg. on Free.fr you need to create a  `session` directory on the root of your website.) You may need to create the session directory of set it up.
* Most hosts regularly clean the temporary and session directories. Your host may be cleaning those directories too aggressively (eg.OVH hosts), forcing an expire of the session. You may want to set the session directory in your web root. (eg. Create the `sessions` subdirectory and add `ini_set('session.save_path', $_SERVER['DOCUMENT_ROOT'].'/../sessions');`. Make sure this directory is not browsable !)
* If your IP address changes during surfing, Shaarli will force expire your session for security reasons (to prevent session cookie hijacking). This can happen when surfing from WiFi or 3G (you may have switched WiFi/3G access point), or in some corporate/university proxies which use load balancing (and may have proxies with several external IP addresses).
* Some browser addons may interfer with HTTP headers (ipfuck/ipflood/GreaseMonkey…). Try disabling those.
* You may be using OperaTurbo or OperaMini, which use their own proxies which may change from time to time.
* If you have another application on the same webserver where Shaarli is installed, these application may forcefully expire php sessions.

### `Sessions do not seem to work correctly on your server`
Follow the instructions in the error message. Make sure you are accessing shaarli via a direct IP address or a proper hostname. If you have **no dots** in the hostname (e.g. `localhost` or `http://my-webserver/shaarli/`), some browsers will not store cookies at all (this respects the [HTTP cookie specification](http://curl.haxx.se/rfc/cookie_spec.html)).


### pubsubhubbub support

Download [publisher.php](https://pubsubhubbub.googlecode.com/git/publisher_clients/php/library/publisher.php) at the root of your Shaarli installation and set `$GLOBALS['config']['PUBSUBHUB_URL']` in your `config.php`

# Notes
### Various hacks

 * [Example patch: add a new "via" field for links](Example-patch---add-new-via-field-for-links)
 * [Copy a Shaarli installation over SSH SCP, serve it locally with php cli](Copy-a-Shaarli-installation-over-SSH-SCP,-serve-it-locally-with-php-cli)
 * To display the array representing the data saved in datastore.php, use the following snippet (TODO where is it gone?)

### Changing timestamp for a link
 * Look for `<input type="hidden" name="lf_linkdate" value="{$link.linkdate}">` in `tpl/editlink.tpl` (line 14)
 * Remove `type="hidden"` from this line
 * A new date/time field becomes available in the edit/new link dialog. You can set the timestamp manually by entering it in the format `YYYMMDD_HHMMS`.

```
$data = "tZNdb9MwFIb... <Commented content inside datastore.php>";
$out = unserialize(gzinflate(base64_decode($data)));
echo "<pre>"; // Pretty printing is love, pretty printing is life
print_r($out);
echo "</pre>";
exit;
```
This will output the internal representation of the datastore, "unobfuscated" (if this can really be considered obfuscation)


# Related software
Unofficial but relatedd work on Shaarli. If you maintain one of these, please get in touch with us to help us find a way to adapt your work to our fork. **TODO** contact repos owners to see if they'd like to standardize their work for the community fork.

 * [shaarchiver](https://github.com/nodiscc/shaarchiver) - Archive your Shaarli bookmarks and their content
 * [Shaarli for Android](http://sebsauvage.net/links/?ZAyDzg) - Android application that adds Shaarli as a sharing provider
 * [Shaarlier for Android](https://play.google.com/store/apps/details?id=com.dimtion.shaarlier) - Android application to simply add links directly into your Shaarli
 * [shaarli-river](https://github.com/mknexen/shaarli-river) - an aggregator for shaarlis with many features 
 * [Shaarlo](https://github.com/DMeloni/shaarlo) - an aggregator for shaarlis with many features ([Demo](http://shaarli.fr/))
 * [kalvn/shaarli-blocks](https://github.com/kalvn/shaarli-blocks) - A template/theme for Shaarli
 * [kalvn/Shaarli-Material](https://github.com/kalvn/Shaarli-Material) - 
A theme (template) based on Google's Material Design for Shaarli, the superfast delicious clone.
 * [Vinm/Blue-theme-for Shaarli](https://github.com/Vinm/Blue-theme-for-Shaarli) - A template/theme for Shaarli ([unmaintained](https://github.com/Vinm/Blue-theme-for-Shaarli/issues/2), compatibility unknown)
 * [vivienhaese/shaarlitheme](https://github.com/vivienhaese/shaarlitheme) - A Shaarli fork meant to be run in an openshift instance
 * [tt-rss-shaarli](https://github.com/jcsaaddupuy/tt-rss-shaarli) - [TinyTiny RSS](http://tt-rss.org/) plugin that adds support for sharing articles with Shaarli
 * [dhoko/ShaarliTemplate](https://github.com/dhoko/ShaarliTemplate) - A template/theme for Shaarli
 * [mknexen/shaarli-api](https://github.com/mknexen/shaarli-api) - a REST API for Shaarli
 * [Albinomouse](https://github.com/alexisju/albinomouse-template) - A full template for Shaarli
 * [Shaarlimages](https://github.com/BoboTiG/shaarlimages) - An image-oriented aggregator for Shaarlis
 * [Shaarli Superhero Theme](https://github.com/AkibaTech/Shaarli---SuperHero-Theme) - A template/theme for Shaarli
 * [Limonade](https://github.com/misterair/limonade) - A fork of Shaarli with a new template
 * [octopress-shaarli](https://github.com/ahmet2mir/octopress-shaarli) - octoprress plugin to retrieve SHaarli links on the sidebara
 * [Bookie](https://github.com/bookieio/bookie) - Another self-hostable, Free bookmark sharing software, written in Python
 * [Unmark](https://github.com/plainmade/unmark) -  An open source to do app for bookmarks ([Homepage](https://unmark.it/))



# Other links
 * [Liens en vrac de sebsauvage](http://sebsauvage.net/links/) - the original Shaarli
 * [A large list of Shaarlis](http://porneia.free.fr/pub/links/ou-est-shaarli.html)
 * [A list of working Shaarli aggregators](https://raw.githubusercontent.com/Oros42/find_shaarlis/master/annuaires.json)
 * [A list of some known Shaarlis](https://github.com/Oros42/shaarlis_list)
 * [Adieu Delicious, Diigo et StumbleUpon. Salut Shaarli ! - sebsauvage.net](http://sebsauvage.net/rhaa/index.php?2011/09/16/09/29/58-adieu-delicious-diigo-et-stumbleupon-salut-shaarli-) (fr) _16/09/2011 - the original post about Shaarli_
 * [Original ideas/fixme/TODO page](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:ideas)
 * [Original discussion page](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:discussion) (fr)
 * [Original revisions history](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
 * [Shaarli.fr/my](https://www.shaarli.fr/my.php) - Unofficial, unsupported (old fork) hosted Shaarlis provider, courtesy of [DMeloni](https://github.com/DMeloni)
 * [Shaarli Communauty](http://shaarferme.etudiant-libre.fr.nf/index.php) - Another unofficial Shaarli hoster (unsupported, old fork), hoster unknown




# FAQ

### Why did you create Shaarli ?

I was a StumbleUpon user. Then I got fed up with they big toolbar. I switched to delicious, which was lighter, faster and more beautiful. Until Yahoo bought it. Then the export API broke all the time, delicious became slow and was ditched by Yahoo. I switched to Diigo, which is not bad, but does too much. And Diigo is sslllooooowww and their Firefox extension a bit buggy. And… oh… **their Firefox addon sends to Diigo every single URL you visit** (Don't believe me ? Use [Tamper Data](https://addons.mozilla.org/en-US/firefox/addon/tamper-data/) and open any page).

Enough is enough. Saving simple links should not be a complicated heavy thing. I ditched them all and wrote my own: Shaarli. It's simple, but it does the job and does it well. And my data is not hosted on a foreign server, but on my server.

### Why use Shaarli and not Delicious/Diigo ?

With Shaarli:

*     The data is yours: It's hosted on your server.
*     Never fear of having your data locked-in.
*     Never fear to have your data sold to third party.
*     Your private links are not hosted on a third party server.
*     You are not tracked by browser addons (like Diigo does)
*     You can change the look and feel of the pages if you want.
*     You can change the behaviour of the program.
*     It's magnitude faster than most bookmarking services.

### What does Shaarli mean ?

Shaarli is for shaaring your links.



# Technical details
 * Application is protected against XSRF (Cross-site requests forgery): Forms which act on data (save,delete…) contain a token generated by the server. Any posted form which does not contain a valid token is rejected. Any token can only be used once. Token are attached to the session and cannot be reused in another session.
 * Sessions automatically expires after 60 minutes. Sessions are protected against highjacking: The sessionID cannot be used from a different IP address.
 * An .htaccess file protects the data file.
 * Link database is an associative array which is serialized, compressed (with deflate), base64-encoded and saved as a comment in a .php file. Thus even if the server does not support htaccess files, the data file will still not be readable by URL. The database looks like this:
```
<?php /* zP1ZjxxJtiYIvvevEPJ2lDOaLrZv7o...
...ka7gaco/Z+TFXM2i7BlfMf8qxpaSSYfKlvqv/x8= */ ?>
```

* The password is salted, hashed and stored in the data subdirectory, in a php file, and protected by htaccess. Even if the webserver does not support htaccess, the hash is not readable by URL. Even if the .php file is stolen, the password cannot deduced from the hash. The salt prevents rainbow-tables attacks.
* Shaarli relies on `HTTP_REFERER` for some functions (like redirects and clicking on tags). If you have disabled or masqueraded `HTTP_REFERER` in your browser, some features of Shaarli may not work
* `magic_quotes` is a horrible option of php which is often activated on servers. No serious developer should rely on this horror to secure their code against SQL injections. You should disable it (and Shaarli expects this option to be disabled). Nevertheless, I have added code to cope with magic_quotes on, so you should not be bothered even on crappy hosts.
* Small hashes are used to make a link to an entry in Shaarli. They are unique. In fact, the date of the items (eg.20110923_150523) is hashed with CRC32, then converted to base64 and some characters are replaced. They are always 6 characters longs and use only A-Z a-z 0-9 - _ and @.

### Directory structure

Here is the directory structure of Shaarli and the purpose of the different files:

```
    index.php : Main program.
    COPYING : Shaarli license.
    assets/ : Includes (libraries, CSS…)
        shaarli.css : Shaarli stylesheet.
        jquery.min.js : jQuery javascript library.
        jquery-ui.min.js : jQuery-UI javascript library.
        jquery-MIT-LICENSE.txt: jQuery license.
        jquery.lazyload.min.js: LazyLoad javascript library.
        rain.tpl.class.php : RainTPL templating library.
    tpl/ : RainTPL templates for Shaarli. They are used to build the pages.
    images/ : Images and icons used in Shaarli.
    data/ : Directory where data is stored (bookmark database, configuration, logs, banlist…)
        config.php : Shaarli configuration (login, password, timezone, title…)
        datastore.php : Your link database (compressed).
        ipban.php : IP address ban system data.
        lastupdatecheck.txt : Update check timestamp file (used to check every 24 hours if a new version of Shaarli is available).
        log.txt : login/IPban log.
    cache/ : Directory containing the thumbnails cache. This directory is automatically created. You can erase it anytime you want.
    tmp/ : Temporary directory for compiled RainTPL templates. This directory is automatically created. You can erase it anytime you want.
```

### Why not use a real database ? Files are slow !

Does browsing [this page](http://sebsauvage.net/links/) feel slow ? Try browsing older pages, too.

It's not slow at all, is it ? And don't forget the database contains more than 16000 links, and it's on a shared host, with 32000 visitors/day for my website alone. And it's still damn fast. Why ?

The data file is only 3.7 Mb. It's read 99% of the time, and is probably already in the operation system disk cache. So generating a page involves no I/O at all most of the time.

# Wiki - TODO
 * Translate (new page can be called Home.fr, Home.es ...) and linked from Home
 * add more screenshots
 * add developer documentation (storage architecture, classes and functions, security handling, ...)
 * Contact related projects
 * Add a Table of Contents to the wiki (can be added to the sidebar)

...