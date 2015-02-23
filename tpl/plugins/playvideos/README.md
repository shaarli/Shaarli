### ► Play Videos plugin for Shaarli
This plugin adds a `► Play Videos` button to [Shaarli](https://github.com/shaarli/Shaarli)'s toolbar. Click this button to play all videos on the page in an overlay HTML5 player. Nice for continuous stream of music, documentaries, talks...

This uses code from https://zaius.github.io/youtube_playlist/ and is currently only compatible with Youtube videos.

![](https://cdn.mediacru.sh/D_izf0zjAtxy.png)

#### Installation
Place the files in the `tpl/plugins/playvideos/` directory of your Shaarli.
This is a default Shaarli plugin, you just have to enable it.

To enable the plugin, add `playvideos` to the `TOOLBAR_PLUGINS` config option in your `index.php` or `data/options.php`. Example:

    $GLOBALS['config']['TOOLBAR_PLUGINS'] = array('playvideos');

#### Troubleshooting
If your server has [Content Security Policy](http://content-security-policy.com/) headers enabled, this may prevent the script from loading fully. You should relax the CSP in your server settings. Example CSP rule for apache2:
`Header set Content-Security-Policy "script-src 'self' 'unsafe-inline' https://www.youtube.com https://s.ytimg.com 'unsafe-eval'"`

### License
The ISC License (http://opensource.org/licenses/ISC)

Copyright (c) 2010-2014, David Kelso (david at kelso dot id dot au)  
Copyright (c) 2010-2014, nodiscc (nodiscc at gmail dot com)

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE
