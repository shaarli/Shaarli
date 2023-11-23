# Plugins

## Installation

For plugins shipped with Shaarli, no installation is required.

If you want to install a third party plugin, download it to the `plugins` directory in Shaarli's installation folder:

```bash
# example directory structure
| index.php
| plugins/
|---| custom_plugin/
|   |---| custom_plugin.php
|   |---| ...

```

Make sure your webserver can read and write the files in your plugin folder.


## Configuration

From Shaarli's administration page (`Tools` link), go to `Plugin administration`. Here you can enable and disable all plugins available, and configure them.

![administration screenshot](https://camo.githubusercontent.com/5da68e191969007492ca0fbeb25f3b2357b748cc/687474703a2f2f692e696d6775722e636f6d2f766837544643712e706e67)


## Order

In the plugin administration page, you can move enabled plugins to the top or bottom of the list. The first plugins in the list will be processed first.

This is important in case plugins depend on each other. Read plugins READMEs for more information.

**Use case**: The (non existent) plugin `shaares_footer` adds a footer to every shaare in Markdown syntax. It needs to be processed *before* (higher in the list) the Markdown plugin. Otherwise its syntax won't be translated in HTML.


## Configuration file

Enabled plugins are stored in your [Configuration file](Shaarli-configuration.md).

## Usage

### Official plugins

Usage of each plugin is documented in it's README file:

 * `addlink-toolbar`: Adds the addlink input on the Shaares list page
 * `archiveorg`: For each Shaare, add a link to the archived page on Archive.org
 * `default_colors`: Override default theme colors.
 * `isso`: Let visitor comment your shaares on permalinks with Isso.
 * `piwik`: A plugin that adds Piwik tracking code to Shaarli pages.
 * [`playvideos`](https://github.com/shaarli/Shaarli/blob/master/plugins/playvideos/README.md): Add a button in the toolbar allowing to watch all videos.
 * `pubsubhubbub`: Enable PubSubHubbub feed publishing
 * `qrcode`: For each Shaare, add a QRCode icon.
 * `readitlater`: Mark bookmarks to read them later, with bookmark list highlight and filter.
 * [`wallabag`](https://github.com/shaarli/Shaarli/blob/master/plugins/wallabag/README.md):  For each Shaare, add a Wallabag icon to save it in your instance.


### Third party plugins

See [Community & related software](Community-and-related-software.md)
