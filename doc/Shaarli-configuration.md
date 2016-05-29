# Shaarli configuration

## Foreword

**Do not edit configuration options in index.php! Your changes would be lost.** 

Once your Shaarli instance is installed, the file `data/config.json.php` is generated:
* it contains all settings in JSON format, and can be edited to customize values
* it defines which [plugins](Plugin-System) are enabled[](.html)
* its values override those defined in `index.php`
* it is wrap in a PHP comment to prevent anyone accessing it, regardless of server configuration

## File and directory permissions

The server process running Shaarli must have:
- `read` access to the following resources:
    - PHP scripts: `index.php`, `application/*.php`, `plugins/*.php`
    - 3rd party PHP and Javascript libraries: `inc/*.php`, `inc/*.js`
    - static assets:
        - CSS stylesheets: `inc/*.css`
        - `images/*`
    - RainTPL templates: `tpl/*.html`
- `read`, `write` and `execution` access to the following directories:
    - `cache` - thumbnail cache
    - `data` - link data store, configuration options
    - `pagecache` - Atom/RSS feed cache
    - `tmp` - RainTPL page cache

On a Linux distribution:
- the web server user will likely be `www` or `http` (for Apache2)
- it will be a member of a group of the same name: `www:www`, `http:http`
- to give it access to Shaarli, either:
    - unzip Shaarli in the default web server location (usually `/var/www/`) and set the web server user as the owner
    - put users in the same group as the web server, and set the appropriate access rights
- if you have a domain / subdomain to serve Shaarli, [configure the server](Server-configuration) accordingly[](.html)

## Configuration

In `data/config.json.php`.

See also [Plugin System](Plugin-System.html).

### Credentials
 
> You shouldn't edit those.

**login**: Login username.  
**hash**: Generated password hash.  
**salt**: Password salt.

### General

**title**: Shaarli's instance title.  
**header_link**: Link to the homepage.  
**links_per_page**: Number of shaares displayed per page.  
**timezone**: See [the list of supported timezones](http://php.net/manual/en/timezones.php).  
**enabled_plugins**: List of enabled plugins.

### Security

**session_protection_disabled**: Disable session cookie hijacking protection (not recommended). 
It might be useful if your IP adress often changes.  
**ban_after**: Failed login attempts before being IP banned.  
**ban_duration**: IP ban duration in seconds.  
**open_shaarli**: Anyone can add a new link while logged out if enabled.  

### Resources

**data_dir**: Data directory.  
**datastore**: Shaarli's links database file path.  
**updates**: File path for the ran updates file.  
**log**: Log file path.  
**update_check**: Last update check file path.  
**raintpl_tpl**: Templates directory.  
**raintpl_tmp**: Template engine cache directory.  
**thumbnails_cache**: Thumbnails cache directory.  
**page_cache**: Shaarli's internal cache directory.  
**ban_file**: Banned IP file path.

### Updates

**check_updates**: Enable or disable update check to the git repository.  
**check_updates_branch**: Git branch used to check updates (e.g. `stable` or `master`).  
**check_updates_interval**: Look for new version every N seconds (default: every day).

### Privacy

**default_private_links**: Check the private checkbox by default for every new link.  
**hide_public_links**: All links are hidden while logged out.  
**hide_timestamps**: Timestamps are hidden.

### Feed

**rss_permalinks**: Enable this to redirect RSS links to Shaarli's permalinks instead of shaared URL.  
**show_atom**: Display ATOM feed button.

### Thumbnail

**enable_thumbnails**: Enable or disable thumbnail display.  
**enable_localcache**: Enable or disable local cache.

### Redirector

**url**: Redirector URL, such as `anonym.to`.  
**encode_url**: Enable this if the redirector needs encoded URL to work properly.

## Configuration file example

```json
<?php /*
{
    "credentials": {
        "login": "<login>",
        "hash": "<password hash>",
        "salt": "<password salt>"
    },
    "security": {
        "ban_after": 4,
        "session_protection_disabled": false,
        "ban_duration": 1800
    },
    "resources": {
        "data_dir": "data",
        "config": "data\/config.php",
        "datastore": "data\/datastore.php",
        "ban_file": "data\/ipbans.php",
        "updates": "data\/updates.txt",
        "log": "data\/log.txt",
        "update_check": "data\/lastupdatecheck.txt",
        "raintpl_tmp": "tmp\/",
        "raintpl_tpl": "tpl\/",
        "thumbnails_cache": "cache",
        "page_cache": "pagecache"
    },
    "general": {
        "check_updates": true,
        "rss_permalinks": true,
        "links_per_page": 20,
        "default_private_links": true,
        "enable_thumbnails": true,
        "enable_localcache": true,
        "check_updates_branch": "stable",
        "check_updates_interval": 86400,
        "enabled_plugins": [
            "markdown",
            "wallabag",
            "archiveorg"
        ],
        "timezone": "Europe\/Paris",
        "title": "My Shaarli",
        "header_link": "?"
    },
    "extras": {
        "show_atom": false,
        "hide_public_links": false,
        "hide_timestamps": false,
        "open_shaarli": false,
        "redirector": "http://anonym.to/?",
        "redirector_encode_url": false
    },
    "general": {
        "header_link": "?",
        "links_per_page": 20,
        "enabled_plugins": [
            "markdown",
            "wallabag"
        ],
        "timezone": "Europe\/Paris",
        "title": "My Shaarli"
    },
    "updates": {
        "check_updates": true,
        "check_updates_branch": "stable",
        "check_updates_interval": 86400
    },
    "feed": {
        "rss_permalinks": true,
        "show_atom": false
    },
    "privacy": {
        "default_private_links": true,
        "hide_public_links": false,
        "hide_timestamps": false
    },
    "thumbnail": {
        "enable_thumbnails": true,
        "enable_localcache": true
    },
    "redirector": {
        "url": "http://anonym.to/?",
        "encode_url": false
    },
    "plugins": {
        "WALLABAG_URL": "http://demo.wallabag.org",
        "WALLABAG_VERSION": "1"
    }
}
```

## Additional configuration

The playvideos plugin may require that you adapt your server's 
[Content Security Policy](https://github.com/shaarli/Shaarli/blob/master/plugins/playvideos/README.md#troubleshooting) 
configuration to work properly.[](.html)

