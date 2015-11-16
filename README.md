![Shaarli logo](doc/images/doc-logo.png)

The personal, minimalist, super-fast, no-database delicious clone.

_Do you want to share the links you discover?_
_Shaarli is a minimalist delicious clone that you can install on your own server._
_It is designed to be personal (single-user), fast and handy._

[![](https://img.shields.io/travis/shaarli/Shaarli.svg?label=master)](https://travis-ci.org/shaarli/Shaarli)
[![](https://img.shields.io/travis/shaarli/Shaarli/stable.svg?label=stable)](https://travis-ci.org/shaarli/Shaarli)
[![](https://img.shields.io/github/release/shaarli/shaarli.svg)](https://github.com/shaarli/Shaarli/releases/latest/)
[![Docker repository](https://img.shields.io/docker/pulls/shaarli/shaarli.svg)](https://hub.docker.com/r/shaarli/shaarli/)

[![Join the chat at https://gitter.im/shaarli/Shaarli](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/shaarli/Shaarli)
[![Bountysource](https://www.bountysource.com/badge/team?team_id=19583&style=bounties_received)](https://www.bountysource.com/teams/shaarli/issues)

## Quickstart
- [Wiki/documentation](https://github.com/shaarli/Shaarli/wiki)
- [Bugs/Feature requests/Discussion](https://github.com/shaarli/Shaarli/issues/)

### Demo
You can use this [public demo instance of Shaarli](http://shaarlidemo.tuxfamily.org/Shaarli).
It runs the latest development version of Shaarli and is updated/reset daily.

Login: `demo`; Password: `demo`

### Installation & upgrade
- [Download](https://github.com/shaarli/Shaarli/wiki/Download)
- [Server requirements](https://github.com/shaarli/Shaarli/wiki/Server-requirements)
- [Server configuration](https://github.com/shaarli/Shaarli/wiki/Server-configuration)
- [Shaarli configuration](https://github.com/shaarli/Shaarli/wiki/Shaarli-configuration)

## Features
### Interface
- minimalist design (simple is beautiful)
- FAST
- ATOM and RSS feeds
- views:
    - paginated link list
    - tag cloud
    - picture wall: image and video thumbnails
    - daily: newspaper-like daily digest
    - daily RSS feed
- permalinks for easy reference
- links can be public or private

### Tag, view and search your links!
- add a custom title and description to archived links
- add tags to classify and search links
    - features tag autocompletion, renaming, merging and deletion
- full-text and tag search

### Easy setup
- dead-simple installation: drop the files, open the page
- links are stored in a file
    - compact storage
    - no database required
    - easy backup: simply copy the datastore file
- import and export links as Netscape bookmarks

### Accessibility
- Firefox bookmarlet to share links in one click
- support for mobile browsers
- works with Javascript disabled
- easy page customization through HTML/CSS/RainTPL

### Security
- bruteforce-proof login form
- protected against [XSRF](http://en.wikipedia.org/wiki/Cross-site_request_forgery)
and session cookie hijacking

### Goodies
- thumbnail generation for images and video services:
dailymotion, flickr, imageshack, imgur, vimeo, xkcd, youtube...
    - lazy-loading with [bLazy](http://dinbror.dk/blazy/)
- [PubSubHubbub](https://code.google.com/p/pubsubhubbub/) protocol support
- URL cleanup: automatic removal of `?utm_source=...`, `fb=...`
- discreet pop-up notification when a new release is available

### Other usages
Though Shaarli is primarily a bookmarking application, it can serve other purposes
(see [usage examples](https://github.com/shaarli/Shaarli/wiki#usage-examples)):
- micro-blogging
- pastebin
- online notepad
- snippet archive

## About
### Shaarli community fork
This friendly fork is maintained by the Shaarli community at https://github.com/shaarli/Shaarli

This is a community fork of the original [Shaarli](https://github.com/sebsauvage/Shaarli/) project by [Sébastien Sauvage](http://sebsauvage.net/).

The original project is currently unmaintained, and the developer [has informed us](https://github.com/sebsauvage/Shaarli/issues/191)
that he would have no time to work on Shaarli in the near future.
The Shaarli community has carried on the work to provide
[many patches](https://github.com/shaarli/Shaarli/compare/sebsauvage:master...master)
for [bug fixes and enhancements](https://github.com/shaarli/Shaarli/issues?q=is%3Aclosed+)
in this repository, and will keep maintaining the project for the foreseeable future, while keeping Shaarli simple and efficient.

### Contributing
If you'd like to help, please:
- have a look at the open [issues](https://github.com/shaarli/Shaarli/issues)
and [pull requests](https://github.com/shaarli/Shaarli/pulls)
- feel free to report bugs (feedback is much appreciated)
- suggest new features and improvements to both code and [documentation](https://github.com/shaarli/Shaarli/wiki)
- propose solutions to existing problems
- submit pull requests :-)


### License
Shaarli is [Free Software](http://en.wikipedia.org/wiki/Free_software). See [COPYING](COPYING) for a detail of the contributors and licenses for each individual component.
