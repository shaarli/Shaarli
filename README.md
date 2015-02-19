![Shaarli logo](https://cdn.mediacru.sh/W2NGCIHB3quT.png)

Shaarli, the personal, minimalist, super-fast, no-database delicious clone.

You want to share the links you discover ? Shaarli is a minimalist delicious clone you can install on your own website.
It is designed to be personal (single-user), fast and handy.

[![Join the chat at https://gitter.im/shaarli/Shaarli](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/shaarli/Shaarli?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

## Features:

[![Join the chat at https://gitter.im/shaarli/Shaarli](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/shaarli/Shaarli?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

 * Minimalist design (simple is beautiful)
 * **FAST**
 * Dead-simple installation: Drop the files, open the page. No database required.
 * Easy to use: Single button in your browser to bookmark a page (**bookmarklet**)
 * Save **URL, title, description** (unlimited size).
 * Classify, search and filter links with **tags**.
  * Tag autocompletion, renaming, merging and deletion.
 * Save links as **public or private**
 * Browse links by page, filter by tag or use the **full text search engine**
 * **Tag cloud**
 * **Picture wall** (which can be filtered by tag or text search)
 * **“Daily”** Newspaper-like digest, browsable by day.
 * **Permalinks** (with QR-Code) for easy reference
 * **RSS** and ATOM feeds
  * Can be filtered by tag or text search!
  * “Daily” RSS feed: Get each day a digest of all new links.
 * Can **import/export** Netscape bookmarks (for import/export from/to Firefox, Opera, Chrome, Delicious…)
 * Automatic **image/video thumbnails** for various services (imgur, imageshack.us, flickr, youtube, vimeo, dailymotion…)
 * Support for http/ftp/file/apt/magnet protocol links
  * URLs in descriptions are automatically converted to clickable links in descriptions
 * Easy backup (Data stored in a single file)
 * 1-click access to your private links/notes
 * Compact storage (1315 links stored in 150 kb)
 * Mobile browsers support
 * Also works with javascript disabled
 * Brute force protected login form
 * [PubSubHubbub](https://code.google.com/p/pubsubhubbub/) protocol support
 * Automatic removal of annoying FeedBurner/Google FeedProxy parameters in URL (?utm_source…)
 * Pages are easy to customize (using CSS and simple RainTPL templates)
 * Protected against [XSRF](http://en.wikipedia.org/wiki/Cross-site_request_forgery), session cookie hijacking.
 * You will be automatically notified by a discreet popup if a new version is available
 * **Shaarli is a bookmarking application, but you can use it for micro-blogging (like Twitter), a pastebin, an online notepad, a snippet repository, etc. See [Usage examples](https://github.com/shaarli/Shaarli/wiki#usage-examples)**


## Links

 * **[Wiki/documentation](https://github.com/shaarli/Shaarli/wiki)**
 * [Bugs/Feature requests/Discussion](https://github.com/shaarli/Shaarli/issues/)


## Installing

Shaarli requires php 5.1

 * Download the latest stable release from https://github.com/shaarli/Shaarli/releases
 * Unpack the archive in a directory on your web server
 * Visit this directory from a web browser.
 * Choose login, password, timezone and page title. Save.

_To get the development version, download https://github.com/shaarli/Shaarli/archive/master.zip or `git clone https://github.com/shaarli/Shaarli`_


## Upgrading

Delete all files and directories except the `data` directory, then unzip the new version of Shaarli.  
You will not lose your links and you will not have to reconfigure it.



## About

This friendly fork is maintained by the community at https://github.com/shaarli/Shaarli

This is a community fork of the original [Shaarli](https://github.com/sebsauvage/Shaarli/) project by [sebsauvage](http://sebsauvage.net/). The original project is currently unmaintained, and the developer [has informed us](https://github.com/sebsauvage/Shaarli/issues/191) that he would have no time to work on Shaarli in the near future. The Shaarli community has carried on the work to provide [many patches](https://github.com/shaarli/Shaarli/compare/sebsauvage:master...master) for [bug fixes and enhancements](https://github.com/shaarli/Shaarli/issues?q=is%3Aclosed+) in this repository, and will keep maintaining the project for the foreseeable future, while keeping Shaarli simple and efficient. If you'd like to help, have a look at the current [issues](https://github.com/shaarli/Shaarli/issues) and [pull requests](https://github.com/shaarli/Shaarli/pulls) and feel free to report bugs and feature requests, propose solutions to existing problems and send us pull requests. 


## License

Shaarli is [Free Software](http://en.wikipedia.org/wiki/Free_software). See [COPYING](COPYING) for a detail of the contributors and licenses for each individual component.

