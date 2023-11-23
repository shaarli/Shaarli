# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [v0.13.0](https://github.com/shaarli/Shaarli/releases/tag/v0.12.3) - 2023-11-22

> Major changes:
>  - Security: Fix XSS vulnerability in tag search
>  - Drop support for PHP 7.1, 7.2 and 7.3

### Added
* Docker build: add ARM64 platform and bump Github action version by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/1965
* github actions: build OCI images that contain both amd64 and armv7 by @nodiscc in https://github.com/shaarli/Shaarli/pull/1962
* Expose tags_separator config through /info API by @amadeous in https://github.com/shaarli/Shaarli/pull/1997
* tools: github actions: build docker images on pull requests by @nodiscc in https://github.com/shaarli/Shaarli/pull/2014
* doc: server configuration: add PHP 8.2 to PHP compatibility table by @nodiscc in https://github.com/shaarli/Shaarli/pull/2021
* Add shaarli-stack theme to Community-and-related-software.md by @dajare in https://github.com/shaarli/Shaarli/pull/2028
* doc: document general.download_max_size/timeout configuration settings by @nodiscc in https://github.com/shaarli/Shaarli/pull/2036
* doc: troubleshooting: automatic title retrieval fails when it is set by javascript by @nodiscc in https://github.com/shaarli/Shaarli/pull/2037

### Changed
* doc: update release procedure (merge the latest release to the release branch) + use the release branch for latest release version detection by @nodiscc in https://github.com/shaarli/Shaarli/pull/1960
* Update german translation by @bschwede in https://github.com/shaarli/Shaarli/pull/1969
* Update Server-configuration.md by @reinboldg in https://github.com/shaarli/Shaarli/pull/1973
* Update Community-and-related-software.md by @nlegaillart in https://github.com/shaarli/Shaarli/pull/1984
* doc: improve docs on usage of OR operator in tags search by @nodiscc in https://github.com/shaarli/Shaarli/pull/1987
* docker: nginx: listen on IPv6 in addition to IPv4 by @cerebrate in https://github.com/shaarli/Shaarli/pull/1983
* Doc update, WebSub (formerly PubSubHubbub) plugin by @clach04 in https://github.com/shaarli/Shaarli/pull/2008
* doc: community/related software/integration with other platforms: add link to shaarli debian package by @nodiscc in https://github.com/shaarli/Shaarli/pull/2018
* replace mkdocs with sphinx/myst-parser for HTML documentation generation, documentation improvements by @nodiscc in https://github.com/shaarli/Shaarli/pull/2025

### Fixed
* Makefile: Use GNU tar if available by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/1957
* Support: ignore disk_free_space if the function is unavailable by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/1970
* Documentation: fix broken link to 3rd party plugins by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/1975
* Fix autofocus: load bulk action input on linklist only by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/1976
* doc: fix mkdocs build warnings/relative links by @nodiscc in https://github.com/shaarli/Shaarli/pull/2015
* correct usage of hyphens in all occurences of 'super fast, database-free' by @nodiscc in https://github.com/shaarli/Shaarli/pull/2003


### Removed
* Drop support for PHP 7.1, 7.2 and 7.3 by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/1958
* doc: themes: remove unmaintained themes by @nodiscc in https://github.com/shaarli/Shaarli/pull/2030
* doc: remove bountysource badge by @nodiscc in https://github.com/shaarli/Shaarli/pull/2035

### Security
* Fix XSS vulnerability in tag search by @ArthurHoaro in https://github.com/shaarli/Shaarli/pull/2039
* tools: run trivy vulnerability scanner on the 'latest' docker image by @nodiscc in https://github.com/shaarli/Shaarli/pull/1980
* github actions: fix value of TRIVY_TARGET_DOCKER_IMAGE by @nodiscc in https://github.com/shaarli/Shaarli/pull/1989
* tools/CI: scan repository with trivy security scanner (yarn.lock, composer.lock) by @nodiscc in https://github.com/shaarli/Shaarli/pull/1998
* tools/tests: update trivy to v0.44.0 by @nodiscc in https://github.com/shaarli/Shaarli/pull/2012
* docker: update base alpine docker image to 3.16.7 by @nodiscc in https://github.com/shaarli/Shaarli/pull/2024

**Full Changelog**: https://github.com/shaarli/Shaarli/compare/v0.12.2...v0.12.3

## [v0.12.2](https://github.com/shaarli/Shaarli/releases/tag/v0.12.2) - 2023-03-18

> Docker: use `ghcr.io/shaarli/shaarli` as Docker image instead of `shaarli/shaarli`.
> The `:master` Docker image has been removed, please use `:latest` instead.
> The `:stable` Docker image has been removed, please use `:release` instead.

## Added

- Bulk action: add or delete tag to multiple bookmarks
- New Core Plugin: ReadItLater
- Plugin system: allow plugins to provide custom routes 
- Support search highlights when matching URL content
- Support for OR (~) and optional AND (+) operators for tag search
- Russian translation
- Chinese translation
- Export:
  - Export: set a bookmark's LAST_MODIFIED attribute to its update timestamp
  - Export: set a bookmark's PRIVATE attribute using an integer value 
- Add an additional free disk space check before saving the datastore
- curl: support HTTP/2 response code header
- CI:
  - Build and push Docker images through Github Actions
  - push container images to github registry in addition to dockerhub
- Documentation: 
  - Add '206 not acceptable' to the Troubleshooting section
  - Add mention to Shaarli Archiver
  - doc: add note to adjust proxy timeouts or PHP max execution time
  - doc: shaarli configuration: mention file:/// URIs 
  - add "formatter" key to example config.json.php

## Changed

- docker latest: replace dev in shaarli_version.php with the latest commit
- Daily RSS Cache: invalidate cache base on the date
- Update Japanese translations
- Update German translations
- Templates: Inject current template name
- format_date: include timezone in IntlDateFormatter object 
- Handle pagination through BookmarkService
- autocapitalize off for username input
- More intuitive label for plugin checkboxes
- Simple and uniform localized website title 
- Use rewrited version of Netscape Bookmark Parser
- tests/makefile: rewrite translate target to be compatible with busybox
- PubSubHub Plugin: make 1 external call per request
- Docker: 
  - newer alpine (for newer PHP) and apk upgrade
  - Dockerfile.armhf: upgrade python2 -> python3
  - Dockerfile: add php8-gettext package
  - update s6 service definition to use php-fpm8
  - install php8-ldap in Docker images
- CI: 
  - use Github Action instead of Travis CI
  - use the yarnpkg command instead of yarn 
  - tools: github actions: fix PHP 8.0 tests
  - github actions: add tests for PHP 8.2
- Documentation: 
  - apache: explicitely ste index.php as DirectoryIndex
  - bookmarklet is now working on github.com
  - LDAP login support, update php requirements list
  - installation/tests: clarify build tools installation procedure
  - doc: PHP extensions are also required for development
  - doc: move OCI images hosting to ghcr.io

## Fixed

- Error handling if the datastore mutex is not working
- Synchronous metadata retrieval is failing in strict mode
- Improve metadata extraction
- Typo: 'Authentication' -> 
- default_colors plugin: update CSS file on color change
- API: POST/PUT Link - properly parse tags string
- Error when using bulk shaare with a single URL 
- Bulk Shaare: 
  - use unique HTML ID
  - error with a single URL
  - redirection with ending slash
- Bug when trying to access ATOM feed without bookmarks
- Documentation build
- pubsubhubbub hub link in RSS / Atom.
- Monthly views previous/next month links during month
- Resolve PHP 8.1 deprecation warnings
- Fix PHP 8 incompatibility with debug mode enabled
- Fixed Roboto-Regular and Roboto-Bold font declarations
- template/vintage: fix typo in visibility selection link
- Do not display deprecated warnings by default
- Fix a bug when using '/' as a tag separator
- Fix Logger exception: gracefully handle permission issue
- Documentation: 
  - plugins.md: fix link casing

## Removed

- Daily RSS: Remove relative description (today, yesterday)
- Documentation: 
  - remove the markdown plugin from the plugins list 
  - remove duplicate "general" key in example config.php.json

## [v0.12.1](https://github.com/shaarli/Shaarli/releases/tag/v0.12.1) - 2020-11-12

> nginx ([#1628](https://github.com/shaarli/Shaarli/pull/1628)) and Apache ([#1630](https://github.com/shaarli/Shaarli/pull/1630)) configurations have been reviewed. It is recommended that you
> update yours using [the documentation](https://shaarli.readthedocs.io/en/master/Server-configuration/).
> Users using official Docker image will receive updated configuration automatically.

### Added
- Bulk creation of bookmarks
- Server administration tool page (and install page requirements)
- Support any tag separator, not just whitespaces
- Share a private bookmark using a URL with a token
- Add a setting to retrieve bookmark metadata asynchronously (enabled by default)
- Highlight fulltext search results
- Weekly and monthly view/RSS feed for daily page
- MarkdownExtra formatter
- Default formatter: add a setting to disable auto-linkification
- Add mutex on datastore I/O operations to prevent data loss
- PHP 8.0 support
- REST API: allow override of creation and update dates
- Add strict types for bookmarks management

### Changed
- Improve regex and performances to extract HTML metadata (title, description, etc.)
- Support using Shaarli without URL rewriting (prefix URL with `/index.php/`)
- Improve the "Manage tags" tools page
- Use PSR-3 logger for login attempts
- Move utils classes to Shaarli\Helper namespace and folder
- Include php-simplexml in Docker image
- Raise 404 error instead of 500 if permalink access is denied
- Display error details even with dev.debug set to false
- Reviewed nginx configuration
- Reviewed Apache configuration
- Replace vimeo link in demo bookmarks due to IP ban on the demo instance
- Apply PSR-12 on code base, and add CI check using PHPCS

### Fixed
- Compatiliby issue on login with PHP 7.1
- Japanese translations update
- Redirect to referrer after bookmark deletion
- Inject ROOT_PATH in plugin instead of regenerating it everywhere
- Wallabag plugin: minor improvements
- REST API postLink: change relative path to absolute path
- Webpack: fix vintage theme images include
- Docker-compose: fix SSL certificate + add parameter for Docker tag

### Removed
- `config.json.php` new lines in prefix/suffix to prevent issues with Windows PHP

## [v0.12.0](https://github.com/shaarli/Shaarli/releases/tag/v0.12.0) - 2020-10-13

**Save you `data/` folder before updating!**

### Added
- Thumbnailer: add soundcloud.com to list of common media domains
- Markdown rendering is now integrated into Shaarli core
- Add autofocus on tag cloud filter input
- Japanese translations
- Japanese translation: add language to admin configuration page
- Support for PHP 8.0
- Support for local anchor URL (starting with `#`)
- LDAP authentication
- Encapsulated PageCacheManager
- Docs:
  - add screenshots of all pages
  - section about mkdocs
  - Ulauncher extension
- CI: run against PHP 7.4
- Added $links_per_page variable to template and display on default
- Inject BookmarkServiceInterface in plugins data
- Add manual configuration for root URL
- Added PATCH to the allowed Apache request methods.
- REST API: compatibility with ionos Apache's headers

### Changed
- Introduce Bookmark object and Service layer
  - Save bookmark as objects in the datastore
  - Handle bookmark as objects across the whole codebase (except templates and plugins)
- Process all Shaarli page through Slim controller, with proper URL rewriting (see #1516)
- Docs: the entire documentation has been reviewed, updated and improved, thanks to @nodiscc!
- ATOM feed: use instance name as author name instead of URL
- Updated French translation
- Default colors plugin: generate CSS file during initialization
- Improve default bookmarks after install
- Upgrade all front end dependencies and webpack build
- Default theme: Make tag cloud/list views buttons more obvious

### Fixed
- Undefined index: thumbnail in daily page
- Undefined index: thumbnail on OpenGraph headers
- Undefined index: updated on linklist
- Make sure that bookmark sort is consistent, even with equal timestamps
- Code PHP version check as requirement bumped to PHP 7.1
- Thumbnail images lazy loading
- Markdown plugin: fix RSS feed direct link reverse
- Fix RSS permalink included in Markdown bloc
- Demo plugin: multiple typos
- Makefile target for releases
- Makefile target for html documentation
- Session cookie setting being set while session is active
- Deprecated use of implode
- Division by zero in tag cloud
- CI: deprecated linux distribution and sudo directive
- Docker build: gcc is no longer included in python alpine image
- Default template: display pin button in mobile view
- Pinned bookmarks are not longer displayed first in ATOM/RSS feeds
- Docs:
  - Outdated Docker documentation for stable branch
  - Outdated links
  - Plugin description in meta files
- docker-compose.yml: pin traefik image to 1.7-alpine

### Removed
- Markdown plugin
- Docs:
  - emojione & twemoji removed
- Makefile: remove static_analysis_summary from all: target
- doc/Makefile: remove references to composer update

## [v0.11.1](https://github.com/shaarli/Shaarli/releases/tag/v0.11.1) - 2019-08-03

Release to fix broken Docker build on the latest version.

### Fixed
- Fixed Docker build
- Fixed a few documentation broken links
- Fixed broken label in configuration page

### Added
- More accessibility improvements

## [v0.11.0](https://github.com/shaarli/Shaarli/releases/tag/v0.11.0) - 2019-07-27

**Shaarli no longer officially support PHP 5.6 and PHP 7.0 as they've reached end of life.**

**Shaarli classes now use namespace, third party plugins need to update.**

### Added
- Add optional PHP extension to composer suggestions.
- composer: enforce PHP security advisories
- phpDocumentor configuration and make target
- Run unit tests against PHP 7.3
- Bunch of accessibility improvements to the default template, thanks to @llune
- Bulk actions: set visibility
- Display sticky label in linklist
- Add print CSS rules to the default template
- New setting to automatically retrieve description for new bookmarks
- Plugin to override default template colors

### Changed
- Shaarli now uses namespaces for its classes.
- Rewrite IP ban management
- Default template: slightly lighten visited link color
- Hide select all button on mobile view
- Switch from FontAwesome v4.x to ForkAwesome
- Daily - display the current day instead of the previous one

### Fixed
- Do not check the IP address with session protection disabled
- API: update test regexes to comply with PCRE2
- Optimize and cleanup imports
- ensure HTML tags are stripped from OpenGraph description
- Documentation invalid links
- Thumbnails disabling if PHP GD is not installed
- Warning if links sticky status isn't set
- Fix button overlapping on mobile in linklist
- Do not try to retrieve thumbnails for internal link
- Update node-sass to fix a vulnerability in node tar dependency
- armhf Dockerfile
- Default template: Responsive issue with delete button fix
- Persist sticky status on bookmark update

### Removed
- Doxygen configuration
- redirector setting
- QRCode link to an external service

## [v0.10.4](https://github.com/shaarli/Shaarli/releases/tag/v0.10.4) - 2019-04-16
### Fixed
- Fix thumbnails disabling if PHP GD is not installed
- Fix a warning if links sticky status isn't set

## [v0.10.3](https://github.com/shaarli/Shaarli/releases/tag/v0.10.3) - 2019-02-23
### Added
- Add OpenGraph metadata tags on permalink page
- Add CORS headers to REST API reponses
- Add a button to toggle checkboxes of displayed links
- Add an icon to the link list when the Isso plugin is enabled
- Add noindex, nofollow to documentation pages
- Document usage of robots.txt
- Add a button to set links as sticky

### Changed
- Update French translation
- Refactor the documentation homepage
- Bump netscape-bookmark-parser
- Update session_start condition
- Improve accessibility
- Cleanup and refactor lint tooling

### Fixed
- Fix input size for dropdown search form
- Fix history for bulk link deletion
- Fix thumbnail requests
- Fix hashtag rendering when markdown escaping is enabled
- Fix AJAX tag deletion
- Fix lint errors and improve PSR-1 and PSR-2 compliance

### Removed
- Remove Firefox Share documentation

## [v0.10.2](https://github.com/shaarli/Shaarli/releases/tag/v0.10.2) - 2018-08-11

### Fixed

- Docker build

## [v0.10.1](https://github.com/shaarli/Shaarli/releases/tag/v0.10.1) - 2018-08-11

### Changed

- Accessibility:
    - Remove alt text on the logo
    - Remove redundant title in tools page

### Fixed

- Fixed an error on the daily page and daily RSS
- Fixed an issue causing 'You are not authorized to add a link' error while logged out
- Fixed thumbnail path when Shaarli's path uses symbolic links
- Add a `mod_version` check in Shaarli's root `.htaccess` file for Apache 2.2 syntax
- Include assets in the release Makefile target

### Removed

- Firefox Social API shaare has been removed

## [v0.10.0](https://github.com/shaarli/Shaarli/releases/tag/v0.10.0) - 2018-07-28
**PHP 5.5 compatibility has been dropped.** Shaarli now requires at least PHP 5.6.

### Added
- Add a filter to display public links only
- Add PHP 7.2 support
- Add German translation
- Resolve front-end dependencies from NPM
- Build front-end bundles with Yarn and Webpack
- Lint Javascript code with ESLint
- Lint SASS code with SASSLint
- Support redirection in cURL download callback
- Introduce multi-stage builds for Docker images
- Use Travis matrix and stages to run Javascript tests in a dedicated environment
- Add tag endpoint in the REST API
- Build the documentation in Travis builds
- Provide a Docker Compose example

### Changed
- Use web-thumbnailer to retrieve thumbnails (see #687)
- Use a specific page title in all pages
- Daily: run hooks before creating the columns
- Load theme translations files automatically
- Make max download size and timeout configurable
- Make Nginx logs accessible as stdout/stderr for Docker images
- Update buttons used to toggle link visibility filters
- Rewrite Javascript code for ES6 compliance
- Refactor IP ban management
- Refactor user login management
- Refactor server-side session management
- Update Doxygen configuration
- Update Parsedown
- Improve documentation
- Docker: build the images from the local sources
- Docker: bump alpine version to 3.7
- Docker: expose a volume for the thumbnail cache

### Removed
- Drop support for PHP 5.5
- Remove vendored front-end libraries
- Remove environment specific .gitignore entries

### Fixed
- Ignore the case while checking DOCTYPE during the file import
- Fix removal of on=... attributes from html generated from Markdown
- httpd: always forward the 'Authorization' header
- Ensure user-specific CSS file is loaded
- Fix feed permalink rendering when Markdown escaping is enabled
- Fix order of tags with the same number of occurrences
- Fixed the referrer meta tag in default template
- Disable MkDocs' strict mode for ReadTheDocs builds to pass
- fix and simplify Dockerfile for armhf

### Security
- Update `.htaccess` to prevent accessing Git metadata when using a Git-based installation


## [v0.9.7](https://github.com/shaarli/Shaarli/releases/tag/v0.9.7) - 2018-06-20
### Changed
- Build the Docker images from the local Git sources


## [v0.9.6](https://github.com/shaarli/Shaarli/releases/tag/v0.9.6) - 2018-03-25
### Changed
- htaccess: prevent accessing resources not managed by SCM
- htaccess: always forward the 'Authorization' HTTP header


## [v0.9.5](https://github.com/shaarli/Shaarli/releases/tag/v0.9.5) - 2018-02-02
### Fixed
- Fix a warning happening when `php-intl` is not installed on the system
- Fix warnings happening when updating from legacy SebSauvage version

## [v0.9.4](https://github.com/shaarli/Shaarli/releases/tag/v0.9.4) - 2018-01-30
### Added
- Enable translations: Shaarli is now also available in French. Other language translations are welcome!
- Add EditorConfig configuration
- Add favicons for mobile devices
- Add Alpine Linux arm32v7 Dockerfiles (master, latest)

### Changed
- Do not write bookmark edition history during file imports (performance)
- Migrate Docker images (master, latest) to Alpine Linux
- Improve unitary tests and code coverage
- Improve thumbnail display
- Improve theme ergonomics
- Improve messages if there is no plugin or parameter available in the admin page
- Increase buffer size for cURL download
- Force HTTPS if the original port is 443 behind a reverse proxy (workaround)
- Improve page title retrieval performances

### Removed
- Remove redirector setting from Configure page

### Fixed
- Fix broken links in the documentation
- Enable access to `data/user.css` (Apache 2.2 & 2.4)
- Don't URL encode description links if parameter `redirector.encode_url` is set to false
- Fix an issue preventing the Save button to appear for plugin parameters


## [v0.9.3](https://github.com/shaarli/Shaarli/releases/tag/v0.9.3) - 2018-01-04
**XSS vulnerability fixed. Please update.**

## Security
- Fix an XSS (cross-site-scripting) vulnerability in `index.php` -
  [CVE-2018-5249](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2018-5249)


## [v0.9.2](https://github.com/shaarli/Shaarli/releases/tag/v0.9.2) - 2017-10-07

**Major security issue fixed. Please update.**

### Added
- Tag search now supports wildcards `*`
- New setting `privacy.force_login` which can be used with `privacy.hide_public_links` to redirect anonymous users to the login page.
- New setting `general.default_note_title` used to override default `Note:` title prefix for notes.
- Add a version hash for asset loading to prevent browser's cache issue

### Changed
- The "Remember me" checkbox is unchecked by default
- The default value of the "Remember me" checkbox can be configured under `data/config.json.php`

### Removed
- Remove obsolete PHP magic quote support

### Fixed
- Generates a permalink URL if the URL is set to blank
- Replace links to the old GitHub wiki with ReadTheDocs URIs
- Use single quotes in the note bookmarklet
- Daily page if there is no link
- Bulk link deletion with a single link
- HTTPS detection behind a reverse proxy
- Travis tests environment and localization
- Improve template paths robustness (trailing slash)
- Robustness: safer gzinflate/zlib usage
- Description links parsing with parenthesis (without Markdown)
- Templates:
    - Sort the tag cloud alphabetically
    - Firefox social title
    - Improved visited link color
    - Fix jumpy textarea with long content in post edit

### Security

- Fixed reflected XSS vulnerability introduced in v0.9.1, discovered by @chb9 ([CVE-2017-15215](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2017-15215)).


## [v0.9.1](https://github.com/shaarli/Shaarli/releases/tag/v0.9.1) - 2017-08-23

The documentation has been migrated to ReadTheDocs:
- https://shaarli.readthedocs.io/
- edits are submitted as pull requests

### Added
- Allow bulk link deletion
- Display subtags in the tag cloud
- Add an endpoint to refresh the token
- Add a token on every page
- Add a tag list view for management
- Add Note bookmarklet
- Add creation date when editing a link

### Changed
- Documentation:
    - Generate static HTML documentation with [mkdocs](http://www.mkdocs.org/)
    - Host documentation on [ReadTheDocs](http://www.mkdocs.org/)
    - Update documentation structure
    - Update Makefile targets to:
        - Build the docs locally
        - Include the generated docs in the release archives
- Theme:
    - Use the new theme as the default
    - Rename the tag cloud template to `tag.cloud.html`
    - Display visited links in grey
    - Use only one search form in `linklist.html`
    - Hide the "search links with these tags" option when an empty `searchtags` is passed to `tag.list.html`
- Improve HTTP header handling when hosting Shaarli with Docker behind a reverse proxy
- Searching for tags with an empty value returns untagged links only
- Set Travis environment to `precise` until the new `trusty` environment is ready

### Removed
- Remove dead Pubsubhubbub code
- Disable the GitHub wiki (see changed/documentation)
- Remove Docker `dev` image and resources
- Theme:
    - Remove the bottom "Sort by" menu in `tag.list.html`

### Fixed
- Fix file existence check for `user.css`
- Limit selection to 2k characters when using the bookmarklet
- Fix JS error `uncaught type error`
- Fix Firefox Social button
- Use pinned PHP dependencies when generating release archives
- Make sure that the tag exists before altering/removing it

### Security
- Add a whitelist for protocols for URLs


## [v0.9.0](https://github.com/shaarli/Shaarli/releases/tag/v0.9.0) - 2017-05-07

This release introduces the REST API, and requires updating HTTP server
configuration to enable URL rewriting, see:
- https://shaarli.github.io/api-documentation/
- https://shaarli.readthedocs.io/en/master/Server-configuration/

**WARNING**: Shaarli now requires PHP 5.5+.

### Added
- REST API v1
    - [Slim](https://www.slimframework.com/) framework
    - [JSON Web Token](https://jwt.io/introduction/) (JWT) authentication
    - versioned API endpoints:
        - `/api/v1/info`: get general information on the Shaarli instance
        - `/api/v1/links`: get a list of shaared links
        - `/api/v1/history`: get a list of latest actions
- Theming:
    - Introduce a new theme
    - Allow selecting themes/templates from the configuration page
    - New/Edit link form can be submitted using CTRL+Enter in the textarea
    - Shaarli version is displayed in the footer when logged in
- Add plugin placeholders to Atom/RSS feed templates
- Add OpenSearch to feed templates
- Add `campaign_` to the URL cleanup pattern list
- Add an AUTHORS file and Makefile target to list authors from Git commit data
- Link imports are now logged in `data/` folder, and can be debug using `dev.debug=true` setting.
- `composer.lock` is now included in git file to allow proper `composer install`
- History mechanism which logs link addition/modification/deletion

### Changed
- Docker: enable nginx URL rewriting for the REST API
- Theming:
    - Move `user.css` to the `data` folder
    - Move default template files to a subfolder (`default`)
    - Rename the legacy theme to `vintage`
    - Private only filter is now displayed as a search parameter
    - Autocomplete: pre-select the first element
    - Display daily date in the page title (browser title)
    - Timezone lists are now passed as an array instead of raw HTML
- Move PubSubHub to a dedicated plugin
- Coding style:
    - explicit method visibility
    - safe boolean comparisons
    - remove unused variables
- The updater now keeps custom theme preferences
- Simplify the COPYING information
- Improved client locale detection
- Improved date time display depending on the locale
- Partial namespace support for Shaarli classes
- Shaarli version is now only present in `shaarli_version.php`
- Human readable maximum file size upload


### Removed
- PHP < 5.5 compatibility
- ReadItYourself plugin

### Fixed
- Ignore generated release tarballs
- Hide default port when behind a reverse proxy
- Fix a typo in the Markdown plugin description
- Fix the presence of empty tags for private tags and in search results
- Fix a fatal error during the install
- Fix permalink image alignment in daily page
- Fix the delete button in `editlink`
- Fix redirection after link deletion
- Do not access LinkDB links by ID before the Updater applies migrations
- Remove extra spaces in the bookmarklet's name
- Piwik plugin: Piwik URL protocol can now be set (http or https)
- All inline JS has been moved to dedicated JS files
- Keep tags after login redirection

### Security
- Markdown plugin: escape HTML entities by default


## [v0.8.7](https://github.com/shaarli/Shaarli/releases/tag/v0.8.7) - 2018-06-20
### Changed
- Build the Docker image from the local Git sources

### Removed
- Disable PHP 5.3 Travis build (unsupported)


## [v0.8.6](https://github.com/shaarli/Shaarli/releases/tag/v0.8.6) - 2018-02-19
### Changed
- Run version check tests against the 'stable' branch


## [v0.8.5](https://github.com/shaarli/Shaarli/releases/tag/v0.8.5) - 2018-01-04
**XSS vulnerability fixed. Please update.**

## Security
- Fix an XSS (cross-site-scripting) vulnerability in `index.php` -
  [CVE-2018-5249](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2018-5249)

## [v0.8.4](https://github.com/shaarli/Shaarli/releases/tag/v0.8.4) - 2017-03-04
### Security
- Markdown plugin: escape HTML entities by default

## [v0.8.3](https://github.com/shaarli/Shaarli/releases/tag/v0.8.3) - 2017-01-20
### Fixed
- PHP 7.1 compatibility: add ConfigManager parameter to anti-bruteforce function call in login template.

## [v0.8.2](https://github.com/shaarli/Shaarli/releases/tag/v0.8.2) - 2016-12-15
### Fixed

- Editing a link created before the new ID system would change its permalink.

## [v0.8.1](https://github.com/shaarli/Shaarli/releases/tag/v0.8.1) - 2016-12-12

> Note: this version will create an automatic backup of your database if anything goes wrong.

### Added
- Add CHANGELOG.md to track the whole project's history
- Enable Composer cache for Travis builds
- Save the last edition date for shaares and use it in Atom/RSS feeds
- Plugins:
    - Add an [Isso](https://posativ.org/isso/) plugin to enable user comments on permalinks
    - Allow defining init functions, e.g. for performing checks and error processing
    - Add a Piwik plugin for analytics.
    - Markdown: add warning notice regarding HTML rendering
- Meta tag to *not* send the referrer to external resources.

### Changed
- Link ID complete refactoring:
    - Links now have a numeric ID instead of dates
    - Short URLs are now created once and can't change over time (previous URL are kept)
- Templates:
    - Changed placeholder behaviour for: `buttons_toolbar`, `fields_toolbar` and `action_plugin`
    - Cleanup `{loop}` declarations in templates
    - Tools: hide Firefox Social button when not in HTTPS
    - Firefox Social: show Shaarli's title when shaaring using Firefox Social
- Release archives now have the same structure as GitHub-generated archives:
    - archives contain a `Shaarli` directory, itself containing sources + dependencies
    - the tarball is now gzipped
- Plugins:
    - Markdown: Parsedown library is now imported through Composer
- Minor code cleanup: PHPDoc, spelling, unused variables, etc.
- Docker: explicitly set the maximum file upload size to 10 MiB

### Fixed
- Fix the server `<self>` value in Atom/RSS feeds
- Plugins:
    - Tools: only display parameter description when it exists
    - archive.org: do not propose archival of private notes
    - Markdown:
        - render links properly in code blocks
        - bug regarding the `nomarkdown` tag
    - W3C compliance
- Use absolute URL for hashtags in RSS and ATOM feeds
- Docker: specify the location of the favicon
- ATOM feed: remove new line between content tag and data

### Security
- Allow whitelisting trusted IPs, else continue banning clients upon login failure


## [v0.8.0](https://github.com/shaarli/Shaarli/releases/tag/v0.8.0) - 2016-10-12
Shaarli now uses [Composer](https://getcomposer.org/) to handle its dependencies.
Please use our release archives, or follow the
[installation documentation](https://github.com/shaarli/Shaarli/wiki/Download-and-Installation).

### Added
- Composer is required to resolve Shaarli's PHP dependencies
- Shaarli now supports `#hashtags`
- Firefox social share now uses selected text as a description
- Plugin parameters can have a description in each plugin's `.meta` file

### Changed
- Configuration is now stored as a JSON file
- Previous configuration format will be automatically updated (PHP -> JSON)
- Shaarli now defaults to cURL to fetch shaare titles
- URL cleanup: remove `PHPSESSID` parameter
-  `nomarkdown` tag is no longer private, and now affects visitors
- Cleanup template indentation
- Rewrite bookmark import using a generic Netscape parser

### Removed
- Shaarli no longer references Delicious in its description

### Deprecated
- Shaarli configuration is not held as PHP globals anymore

### Fixed
- Ignore case for tags in autocompletion and cloud tag
- Avoid generating empty tags
- Fix a Dockerfile syntax error

### Security
- Fixed a bug preventing to change password
- XSRF token now generated each time a page is rendered


## [v0.7.1](https://github.com/shaarli/Shaarli/releases/tag/v0.7.1) - 2017-03-08
### Security
- Markdown plugin: escape HTML entities by default

## [v0.7.0](https://github.com/shaarli/Shaarli/releases/tag/v0.7.0) - 2016-05-14
### Added
- Adds an option to encode redirector URL parameter
- Atom/RSS feeds now support Markdown formatting, and plugins in general
- Markdown: use the tag `.nomarkdown` to avoid markdown processing
- Prefill the login field when the authentication has failed
- Show a private links counter

### Changed
- Allow to use the bookmarklet in Firefox reader view (URL clean up)
- Improve tagcloud font size
- Improve title retrieving
- Markdown: inline code background color
- Refactor Netscape bookmark export
- Refactor Atom/RSS feed generation

### Removed
- Remove delicious from Shaarli description

### Fixed
- Fix bad login redirections causing a 404 in a few cases
- Fix tagcloud font-size with French locale
- Don't display empty tags in tag search
- Fix Awesomeplete conflicts with jQuery
- Fix UTC timezone selection
- Fix a bug preventing to import notes in browsers from bookmarks export
- Don't redirect to ?post if ?addlink is reached while logged out


## [v0.6.5](https://github.com/shaarli/Shaarli/releases/tag/v0.6.5) - 2016-03-02
### Fixed
- Fixes a regression generating an unnecessary warning (language in HTTP request)
- Fixes a bug where going through multiple reverse proxy could generate malformed URL
- Markdown: Fixes a bug where empty description blocks were displayed


## [v0.6.4](https://github.com/shaarli/Shaarli/releases/tag/v0.6.4) - 2016-02-28
### Added
- Add an updater class to automate user data upgrades
- Plugin admin page: adds a label for checkboxes and improve name display
- Plugin Wallabag: API version can be specified in plugin admin page

### Changed
- Better tag cloud sorting, including special chars (`a > E > é > z`)
- Autolocale now sets all locale categories, not just time
- Use PHP's DateTime object instead of custom functions
- Plugin hooks: process includes before header/footer
- Markdown plugin: better styles for `<code>` and `<pre>` tags
- Improve searching:
    - search terms are now considered separated and won't only return exact results anymore
    - exact search can be done with quotes `"this exact sentence"`
    - search supports excluded terms starting a dash `-exclude`
    - implement crossed search: terms + tags
    - all of them combined across all shaare fields
- New tag behaviour:
    - tags starting with a dash will be renamed without it
    - tags starting with a dot `.` will be hidden unless the user is logged in

### Fixed
- Fix Markdown plugin escape issues (code/quote blocks, etc.)
- Link description aren't trimmed anymore to allow markdown format at the beginning of a shaare
- Fixes plugin admin redirection page on error

### Security
- Fix a bug where non initialized variables were causing a warning
- Fix a bug where saving a link after edit could cause a 404 error


## [v0.6.3](https://github.com/shaarli/Shaarli/releases/tag/v0.6.3) - 2016-01-31
### Added
- Plugins administration page
- Markdown plugin added for shaares description
- Docker: Dockerfile is now in the main git repository and improved
- Add a `.gitattributes` to ease repository management
- Travis: include file permission checks

### Changed
- Auto retrieve of title know works with websites (HTTPS, follow redirections, etc.)
- 404 page is now handled in a template
- Date in log files updated to work with fail2ban
- Wallabag: support of Wallabag v2 and minor fixes
- Link search refactoring
- Logging function refactoring

### Fixed
- Fix a bug where renaming a tag was causing a 404
- Fix a bug allowing to search blank terms
- Fix a bug preventing to remove a tag with special chars when searching


## [v0.6.2](https://github.com/shaarli/Shaarli/releases/tag/v0.6.2) - 2015-12-23
### Changed
- Plugins: new footer hook
- Plugins: improve QR code
- Cleanup templates

### Fixed
- Plugins: use the actual link URL to generate QR codes
- Templates: missing/erroneous page titles
- Templates: missing variables resulting in PHP errors

### Security
- Fix invalid file permissions (remove executable bit)


## [v0.6.1](https://github.com/shaarli/Shaarli/releases/tag/v0.6.1) - 2015-12-01
### Added
- Add OpenSearch support
- Add a Doxygen makefile target
- Tools: add fine-grained file/directory permission checks (installation)

### Changed
- Tools: check the 'stable' branch for new versions (updates)
- Cleanup: introduce an `ApplicationUtils` class

### Removed
 - Cleanup: remove `json_encode()` function (built-in since PHP 5.2)

### Fixed
 - Auto-complete more than one tag
 - Bookmarklet: support titles containing quotes
 - URL encode links when setting a redirector


## [v0.6.0](https://github.com/shaarli/Shaarli/releases/tag/v0.6.0) - 2015-11-18
### Added
- Introduce a plugin system
- Add a demo_plugin
- Add plugins:
    - addlink_toolbar
    - archiveorg
    - playvideos
    - qrcode
    - readityourself
    - wallabag

### Changed
- Coding style

### Fixed
- Adding a new link now returns the correct anchor in the URL
- Set default file permissions


## [v0.5.4](https://github.com/shaarli/Shaarli/releases/tag/v0.5.4) - 2015-09-15
### Added
- HTTPS: support being served behing an SSL-enabled proxy

### Changed
- HTTP/Server utilities: refactor & add test coverage
- Project & documentation:
    - improve/rewrite `README.md`
    - update contributor list
    - update `index.php` header

### Fixed
- PHP session IDs: handle hash algorithms and bits per char representations


## [v0.5.3](https://github.com/shaarli/Shaarli/releases/tag/v0.5.3) - 2015-09-02
### Fixed
- Fix a bug that could prevent user to login


## [0.5.3](https://github.com/shaarli/Shaarli/releases/tag/0.5.3) - 2015-09-02
This release has been YANKED as it points to a tag that does not follow our naming convention. Please use `v0.5.3` instead

### Fixed
- Allow uppercase letters in PHP sessionid format


## [v0.5.2](https://github.com/shaarli/Shaarli/releases/tag/v0.5.2) - 2015-08-31
### Added
- Add PHP 7 to Travis platforms

### Changed
- Also extract HTTPS page metadata (title)

### Fixed
- Fix regression preventing to load LinkDB info when adding an existing link

### Security
- Fix Full Path Disclosure upon cookie forgery


## [v0.5.1](https://github.com/shaarli/Shaarli/releases/tag/v0.5.1) - 2015-08-17
### Added
- Add a link to the shaarli/shaarli DockerHub repository

### Changed
- Update local documentation
- Improve timezone detection at installation
- Improve feed cache handling
- Improve URL cleanup for new links

### Fixed
- Fix 404 after editing a link while being logged out


## [v0.5.0](https://github.com/shaarli/Shaarli/releases/tag/v0.5.0) - 2015-07-31
### Added
- Add Firefox Social API
- Start code refactoring:
    - add unit test coverage
    - add Travis integration

### Changed
- Search/Filter by tag fieds can now be accessed quickly with the `Tab` key
- Update documentation
- Remove duplicate tags in links
- Remove annoying URL patterns
- Start code refactoring:
    - move all settings to `data/config.php`
    - refactor Config, LinkDB, TimeZone, Utils

### Fixed
- Fix locale handling
- Fix note URLs
- Fix page redirections
- Fix daily RSS browsing
- Fix title display
- Restore compatibility with PHP 5.3

### Security
- Fix links not being hidden when `HIDE_PUBLIC_LINKS` is set


## [v0.0.45beta](https://github.com/shaarli/Shaarli/releases/tag/v0.0.45beta) - 2015-03-16
### Fixed
- Fix improperly displayed Unicode character
- Fix incorrect font size for "Add link" input field


## [v0.0.44beta](https://github.com/shaarli/Shaarli/releases/tag/v0.0.44beta) - 2015-03-15
### Added
- Add a Makefile to run static code checkers
- Add local documentation (help link in page footer)
- Use awesomplete library for autocompletion
- Use bLazy.js library for images lazy loading
- New 'Add Note' bookmarklet to immediatly open a note (text post) compose window

### Changed
- Theme improvements and cleanup (menu, search fields, icons, linklist...)
- Allow 'javascript:' links sharing (bookmarklets)
- Make update check optional
- Redirect to homepage after adding a link via "Add Link" dialog
- Remove more annoying URL parameters for shared links
- Code cleanup

### Removed
- Remove jQuery

### Security
- Don't disclose version to visitors (shaarli-version.txt)


## [v0.0.43beta](https://github.com/shaarli/Shaarli/releases/tag/v0.0.43beta) - 2015-02-20
### Added
- Title button link URL is now configurable
- RainTPL's TMP and TPL directories path are now configurable
- Displayed URLs for each link are now clickable links
- Show links timestamps in Daily view

### Changed
- Automatically prepend "Note:" to title of self-posts (posts not pointing to an URL)
- Make ATOM toolbar button optional (`SHOW_ATOM` configuration variable)
- Optional archive.org links for each Shaarli link (`ARCHIVE_ORG` option)
- Thumbnails: force HTTPS when possible
- Improve tag cloud font scaling
- Allow pointing RSS items to the permalink instead of the direct URL (`ENABLE_RSS_PERMALINKS` option)
- Update JS libraries and add version numbers in filenames
- Updates to README and footer

### Fixed
- Fix problems when running Shaarli behind a reverse proxy (invalid RSS feed URL)
- Update check now checks against the community fork version
- Include `cache/`, `data/`, `pagecache/` and `tmp/` directories in the repository
- Fix duplicate tag search returning no results
- Fix unnecessary 404 error on "Add link" when the user is logged out
- Fixes to copyright/licensing information and unlicensed media
- Fixes for tag cloud invalid links
- Coding style fixes/cleanup
- Fix redirection after deleteing a link leading to a 404 error
- Shaarli's HTML is now W3C-compliant
- Search now works with Unicode characters

### Security
- Do not leak server's PHP version and Shaarli's full path on errors
- Prevent Shaarli from sending a lot of duplicate cookies


## [v0.0.42beta](https://github.com/shaarli/Shaarli/releases/tag/v0.0.42beta) - 2014-07-27
### Added
- Add QRCode Javascript library
- Allow importing bookmarks with the same timestamp (hack)
- Allow putting a description in the bookmarklet URL
- Add `json_encode()` implementation for PHP<5.2
- Highlight search results

### Changed
- Improve 'Stay signed in' behaviour
- Improve `smallHash()`
- Refactor QRCode generation
- Update Javascript lazyloading
- Update CSS

### Removed
- Remove jQuery from almost all pages

### Fixed
- Fix overlapping tags
- Fix field foxus in the bookmarklet
- Fix error message when `data/` is not writable
- Fix HTML generation

### Security
- Fix XSS flaw


## [v0.0.41beta](https://github.com/shaarli/Shaarli/releases/tag/v0.0.41beta) - 2013-03-08
### Added
- Add HTTPS to the allowed protocols
- Add support for magnet links in link descriptions
- Allow creating new links as private by default
- Allow disabling jQuery
- Check write permissions
- Check session support before installation

### Changed
- Improve token security
- RSS feed: allow inverting links/permalinks

### Fixed
- Fix display issues during installation
- Fix popup redirection after login failure
- Fix RSS formatting for Thunderbird
- Fix thumbnail creation
- Fix cache purge

### Security
- Fix login issue with WebKit browsers


## [v0.0.40beta](https://github.com/shaarli/Shaarli/releases/tag/v0.0.40beta) - 2013-02-26
Initial release on GitHub.


## [v0.0.40beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-08-24
### Added
- Flickr thumbnail now also support albums, galleries and users
- Add a configuration option to disable session cookie protection
  Check this if your get disconnected often or your IP address changes often

### Removed
- Removed the xml comment in cached RSS/ATOM feed
  (although W3C-compliant, this may cause problems in some feed readers)

### Fixed
- A bug in the RSS cache would present old items as new in some cases
- A small bug (non-initialized variable) in page cache cleaning
- Proper "Nothing found" message when search returns no results
- No more 404 error when searching with empty input
- Flickr thumbnails are back (Flickr has made some changes to their domains)

## [v0.0.39beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-08-10
### Added
- A cache for RSS feed, ATOM feed and Daily RSS feed, because these URLs
  are massively hammered. Cache is automatically purged whenever the database
  is changed. This will reduce server load. I may add cache to other pages later.

### Changed
- No more global `$LINKSDB` (Yuk)
- Background color was removed when hovering a link

### Fixed
- Small bug corrected in config screen on timezones
- Calling a non-existing permalink now returns a crude 404 error instead of 200 (OK)
  This is done on purpose
- The `shaarli` session cookie now has a proper path
  Thus you can now install several Shaarlis on the same server in different paths,
  and each will have its session
- Now when you delete a link, you go back the same page/search parameters you were on
- Restore previously removed `error_get_last()`, to ensure PHP 5.1 compatibility
  (Yes, now it works on free.fr hosting)
- Added `dialog=1` in bookmarklet code for some browsers


## [v0.0.38beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-02-06
### Added
- Automatic creation of the `tmp` directory with proper rights (for RainTPL)
- When you click the key to see only private links, it turns yellow

### Changed
- The "Daily" page now automatically skips empty days.

### Fixed
- Corrected the tag encoding (there was a bug when selecting a second tag which contains accented characters)


## [v0.0.37beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-02-01
### Added
- Basic CSS for mobiles, which makes Shaarli //much// more usable on mobile devices
- Picture wall no more instantly kills your browser. Now it uses
  [lazy image loading](http://www.appelsiini.net/projects/lazyload);
  the pictures are loaded only as you scroll the page.
  This will reduce browser memory usage (especially on mobile devices),
  as well as server load.
  If you have javascript disabled, the page will still work as before
  (all images loaded at once)
- RSS feed for the "Daily" page. 1 RSS entry per day, with all links of that day.
  RSS feed provides the last 7 days (only non-empty days are returned).
- In link list, added an icon to see only private links. Click to toggle (only private / all)


## [v0.0.36beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-01-25
### Added
- Shaarli licence in COPYING

### Changed
- Display adjustments in "Daily" page

### Fixed
- Improper text color in install form
- Error in QRCode url (missing '?')


## [v0.0.35beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-01-25
### Fixed
- Corrected a bug introduced in 0.0.34 which would improperly preprend data to URLs


## [v0.0.34beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-01-25
### Added
- There is now a QR-Code of each permalink to easily open a link on your smartphone
- Protocols `file:` and `apt:` are now also converted to clickable links (patch by Francis Chavanon)
- Thumbnail support for http://xkcd.com/ (patch by Emilien Klein)
- Thumbnail support for http://pix.toile-libre.org/
- Well I had _some_ mercy for users with antique browsers (IE) which do not have
  support for gradients: I added a few `background-color`
- First version of the "Shaarli Daily", a page showing all links of a specific day.
  By default, you see the links of the previous day.
  There is still work to do on this page (error checking, better navigation (calendar?),
  RSS feed, CSS for mobile and printing...)

### Changed
- Upgraded bundled versions of jQuery (1.7.1) and jQuery UI (1.8.17)
- Upgraded bundled version of RainTPL (2.7)
- Changed HTTPS detection code

### Fixed
- In link edition, you can now click the word "Private" to check the box
- Clicking a tag would not work properly if the tag contained special characters (like +)
- Added proper jQuery licence (shame on me)


## [v0.0.33beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2012-01-17
### Added
- Shaarli packaged to ease Linux distributions integration
  As a simple user, you do not need to cope with these versions
  Future releases of Shaarli will also be customized and published in these directories
  Differences with the standard Shaarli version:
    - deb:
        - .tar.gz instead of .zip
        - COPYING licence file added
        - jQuery/jQuery-UI libraries removed to cope with Debian rules
          This version links to the libs hosted at http://code.jquery.com
    - rpm:
        - sources located in a subdirectory with the same name as the zip file
        - COPYING licence file added
    - WARNING: When downloading the .tar.gz, always use wget (and not your browser),
      otherwise the .tar.gz will be corrupted

### Fixed
- ATOM feed validates again

### Security
- XSS vulnerability patched (thanks to Stanislas D.!)


## [v0.0.32beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-12-16
### Added
- Better check on URL parameters (patch by gege2061)
- Add `max-height` and `overflow:auto` attributes so that content can be scrolled if too large

### Changed
- HTML generation moved to RainTPL templates (in the `tpl/` directory)
- Better detection of HTTPS (patch by gege2061)
- In RSS/ATOM feeds, the GUID is now the permalink instead of the final URL (patch by gege2061)
- Jerrywham CSS patch included
- Multiple spaces are now respected in description.
  Thus you can use Shaarli as a personal pastebin (for posting source code, for example).

### Removed
- Page time generation was removed

### Fixed
- Tab order changed in login screen
- Permalinks now work even if additional parameters have been added
  (e.g. `/?E8Yj2Q&utm_source=blablabla...`)
- user.css is included only if the file is present
  (This prevents a useless CSS include which makes a harmless but useless 404 error.)


## [v0.0.31beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-11-29
### Added
- Support for TED Talks (ted.com/talks) thumbnails (patch by Emilien K.)
- partial [patch](http://www.idleman.fr/blog/?p=508) by Idleman: Better design consistency, icon on private links. In-page popup was not included because it causes problem on some websites
- Support for bookmark files without ADD_DATE attributes
- Logo is clickable
- `user.css` can be added to overload Shaarli base CSS.(patch by Jerrywham).
  Just put `user.css` in the same directory as shaarli.css.
  Example: `<code css>#pageheader { background: blue; }</code>`
  Please note that Shaarli CSS are not stable and may completely change on each version

### Changed
- Edit and Delete buttons in link list were replaced with icons. (patch by Jerrywham)

### Fixed
- Better error handling in thumbnail generation (patch by Emilien K.)
- The top menu is no longer displayed in bookmarklet popup
- Bookmark which have the exact same date/time are now correctly imported.
  Most remaining import problems should be solved now
- Comment in Shaarli export moved to beginning of file to prevent clash with last link description


## [v0.0.30beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-11-18
### Added
- Add a small `delete` button in link list (after the `edit` button)

### Fixed
- Moved the call to PubSubHub


## [v0.0.29beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-11-18
### Fixed
- Corrected a bug introduced in v0.0.28beta
  (there was an error if you use the bookmarklet and you're not logged in)


## [v0.0.28beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-11-17
### Added
- Thumbnail support for youtu.be URLs (YouTube short url service)
- PubSubHub protocol support (from http://aldarone.fr/les-flux-rss-shaarli-et-pubsubhubbub/).
  Warning: This was not tested. You need to set your hub url in
  `$GLOBALS['config']['PUBSUBHUB_URL']` and put the official client (`publisher.php`)
  in the same directory as Shaarli's `index.php`
- RSS and ATOM feeds now also contain tags (in `category` tags, as per their
  respective specifications)

### Changed
- New Shaarli theme and logo by Idle (http://www.idleman.fr/blog/?p=469)
- In picture wall, pictures point to Shaarli permalink instead of final URL.
  This way, users can read the description.
- In RSS/ATOM feeds, guid and link URL of permalinks are now proper absolute URLs
- In RSS/ATOM feeds, URLs are now clickable
- Rename `http_parse_headers()` to `http_parse_headers_shaarli()` to prevent
  name collision with some PHP extensions

### Fixed
- Thumbnails removed for imgur.com/a/ URLs (Thumbnails are not available for albums on imgur)
- Shaarli now correctly only tries to get thumbnails for vimeo video URLs
- Fix a bug in imgur.com URLs handling that would cause some thumbnails not to appear
- The search engine would not return a result if the word to search was the first in description
- Extracted title is now correct if the page has two `title` html tags


## [v0.0.27beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-10-18
### Added
- Add a picture wall, which can be filtered too: it will use the same filters
  (tags,text search) as current page when clicked.


## [v0.0.26beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-10-17
### Changed
- Made permalink more visible (smallHash)

### Fixed
- Removed extras space in description when URLs are converted to clickable links
- Thumbnail for subreddit imgur urls (/r/...) were corrected (thanks to Accent Grave)


## [v0.0.25beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-10-13
### Added
- Better CSS for printing (thanks to jerrywham suggestion)
- Allow using a redirector or anonymizing proxy for links
  (such as `http://anonym.to/?` to mask you `HTTP_REFERER`).
  Just go to `Tools > Configure > Redirector`
  (thanks to Accent Grave for the suggestion).
- The `ENABLE_LOCALCACHE` option can be set to `false` for those who have
  a limited quota on their host.
  This will disable the local thumbnail cache.
  Services which require the use of the cache will have no thumbnails
  (vimeo, flickr, direct link to image).
  Other services will still have a thumbnail (youtube,imgur.com,dailymotion,imageshack.us)

### Changed
- Now thumbnails generated by Shaarli are croped to a height of 120 pixels
- YouTube thumbnails now use `default.jpg` instead of `2.jpg` (This is usually more pertinent)
- Configuration options (such as `HIDE_TIMESTAMPS`, `ENABLE_THUMBNAILS`, etc.)
  can now be put in a an external file so that you do not have to tweak them again
  when you upgrade Shaarli.
  Just add the file `data/options.php`.
- If a single link is displayed, the page title contains the title of the link
- Shaarli page title is clickable (and has the same link as "Home")
- A few CSS tweaks (thanks to maethor for suggestion)

### Fixed
- Shaarli now supports newlines in titles (thanks to dixy)
- The link to the RSS feed in page header was not correct


## [v0.0.24beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Allow posting an entry without a link. (You can use Shaarli as a kind of "personal twitter")
- Each Shaarli entry now has a short link (just click on the date of a link).
  Now you can send a link that points to a single entry in your Shaarli
- In descriptions, URLs are now clickable
- Thumbnails will be generated for all link pointing to .jpg/png/gif
  (as long as the images are less than 4 Mb and take less than 30 seconds to download)

### Fixed
- Now thumbnails also work for imgur gallery links (/gallery/...)
  (Thanks to Accent Grave for the correction)
- Removed useless debugging information in log
- The filter in RSS/ATOM feed now works again properly (it was broken in 0.0.17beta)


## [v0.0.23beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Added thumbnail support for imageshack.us

### Changed
- Now you can clic the sentence "Stay signed in" to tick the checkbox (patch by Emilien)
- In tag editing, comma (,) are now automatically converted to spaces
- In tag editing, autocomplete no longuer suggests a tag you have already entered in the same line


## [v0.0.22beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Support for thumbnails for flickr.com
- Allow staying signed in:
  Your session will be kept open even if you close the browser.
  This is available through a checkbox in the login screen.

### Changed
- Some hosts (flickr, vimeo) are slow as hell for the thumbnails,
  or require an extra HTTP request.
  For these hosts the thumbnail generation has been deported outside the generation
  of the page to keep Shaarli snappy.
  For these slow services, the thumbnails are also cached.

### Fixed
- Title was not properly passed if you had to login when using the bookmarklet (patch by shenshei)


## [v0.0.21beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Thumbnails for some services
  Currently supports: YouTube.com, dailymotion.com, vimeo.com (slow!) and imgur.com.
  Thumbnails are enabled by default, but you can turn them off
  (set `define('ENABLE_THUMBNAILS',true);` to `false`).

### Changed
- Removed the focus on the searchbox (this is cumbersome when you want to browse pages
  and scroll with the keyboard)


## [v0.0.20beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Fixed
- RSS feed is now served as `application/rss+xml` instead of `application/xhtml+xml`
  (which was causing problem in //RSS Lounge//)
- ATOM feed is now served as `application/atom+xml` instead of `application/xhtml+xml`


## [v0.0.19beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- ATOM feed

### Fixed
- Patch by Emilien to remove the update notification after the update


## [v0.0.18beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- You can now configure the title of your page
- New screen to configure title and timezone

### Changed
- Nicer timezone selection patch by killruana

### Fixed
- New lines now appear correctly in the RSS feed descriptions.


## [v0.0.17beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Change password screen added (based on a patch by killruana)
- Autocomplete in the tag search form
- You can rename or delete a tag in all links
  (very handy if you misspelled a tag or want to merge tags)
- When you click the RSS feed, the feed will be filtered with the same filters
  as the page you were viewing

### Changed
- CSS adjustments by jerrywham
- Minor corrections


## [v0.0.16beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Upgrade notification:
  If a new version of Shaarli is available, you will be notified by a discreet
  message in top-right corner.
  This message will only be visible if you are logged in, and the check will be
  performed at most once a day.
- Preliminary tag cloud (ugly for the moment, I need to find something better)

### Changed
- Replaced `preg_match()` with `version_compare()` to check PHP version
- Includes a patch by Emilien K. to mask dates if user is not logged in.
  The option can be activated by changing `define('HIDE_TIMESTAMPS',false);` to `true`


## [v0.0.15beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- New in import: Option to overwrite existing links when importing
- On free.fr, automatic creation of the `/sessions` directory

### Changed
- CSS Stylesheet is now an external file (shaarli.css).
  This reduces page size and eases customization.

### Removed
- Removed some parameters in URL added by some feed proxies (`#xtor=RSS-...`)

### Fixed
- Bug corrected: Prevented loop on login screen upon successful login after a failed login
- Bug corrected in import: HTML entities were not properly decoded.
  If you imported your Delicious/Diigo bookmarks, your should import them again
  and use the 'overwrite' option of the import feature.


## [v0.0.14beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- You no longer need to disable `magic_quotes` on your host.
  Shaarli will cope with this option beeing activated.


## [v0.0.13beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Import: New option to import html bookmark file as private links
- Import: Importing a bookmark file will not overwrite existing links anymore
- Export: New options to export only public or private links

### Changed
- In tag autocomplete, tags are presented in use order
  (most used tags first, instead of alphabetical order)
- RSS Feed can now be filtered by tags or fulltext search. Just add to the feed url:
  - `&searchtags=minecraft+video` for tag filtering
  - `&searchterm=portal` for fulltext search to the feed url


## [v0.0.12beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Add a check that the config file was properly created
  (in case Shaarli does not have the write rights in its folder)
- Open Shaarli: there is an option to open your Shaarli to anyone.
  Anybody will be able to add/edit/delete links without having to login.
  In code, change `define('OPEN_SHAARLI',false);` to `true`.
  Note: No anti-spam for the moment. You are warned!
- Autocomplete for tags


## [v0.0.11beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Add a check and a warning for some hosts which still have `magic_quotes` activated


## [v0.0.10beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Get rid of `&quot;` in titles


## [v0.0.9beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Added
- Now works on hosts `free.fr` and `1and1`
- Now works with PHP 5.1
- PHP version is now checked and an error message is displayed if version is not correct

### Fixed
- No more error messages if the browser does not send `HTTP_REFERER`
- No more error messages if the host has disabled http protocol in PHP config (eg. 1and1)


## [v0.0.8beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history)
### Changed
- In RSS feed, GUID content replaced with the URL of the link, because some
  stupid RSS reader (like Google Reader) use `<guid>` as a link instead of using `<link>`


## [v0.0.7beta](http://sebsauvage.net/wiki/doku.php?id=php:shaarli:history) - 2011-09-16
First public release by Sebsauvage, see original article:
[Adieu Delicious, Diigo et StumbleUpon. Salut Shaarli !](http://sebsauvage.net/rhaa/index.php?2011/09/16/09/29/58-adieu-delicious-diigo-et-stumbleupon-salut-shaarli-) (FR)
