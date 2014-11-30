## Save to Wallabag plugin for Shaarli

For each link in your Shaarli, adds a button to save the target page in your [wallabag](https://www.wallabag.org/).

### Installation/configuration
Clone this repository inside your `tpl/plugins/` directory, or download the archive and unpack it there.  
The directory structure should look like:

```
└── tpl
    └── plugins
        └── wallabag
            ├── README.md
            ├── wallabag.html
            └── wallabag.png
```

Then, set the `WALLABAG_URL` variable in `data/options.php` pointing to your wallabag URL. Example:

```
$GLOBALS['config']['WALLABAG_URL'] = 'http://demo.wallabag.org' ; //Base URL of your wallabag installation
```