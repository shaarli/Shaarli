### ► Play Videos plugin for Shaarli

Adds a `► Play Videos` button to [Shaarli](https://github.com/shaarli/Shaarli)'s toolbar. Click this button to play all videos on the page in an overlay HTML5 player. Nice for continuous stream of music, documentaries, talks...

<!-- TODO screenshot -->

This plugin is currently only compatible with Youtube videos.

#### Installation and setup

This is a default Shaarli plugin, you just have to enable it. See [Shaarli configuration](../../doc/md/Shaarli-configuration.md).


#### Troubleshooting

If your server has [Content Security Policy](http://content-security-policy.com/) headers enabled, this may prevent the script from loading fully. You should relax the CSP in your server settings. Example CSP rule for apache2:

```apache
<Directory /path/to/shaarli>
    # Required for playvideos plugin
    Header set Content-Security-Policy "script-src 'self' 'unsafe-inline' https://www.youtube.com https://s.ytimg.com 'unsafe-eval'"
</Directory>
```

You may place the `Header` directive in the `<Directory...` section of your [webserver configuration](../../doc/md/Server-configuration.md)/virtualhost file, or write the above snippet to `/etc/apache2/conf-available/shaarli-csp.conf`; then run `a2enconf shaarli-csp; service apache2 reload`.

### License
```
File: youtube_playlist.js
Copyright: (c) 2025 The Shaarli Community, see AUTHORS
License: zlib/libpng

----------------------------------------------------
ZLIB/LIBPNG LICENSE

This software is provided 'as-is', without any express or implied warranty.
In no event will the authors be held liable for any damages arising from
the use of this software.

Permission is granted to anyone to use this software for any purpose,
including commercial applications, and to alter it and redistribute it
freely, subject to the following restrictions:

  1. The origin of this software must not be misrepresented; you must not
     claim that you wrote the original software. If you use this software
     in a product, an acknowledgment in the product documentation would
     be appreciated but is not required.

  2. Altered source versions must be plainly marked as such, and must
     not be misrepresented as being the original software.

  3. This notice may not be removed or altered from any source distribution.

----------------------------------------------------
```
