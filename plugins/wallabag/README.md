## Save to Wallabag plugin for Shaarli

For each link in your Shaarli, adds a button to save the target page in your [wallabag](https://www.wallabag.org/).

### Installation

Clone this repository inside your `tpl/plugins/` directory, or download the archive and unpack it there.
The directory structure should look like:

```bash
└── tpl
    └── plugins
        └── wallabag
            ├── README.md
            ├── wallabag.html
            ├── wallabag.meta
            ├── wallabag.php
            ├── wallabag.php
            └── WallabagInstance.php
```

To enable the plugin, you can either:

  * enable it in the plugins administration page (`/admin/plugins`).
  * add `wallabag` to your list of enabled plugins in `data/config.json.php` (`general.enabled_plugins` section).

### Configuration

Go to the plugin administration page, and edit the following settings (with the plugin enabled).

**WALLABAG_URL**: *Wallabag instance URL*
Example value: `http://v2.wallabag.org`

**WALLABAG_VERSION**: *Wallabag version*
Value: either `1` (for 1.x) or `2` (for 2.x)

> Note: these settings can also be set in `data/config.json.php`, in the plugins section.
