# Installation

Once your server is [configured](Server-configuration.md), install Shaarli:

## From release ZIP

To install Shaarli, simply place the files from the latest [release .zip archive](https://github.com/shaarli/Shaarli/releases) under your webserver's document root (directly at the document root, or in a subdirectory). Download the **shaarli-vX.X.X-full** archive to include dependencies.

```bash
wget https://github.com/shaarli/Shaarli/releases/download/v0.11.1/shaarli-v0.11.1-full.zip
unzip shaarli-v0.11.1-full.zip
sudo rsync -avP Shaarli/ /var/www/shaarli.mydomain.org/
```

## From sources

These components are required to build Shaarli:

- [Composer](dev/Development.md#install-composer) to manage third-party [PHP dependencies](dev/Development#third-party-libraries).
- [yarn](https://classic.yarnpkg.com/en/docs/install/) to build frontend dependencies.
- [python3-virtualenv](https://pypi.org/project/virtualenv/) to build local HTML documentation.

```bash
# example from a Debian-based build machine
sudo apt install composer yarnpkg python3-virtualenv
```

Clone the repository, either pointing to:

- any [tagged release](https://github.com/shaarli/Shaarli/releases)
- `latest`: the latest tagged release
- `master`: development branch

```bash
# clone the branch/tag of your choice
$ git clone -b latest https://github.com/shaarli/Shaarli.git /home/me/Shaarli
# OR download/extract the tar.gz/zip: wget https://github.com/shaarli/Shaarli/archive/latest.tar.gz...

# enter the directory
$ cd /home/me/Shaarli
# install 3rd-party PHP dependencies
$ composer install --no-dev --prefer-dist
# build frontend static assets
$ make build_frontend
# build translations
$ make translate
# build HTML documentation
$ make htmldoc
# copy the resulting shaarli directory under your webserver's document root
$ rsync -avP /home/me/Shaarli/ /var/www/shaarli.mydomain.org/
```

## Set file permissions

Regardless of the installation method, appropriate [file permissions](dev/Development.md#directory-structure) must be set:

```bash
sudo chown -R root:www-data /var/www/shaarli.mydomain.org
sudo chmod -R g+rX /var/www/shaarli.mydomain.org
sudo chmod -R g+rwX /var/www/shaarli.mydomain.org/{cache/,data/,pagecache/,tmp/}
```

## Using Docker

[See the documentation](Docker.md)


## Finish Installation

Once Shaarli is downloaded and files have been placed at the correct location, open this location your web browser.

Enter basic settings for your Shaarli installation, and it's ready to use!

![](images/07-installation.jpg)

Congratulations! Your Shaarli is now available at `https://shaarli.mydomain.org`.

You can further [configure Shaarli](Shaarli-configuration.md), setup [Plugins](Plugins.md) or [additional software](Community-and-related-software.md).


## Upgrading Shaarli

See [Upgrade and Migration](Upgrade-and-migration)
