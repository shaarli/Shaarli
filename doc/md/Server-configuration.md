
- [Prerequisites](#prerequisistes)
- [Apache](#apache)
- [Nginx](#nginx)
- [Proxies](#proxies)
- [See also](#see-also)

## Prerequisites
### Shaarli

- A web server and PHP interpreter module/service have been installed.
- You have write access to the Shaarli installation directory.
- The correct read/write permissions have been granted to the web server user and group.
- Your PHP interpreter is compatible with supported PHP versions:

Version | Status | Shaarli compatibility
:---:|:---:|:---:
7.2 | Supported | Yes
7.1 | Supported | Yes
7.0 | Supported | Yes
5.6 | Supported | Yes
5.5 | EOL: 2016-07-10 | Yes
5.4 | EOL: 2015-09-14 | Yes (up to Shaarli 0.8.x)
5.3 | EOL: 2014-08-14 | Yes (up to Shaarli 0.8.x)

- The following PHP extensions are installed on the server:

Extension | Required? | Usage
---|:---:|---
[`openssl`](http://php.net/manual/en/book.openssl.php) | All | OpenSSL, HTTPS
[`php-mbstring`](http://php.net/manual/en/book.mbstring.php) | CentOS, Fedora, RHEL, Windows, some hosting providers | multibyte (Unicode) string support
[`php-gd`](http://php.net/manual/en/book.image.php) | optional | required to use thumbnails
[`php-intl`](http://php.net/manual/en/book.intl.php) | optional | localized text sorting (e.g. `e->è->f`)
[`php-curl`](http://php.net/manual/en/book.curl.php) | optional | using cURL for fetching webpages and thumbnails in a more robust way
[`php-gettext`](http://php.net/manual/en/book.gettext.php) | optional | Use the translation system in gettext mode (faster)

--------------------------------------------------------------------------------

### SSL/TLS configuration 

To setup HTTPS / SSL on your webserver (recommended), you must generate a public/private **key pair** and a **certificate**, and install, configure and activate the appropriate **webserver SSL extension**.

#### Let's Encrypt

[Let's Encrypt](https://en.wikipedia.org/wiki/Let%27s_Encrypt) is a certificate authority that provides free TLS/X.509 certificates via an automated process.

 * Install `certbot` using the appropriate method described on https://certbot.eff.org/.
 
Location of the `certbot` program and template configuration files may vary depending on which installation method was used. Change the file paths below accordingly. Here is an easy way to create a signed certificate using `certbot`, it assumes `certbot` was installed through APT on a Debian-based distribution:

 * Stop the apache2/nginx service.
 * Run `certbot --agree-tos --standalone --preferred-challenges tls-sni --email "youremail@example.com" --domain yourdomain.example.com`
 * For the Apache webserver, copy `/usr/lib/python2.7/dist-packages/certbot_apache/options-ssl-apache.conf` to `/etc/letsencrypt/options-ssl-apache.conf` (paths may vary depending on installation method)
 * For Nginx: TODO
 * Setup your webserver as described below
 * Restart the apache2/nginx service.

#### Self-signed certificates

If you don't want to request a certificate from Let's Encrypt, or are unable to (for example, webserver on a LAN, or domain name not registered in the public DNS system), you can generate a self-signed certificate. This certificate will trigger security warnings in web browsers, unless you add it to the browser's SSL store manually.

* Apache: run `make-ssl-cert generate-default-snakeoil --force-overwrite`
* Nginx: TODO

--------------------------------------------------------------------------------

## Apache

Here is a basic configuration example for the Apache web server with `mod_php`.

In `/etc/apache2/sites-available/shaarli.conf`:

```apache
<VirtualHost *:443>
    ServerName   shaarli.my-domain.org
    DocumentRoot /absolute/path/to/shaarli/

    # Logging
    # Possible values include: debug, info, notice, warn, error, crit, alert, emerg.
    LogLevel  warn
    ErrorLog  /var/log/apache2/shaarli-error.log
    CustomLog /var/log/apache2/shaarli-access.log combined

    # Let's Encrypt SSL configuration (recommended)
    SSLEngine             on
    SSLCertificateFile    /etc/letsencrypt/live/yourdomain.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.example.com/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf

    # Self-signed SSL cert configuration
    #SSLEngine             on
    #SSLCertificateFile    /etc/ssl/certs/ssl-cert-snakeoil.pem
    #SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key

    # Optional, log PHP errors, useful for debugging
    #php_flag  log_errors on
    #php_flag  display_errors on
    #php_value error_reporting 2147483647
    #php_value error_log /var/log/apache2/shaarli-php-error.log

    <Directory /absolute/path/to/shaarli/>
        #Required for .htaccess support
        AllowOverride All
        Order allow,deny
        Allow from all

        Options Indexes FollowSymLinks MultiViews #TODO is Indexes/Multiviews required?

        # Optional - required for playvideos plugin
        #Header set Content-Security-Policy "script-src 'self' 'unsafe-inline' https://www.youtube.com https://s.ytimg.com 'unsafe-eval'"
    </Directory>

</VirtualHost>
```

Enable this configuration with `sudo a2ensite shaarli`

_Note: If you use Apache 2.2 or lower, you need [mod_version](https://httpd.apache.org/docs/current/mod/mod_version.html) to be installed and enabled._

_Note: Apache module `mod_rewrite` must be enabled to use the REST API._


## Nginx

Here is a basic configuration example for the Nginx web server, using the [php-fpm](http://php-fpm.org) PHP FastCGI Process Manager, and Nginx's [FastCGI](https://en.wikipedia.org/wiki/FastCGI) module.

<!--- TODO refactor everything below this point --->

### Common setup
Once Nginx and PHP-FPM are installed, we need to ensure:

- Nginx and PHP-FPM are running using the _same user and group_
- both these user and group have
    - `read` permissions for Shaarli resources
    - `execute` permissions for Shaarli directories _AND_ their parent directories

On a production server:

- `user:group` will likely be `http:http`, `www:www` or `www-data:www-data`
- files will be located under `/var/www`, `/var/http` or `/usr/share/nginx`

On a development server:

- files may be located in a user's home directory
- in this case, make sure both Nginx and PHP-FPM are running as the local user/group!

For all following configuration examples, this user/group pair will be used:

- `user:group = john:users`,

which corresponds to the following service configuration:

```ini
; /etc/php/php-fpm.conf
user = john
group = users

[...]
listen.owner = john
listen.group = users
```

```nginx
# /etc/nginx/nginx.conf
user john users;

http {
    [...]
}
```

### (Optional) Increase the maximum file upload size
Some bookmark dumps generated by web browsers can be _huge_ due to the presence of Base64-encoded images and favicons, as well as extra verbosity when nesting links in (sub-)folders.

To increase upload size, you will need to modify both nginx and PHP configuration:

```nginx
# /etc/nginx/nginx.conf

http {
    [...]

    client_max_body_size 10m;

    [...]
}
```

```ini
# /etc/php5/fpm/php.ini

[...]
post_max_size = 10M
[...]
upload_max_filesize = 10M
```

### Minimal
_WARNING: Use for development only!_ 

```nginx
user john users;
worker_processes  1;
events {
    worker_connections  1024;
}

http {
    include            mime.types;
    default_type       application/octet-stream;
    keepalive_timeout  20;

    index index.html index.php;

    server {
        listen       80;
        server_name  localhost;
        root         /home/john/web;

        access_log  /var/log/nginx/access.log;
        error_log   /var/log/nginx/error.log;

        location /shaarli/ {
            try_files $uri /shaarli/index.php$is_args$args;
            access_log  /var/log/nginx/shaarli.access.log;
            error_log   /var/log/nginx/shaarli.error.log;
        }

        location ~ (index)\.php$ {
            try_files $uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass   unix:/var/run/php-fpm/php-fpm.sock;
            fastcgi_index  index.php;
            include        fastcgi.conf;
        }
    }
}
```

### Modular
The previous setup is sufficient for development purposes, but has several major caveats:

- every content that does not match the PHP rule will be sent to client browsers:
    - dotfiles - in our case, `.htaccess`
    - temporary files, e.g. Vim or Emacs files: `index.php~`
- asset / static resource caching is not optimized
- if serving several PHP sites, there will be a lot of duplication: `location /shaarli/`, `location /mysite/`, etc.

To solve this, we will split Nginx configuration in several parts, that will be included when needed:

```nginx
# /etc/nginx/deny.conf
location ~ /\. {
    # deny access to dotfiles
    access_log off;
    log_not_found off;
    deny all;
}

location ~ ~$ {
    # deny access to temp editor files, e.g. "script.php~"
    access_log off;
    log_not_found off;
    deny all;
}
```

```nginx
# /etc/nginx/php.conf
location ~ (index)\.php$ {
    # Slim - split URL path into (script_filename, path_info)
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;

    # filter and proxy PHP requests to PHP-FPM
    fastcgi_pass   unix:/var/run/php-fpm/php-fpm.sock;
    fastcgi_index  index.php;
    include        fastcgi.conf;
}

location ~ \.php$ {
    # deny access to all other PHP scripts
    deny all;
}
```

```nginx
# /etc/nginx/static_assets.conf
location ~* \.(?:ico|css|js|gif|jpe?g|png)$ {
    expires    max;
    add_header Pragma public;
    add_header Cache-Control "public, must-revalidate, proxy-revalidate";
}
```

```nginx
# /etc/nginx/nginx.conf
[...]

http {
    [...]

    root        /home/john/web;
    access_log  /var/log/nginx/access.log;
    error_log   /var/log/nginx/error.log;

    server {
        # virtual host for a first domain
        listen       80;
        server_name  my.first.domain.org;

        location /shaarli/ {
            # Slim - rewrite URLs
            try_files $uri /shaarli/index.php$is_args$args;

            access_log  /var/log/nginx/shaarli.access.log;
            error_log   /var/log/nginx/shaarli.error.log;
        }

        location = /shaarli/favicon.ico {
            # serve the Shaarli favicon from its custom location
            alias /var/www/shaarli/images/favicon.ico;
        }

        include deny.conf;
        include static_assets.conf;
        include php.conf;
    }

    server {
        # virtual host for a second domain
        listen       80;
        server_name  second.domain.com;

        location /minigal/ {
            access_log  /var/log/nginx/minigal.access.log;
            error_log   /var/log/nginx/minigal.error.log;
        }

        include deny.conf;
        include static_assets.conf;
        include php.conf;
    }
}
```

### Redirect HTTP to HTTPS
Assuming you have generated a (self-signed) key and certificate, and they are
located under `/home/john/ssl/localhost.{key,crt}`, it is pretty straightforward
to set an HTTP (:80) to HTTPS (:443) redirection to force SSL/TLS usage.

```nginx
# /etc/nginx/nginx.conf
[...]

http {
    [...]

    index index.html index.php;

    root        /home/john/web;
    access_log  /var/log/nginx/access.log;
    error_log   /var/log/nginx/error.log;

    server {
        listen       80;
        server_name  localhost;

        return 301 https://localhost$request_uri;
    }

    server {
        listen       443 ssl;
        server_name  localhost;

        ssl_certificate      /home/john/ssl/localhost.crt;
        ssl_certificate_key  /home/john/ssl/localhost.key;

        location /shaarli/ {
            # Slim - rewrite URLs
            try_files $uri /index.php$is_args$args;

            access_log  /var/log/nginx/shaarli.access.log;
            error_log   /var/log/nginx/shaarli.error.log;
        }

        location = /shaarli/favicon.ico {
            # serve the Shaarli favicon from its custom location
            alias /var/www/shaarli/images/favicon.ico;
        }

        include deny.conf;
        include static_assets.conf;
        include php.conf;
    }
}
```

## Proxies

If Shaarli is served behind a proxy (i.e. there is a proxy server between clients and the web server hosting Shaarli), please refer to the proxy server documentation for proper configuration. In particular, you have to ensure that the following server variables are properly set:

- `X-Forwarded-Proto`
- `X-Forwarded-Host`
- `X-Forwarded-For`

See also [proxy-related](https://github.com/shaarli/Shaarli/issues?utf8=%E2%9C%93&q=label%3Aproxy+) issues.

## Robots and crawlers

Shaarli disallows indexing and crawling of your local documentation pages by search engines, using `<meta name="robots">` HTML tags.
Your Shaarli instance and other pages you host may still be indexed by various robots on the public Internet.
You may want to setup a robots.txt file or other crawler control mechanism on your server.
See [[1]](https://en.wikipedia.org/wiki/Robots_exclusion_standard), [[2]](https://support.google.com/webmasters/answer/6062608?hl=en) and [[3]](https://developers.google.com/search/reference/robots_meta_tag)

## See also

 * [Server security](Server-security.md)

#### Webservers

- [Apache/PHP - error log per VirtualHost](http://stackoverflow.com/q/176) (StackOverflow)
- [Apache - PHP: php_value vs php_admin_value and the use of php_flag explained](https://ma.ttias.be/php-php_value-vs-php_admin_value-and-the-use-of-php_flag-explained/)
- [Server-side TLS (Apache)](https://wiki.mozilla.org/Security/Server_Side_TLS#Apache) (Mozilla)
- [Nginx Beginner's guide](http://nginx.org/en/docs/beginners_guide.html)
- [Nginx ngx_http_fastcgi_module](http://nginx.org/en/docs/http/ngx_http_fastcgi_module.html)
- [Nginx Pitfalls](http://wiki.nginx.org/Pitfalls)
- [Nginx PHP configuration examples](http://kbeezie.com/nginx-configuration-examples/) (Karl Blessing)
- [Server-side TLS (Nginx)](https://wiki.mozilla.org/Security/Server_Side_TLS#Nginx) (Mozilla)
- [How to Create Self-Signed SSL Certificates with OpenSSL](http://www.xenocafe.com/tutorials/linux/centos/openssl/self_signed_certificates/index.php)
- [How do I create my own Certificate Authority?](https://workaround.org/certificate-authority)

#### PHP

- [Travis configuration](https://github.com/shaarli/Shaarli/blob/master/.travis.yml)
- [PHP: Supported versions](http://php.net/supported-versions.php)
- [PHP: Unsupported versions](http://php.net/eol.php) _(EOL - End Of Life)_
- [PHP 7 Changelog](http://php.net/ChangeLog-7.php)
- [PHP 5 Changelog](http://php.net/ChangeLog-5.php)
- [PHP: Bugs](https://bugs.php.net/)
