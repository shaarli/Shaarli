### ► Play Videos plugin for Shaarli
This plugin adds a `► Play Videos` button to [Shaarli](https://github.com/shaarli/Shaarli)'s toolbar. Click this button to play all videos on the page in an overlay HTML5 player. Nice for continuous stream of music, documentaries, talks...

This uses code from https://zaius.github.io/youtube_playlist/ and is currently only compatible with Youtube videos.

![](https://cdn.mediacru.sh/D_izf0zjAtxy.png)

#### Installation and setup
Place the files in the `tpl/plugins/playvideos/` directory of your Shaarli.
This is a default Shaarli plugin, you just have to enable it.

To enable the plugin, add `playvideos` to the `TOOLBAR_PLUGINS` config option in your `index.php` or `data/options.php`. Example:

    $GLOBALS['config']['TOOLBAR_PLUGINS'] = array('aplugins', 'anotherone', 'playvideos');

#### Troubleshooting
If your server has [Content Security Policy](http://content-security-policy.com/) headers enabled, this may prevent the script from loading fully. You should relax the CSP in your server settings. Example CSP rule for apache2:
`Header set Content-Security-Policy "script-src 'self' 'unsafe-inline' https://www.youtube.com https://s.ytimg.com 'unsafe-eval'"`

### License
```
File: youtube_playlist.js
Copyright: (c) 2010-2014, David Kelso <david@kelso.id.au>
License: The ISC License (http://opensource.org/licenses/ISC)

Files: jquery*.js
License: MIT License (http://opensource.org/licenses/MIT)
Copyright: (C) jQuery Foundation and other contributors, https://jquery.com/download/

-----------------------------------------------------

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

----------------------------------------------------
MIT LICENSE

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

----------------------------------------------------
```