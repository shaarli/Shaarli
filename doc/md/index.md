# Shaarli

_The personal, minimalist, super fast, database-free, bookmarking service._

Do you want to share the links you discover? Shaarli is a minimalist bookmark manager and link sharing service that you can install on your own server. It is designed to be personal (single-user), fast and handy.

Visit the pages in the sidebar to find information on how to setup, use, configure, tweak and troubleshoot Shaarli.

* [GitHub project page](https://github.com/shaarli/Shaarli)
* [Documentation](https://shaarli.readthedocs.io/)
* [Changelog](https://github.com/shaarli/Shaarli/blob/master/CHANGELOG.md)


[![](https://i.imgur.com/8wEBRSG.png)](https://i.imgur.com/WWPfSj0.png) [![](https://i.imgur.com/93PpLLs.png)](https://i.imgur.com/V09kAQt.png) [![](https://i.imgur.com/rrsjWYy.png)](https://i.imgur.com/TZzGHMs.png) [![](https://i.imgur.com/8iRzHfe.png)](https://i.imgur.com/sfJJ6NT.png) [![](https://i.imgur.com/GjZGvIh.png)](https://i.imgur.com/QsedIuJ.png) [![](https://i.imgur.com/TFZ9PEq.png)](https://i.imgur.com/KdtF8Ll.png) [![](https://i.imgur.com/tVvD3gH.png)](https://i.imgur.com/zGF4d6L.jpg)

```{toctree}
:maxdepth: 1
:hidden:

Server-configuration.md
Installation.md
Reverse-proxy.md
Docker.md
Shaarli-configuration.md
Usage.md
Backup-and-restore.md
Upgrade-and-migration.md
Community-and-related-software.md
Plugins.md
REST-API.md
Troubleshooting.md
dev/Development.md
```

## Demo

You can use this [public demo instance of Shaarli](https://demo.shaarli.org).
It runs the latest development version of Shaarli and is updated/reset daily.

Login: `demo`; Password: `demo`


## Getting started

- [Configure your server](Server-configuration.md)
- [Install Shaarli](Installation.md)
- Or install Shaarli using [Docker](Docker.md)


## Features

Shaarli can be used:

- to share, comment and save interesting links
- to bookmark useful/frequent links and share them between computers
- as a minimal blog/microblog/writing platform
- as a read-it-later/todo list
- as a notepad to draft and save articles/posts/ideas
- as a knowledge base to keep notes, documentation and code snippets
- as a shared clipboard/notepad/pastebin between computers
- as playlist manager for online media
- to feed other blogs, aggregators, social networks...

### Edit, view and search your links

- Editable URL, title, description, tags, private/public status for all your [Shaares](Usage.md)
- [Tags](Usage.md#tags) to organize your Shaares
- [Search](Usage.md#search) in all fields
- Unique [permalinks](Usage.md#permalinks) for easy reference
- Paginated Shaares list view (with image and video thumbnails)
- [Tag cloud/list](Usage.md#tag-cloud) views
- [Picture wall](Usage.md#picture-wall)/thumbnails view (with lazy loading)
- [ATOM and RSS feeds](Usage.md#rss-feeds) (can also be filtered using tags or text search)
- [Daily](Usage.md#daily): newspaper-like daily digest (and daily RSS feed)
- URL cleanup: automatic removal of `?utm_source=...`, `fb=...` tracking parameters
- Extensible through [plugins](Plugins.md)
- Easily extensible by any client using the [REST API](REST-API.md) exposed by Shaarli
- Bookmarklet and [other tools](Community-and-related-software.md) to share links in one click
- Responsive/support for mobile browsers, degrades gracefully with Javascript disabled
- LDAP (single-user) login support


### Easy setup

- Dead-simple [installation](Installation.md): drop the files on your server, open the page
- Shaares are stored in a file (no database required, easy [backup](Backup-and-restore.md))
- [Configurable](Shaarli-configuration.md) from dialog and configuration file
- Extensible through third-party [plugins and themes](Community-and-related-software.md)


### Fast

- Fast! Small datastore file, write-once/read-many, served most of the time from OS disk caches (no disk I/O)
- Stays fast with even tens of thousands shaares!


### Self-hosted

- Shaarli is an alternative to commercial services such as StumbleUpon, Delicio.us, Diigo...
- The data is yours, [import and export](Usage.md#import-export) it to HTML bookmarksformat compatible with most web browser, and from a variety of formats
- Shaarli does not send any telemetry/metrics/private information to developers
- Shaarli is Free and Open-Source software, inspect and change how the program works in the [source code](https://github.com/shaarli/Shaarli)
- Built-in [Security](dev/Development.md#security) features to help you protect your Shaarli instance


## About

This [community fork](https://github.com/shaarli/Shaarli) of the original [Shaarli](https://github.com/sebsauvage/Shaarli/) project by [Sébastien Sauvage](https://sebsauvage.net/) (now [unmaintained](https://github.com/sebsauvage/Shaarli/issues/191)) has carried on the work to provide [many patches](https://github.com/sebsauvage/Shaarli/compare/master...shaarli:Shaarli:master) for [bug fixes and enhancements](https://github.com/shaarli/Shaarli/issues?q=is%3Aclosed+) in this repository, and will keep maintaining the project for the foreseeable future, while keeping Shaarli simple and efficient.

The original Shaarli instance is still available [here](https://sebsauvage.net/links/) (+25000 shaares!)


### Contributing and getting help

Feedback is very appreciated! Feel free to propose solutions to existing problems, help us improve the documentation and [translations](dev/Development.md#translations), and submit pull requests :-)

See [Support](Troubleshooting.md#support) to get in touch with the Shaarli community.


### License

Shaarli is [Free Software](https://en.wikipedia.org/wiki/Free_software). See [COPYING](https://github.com/shaarli/Shaarli/blob/master/COPYING) for a detail of the contributors and licenses for each individual component. A list of contributors is available [here](https://github.com/shaarli/Shaarli/blob/master/AUTHORS).
