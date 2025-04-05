### ► Play Videos plugin for Shaarli

Adds a `► Play Videos` button to [Shaarli](https://github.com/shaarli/Shaarli)'s toolbar. Click this button to play all videos on the page in an overlay HTML5 player. Nice for continuous stream of music, documentaries, talks...

<!-- TODO screenshot -->

This uses code from https://zaius.github.io/youtube_playlist/ and is currently only compatible with Youtube videos.

#### Installation and setup

This is a default Shaarli plugin, you just have to enable it. See [Shaarli configuration](../../doc/md/Shaarli-configuration.md).


#### Troubleshooting

If your server has [Content Security Policy](https://content-security-policy.com/) headers enabled, this may prevent the script from loading fully. This plugin only requires to accept content from Youtube servers, so simply allow `https://www.youtube.com` as source for `script-src` and `frame-src` in your server settings. Example CSP rule for apache2:

```apache
<Directory /path/to/shaarli>
    # Required for playvideos plugin
    Header set Content-Security-Policy "default-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; script-src 'self' https://www.youtube.com; connect-src 'self'; frame-src 'self' https://www.youtube.com; img-src 'self' data:; style-src 'self'; font-src 'self'"
</Directory>
```

You may place the `Header` directive in the `<Directory...` section of your [webserver configuration](../../doc/md/Server-configuration.md)/virtualhost file, or write the above snippet to `/etc/apache2/conf-available/shaarli-csp.conf`; then run `a2enconf shaarli-csp; service apache2 reload`.

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
