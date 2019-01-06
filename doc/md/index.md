# <img src="images/icon.png" width="20px" height="20px"> Shaarli

The personal, minimalist, super-fast, database free, bookmarking service.

Do you want to share the links you discover?
Shaarli is a minimalist bookmark manager and link sharing service that you can install on your own server.
It is designed to be personal (single-user), fast and handy.

<!-- TODO screenshots -->

Visit the pages in the sidebar to find information on how to setup, use, configure, tweak and troubleshoot Shaarli.


* [GitHub project page](https://github.com/shaarli/Shaarli)
* [Online documentation](https://shaarli.readthedocs.io/)
* [Latest releases](https://github.com/shaarli/Shaarli/releases)
* [Changelog](https://github.com/shaarli/Shaarli/blob/master/CHANGELOG.md)


### Demo

You can use this [public demo instance of Shaarli](https://demo.shaarli.org).
It runs the latest development version of Shaarli and is updated/reset daily.

Login: `demo`; Password: `demo`

## Features

Shaarli can be used:

- to share, comment and save interesting links and news
- to bookmark useful/frequent links and share them between computers
- as a minimal blog/microblog/writing platform
- as a read-it-later list
- to draft and save articles/posts/ideas
- to keep notes, documentation and code snippets
- as a shared clipboard/notepad/pastebin between machines
- as a todo list
- to store media playlists
- to keep extracts/comments from webpages that may disappear.
- to keep track of ongoing discussions
- to feed other blogs, aggregators, social networks... using RSS feeds

### Edit, view and search your links

- Minimalist design
- FAST
- Customizable link titles and descriptions
- Tags to organize your links (features tag autocompletion, renaming, merging and deletion)
- Search by tag or using the full-text search
- Public and private links (visible only to logged-in users)
- Unique permalinks for easy reference
- Paginated link list (with image and video thumbnails)
- Tag cloud and list views
- Picture wall: image and video thumbnails view (with lazy loading)
- ATOM and RSS feeds (can also be filtered using tags or text search)
- Daily: newspaper-like daily digest (and daily RSS feed)
- URL cleanup: automatic removal of `?utm_source=...`, `fb=...`
- Extensible through [plugins](https://shaarli.readthedocs.io/en/master/Plugins/#plugin-usage)

### Easy setup

- Dead-simple installation: drop the files, open the page
- Links are stored in a file (no database required, easy backup: simply copy the datastore file)
- Import and export links as Netscape bookmarks compatible with most Web browsers

### Accessibility

- Bookmarklet and other tools to share links in one click
- Support for mobile browsers
- Degrades gracefully with Javascript disabled
- Easy page customization through HTML/CSS/RainTPL

### Security

- Discreet pop-up notification when a new release is available
- Bruteforce protection on the login form
- Protected against [XSRF](http://en.wikipedia.org/wiki/Cross-site_request_forgery) and session cookie hijacking

<!-- TODO Limitations -->

### REST API

- Easily extensible by any client using the REST API exposed by Shaarli ([API documentation](http://shaarli.github.io/api-documentation/)).

## About

### Shaarli community fork

This friendly fork is maintained by the Shaarli community at <https://github.com/shaarli/Shaarli>

This is a community fork of the original [Shaarli](https://github.com/sebsauvage/Shaarli/) project by [Sébastien Sauvage](http://sebsauvage.net/).

The original project is currently unmaintained, and the developer [has informed us](https://github.com/sebsauvage/Shaarli/issues/191) that he would have no time to work on Shaarli in the near future.

The Shaarli community has carried on the work to provide [many
patches](https://github.com/shaarli/Shaarli/compare/sebsauvage:master...master) for
[bug fixes and enhancements](https://github.com/shaarli/Shaarli/issues?q=is%3Aclosed+)
in this repository, and will keep maintaining the project for the foreseeable
future, while keeping Shaarli simple and efficient.


### Contributing and getting help

Feedback is very appreciated!

- If you have any questions or ideas, please join the [chat](https://gitter.im/shaarli/Shaarli) (also reachable via [IRC](https://irc.gitter.im/)), post them in our [general discussion](https://github.com/shaarli/Shaarli/issues/308) or read the current [issues](https://github.com/shaarli/Shaarli/issues).
- Have a look at the open [issues](https://github.com/shaarli/Shaarli/issues) and [pull requests](https://github.com/shaarli/Shaarli/pulls)
- If you would like a feature added to Shaarli, check the issues labeled [`feature`](https://github.com/shaarli/Shaarli/labels/feature), [`enhancement`](https://github.com/shaarli/Shaarli/labels/enhancement), and [`plugin`](https://github.com/shaarli/Shaarli/labels/plugin).
- If you've found a bug, please create a [new issue](https://github.com/shaarli/Shaarli/issues/new).
- Feel free to propose solutions to existing problems, help us improve the documentation and translations, and submit pull requests :-)


### License

Shaarli is [Free Software](http://en.wikipedia.org/wiki/Free_software). See
[COPYING](https://github.com/shaarli/Shaarli/blob/master/COPYING) for a detail
of the contributors and licenses for each individual component. A list of
contributors is available
[here](https://github.com/shaarli/Shaarli/blob/master/AUTHORS).

