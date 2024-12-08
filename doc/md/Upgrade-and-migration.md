# Upgrade and migration

## Note your current version

If anything goes wrong, it's important for us to know which version you're upgrading from.
The current version is present in the `shaarli_version.php` file.


## Backup your data

Shaarli stores all user data and [configuration](Shaarli-configuration.md) under the `data` directory. [Backup](Backup-and-restore.md) this repository _before_ upgrading Shaarli. You will need to restore it after the following upgrade steps.

```bash
sudo cp -r /var/www/shaarli.mydomain.org/data ~/shaarli-data-backup
```

## Upgrading from ZIP archives

If you installed Shaarli from a [release ZIP archive](Installation.md#from-release-zip):

```bash
# Download the archive to the server, and extract it
cd ~
wget https://github.com/shaarli/Shaarli/releases/download/v0.X.Y/shaarli-v0.X.Y-full.zip
unzip shaarli-v0.X.Y-full.zip

# overwrite your Shaarli installation with the new release **All data will be lost, see _Backup your data_ above.**
sudo rsync -avP --delete Shaarli/ /var/www/shaarli.mydomain.org/

# restore file permissions as described on the installation page
sudo chown -R root:www-data /var/www/shaarli.mydomain.org
sudo chmod -R g+rX /var/www/shaarli.mydomain.org
sudo chmod -R g+rwX /var/www/shaarli.mydomain.org/{cache/,data/,pagecache/,tmp/}

# restore backups of the data directory
sudo cp -r ~/shaarli-data-backup/* /var/www/shaarli.mydomain.org/data/

# If you use gettext mode for translations (not the default), reload your web server.
sudo systemctl restart apache2
sudo systemctl restart nginx
```

If you don't have shell access (eg. on shared hosting), backup the shaarli data directory, download the ZIP archive locally, extract it, upload it to the server using file transfer, and restore the data directory backup.

Access your fresh Shaarli installation from a web browser; the configuration and data store will then be automatically updated, and new settings added to `data/config.json.php` (see [Shaarli configuration](Shaarli-configuration.md) for more details).


## Upgrading from Git

If you have installed Shaarli [from sources](Installation.md#from-sources):

```bash
# pull new changes from your local clone
cd /var/www/shaarli.mydomain.org/
sudo git pull

# update PHP dependencies (Shaarli >= v0.8)
sudo composer install --no-dev

# update translations (Shaarli >= v0.9.2)
sudo make translate

# If you use translations in gettext mode (not the default), reload your web server.
sudo systemctl reload apache
sudo systemctl reload nginx

# update front-end dependencies (Shaarli >= v0.10.0)
sudo make build_frontend

# restore file permissions as described on the installation page
sudo chown -R root:www-data /var/www/shaarli.mydomain.org
sudo chmod -R g+rX /var/www/shaarli.mydomain.org
sudo chmod -R g+rwX /var/www/shaarli.mydomain.org/{cache/,data/,pagecache/,tmp/}
``` 

Access your fresh Shaarli installation from a web browser; the configuration and data store will then be automatically updated, and new settings added to `data/config.json.php` (see [Shaarli configuration](Shaarli-configuration.md) for more details).

---------------------------------------------------------------

## Migrating and upgrading from Sebsauvage's repository

If you have installed Shaarli from [Sebsauvage's original Git repository](https://github.com/sebsauvage/Shaarli), you can use [Git remotes](https://git-scm.com/book/en/v2/Git-Basics-Working-with-Remotes) to update your working copy.

The following guide assumes that:

- you have a basic knowledge of Git [branching](https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell) and [remote repositories](https://git-scm.com/book/en/v2/Git-Basics-Working-with-Remotes)
- the default remote is named `origin` and points to Sebsauvage's repository
- the current branch is `master`
    - if you have personal branches containing customizations, you will need to [rebase them](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) after the upgrade; beware though, a lot of changes have been made since the community fork has been created, so things are very likely to break!
- the working copy is clean:
    - no versioned file has been locally modified
    - no untracked files are present

### Step 0: show repository information

```bash
$ cd /path/to/shaarli

$ git remote -v
origin	https://github.com/sebsauvage/Shaarli (fetch)
origin	https://github.com/sebsauvage/Shaarli (push)

$ git branch -vv
* master 029f75f [origin/master] Update README.md

$ git status
On branch master
Your branch is up-to-date with 'origin/master'.
nothing to commit, working directory clean
```

### Step 1: update Git remotes

```
$ git remote rename origin sebsauvage
$ git remote -v
sebsauvage	https://github.com/sebsauvage/Shaarli (fetch)
sebsauvage	https://github.com/sebsauvage/Shaarli (push)

$ git remote add origin https://github.com/shaarli/Shaarli
$ git fetch origin

remote: Counting objects: 3015, done.
remote: Compressing objects: 100% (19/19), done.
remote: Total 3015 (delta 446), reused 457 (delta 446), pack-reused 2550
Receiving objects: 100% (3015/3015), 2.59 MiB | 918.00 KiB/s, done.
Resolving deltas: 100% (1899/1899), completed with 48 local objects.
From https://github.com/shaarli/Shaarli
 * [new branch]      master     -> origin/master
[...]
 * [new tag]         v0.6.4     -> v0.6.4
 * [new tag]         v0.7.0     -> v0.7.0
```

### Step 2: use the release community branch

```bash
$ git checkout origin/release -b release
Branch release set up to track remote branch release from origin.
Switched to a new branch 'release'

$ git branch -vv
  master 029f75f [sebsauvage/master] Update README.md
* release 890afc3 [origin/release] Merge tag 'v0.13.0' into release
```

Shaarli >= `v0.8.x`: install/update third-party PHP dependencies using [Composer](https://getcomposer.org/):

```bash
$ composer install --no-dev

Loading composer repositories with package information
Updating dependencies
  - Installing shaarli/netscape-bookmark-parser (v1.0.1)
    Downloading: 100%
```

Shaarli >= `v0.9.2` supports translations:

```bash
$ make translate
```

If you use translations in gettext mode, reload your web server.

Shaarli >= `v0.10.0` manages its front-end dependencies with nodejs. You need to install [yarn](https://classic.yarnpkg.com/en/docs/install/):

```bash
$ make build_frontend
``` 

Optionally, you can delete information related to the legacy version:

```bash
$ git branch -D master
Deleted branch master (was 029f75f).

$ git remote remove sebsauvage

$ git remote -v
origin	https://github.com/shaarli/Shaarli (fetch)
origin	https://github.com/shaarli/Shaarli (push)

$ git gc
Counting objects: 3317, done.
Delta compression using up to 8 threads.
Compressing objects: 100% (1237/1237), done.
Writing objects: 100% (3317/3317), done.
Total 3317 (delta 2050), reused 3301 (delta 2034)to
```

### Step 3: configuration

After migrating, access your fresh Shaarli installation from a web browser; the
configuration will then be automatically updated, and new settings added to
`data/config.json.php` (see [Shaarli configuration](Shaarli-configuration.md) for more
details).

## Troubleshooting

If the solutions provided here don't work, see [Troubleshooting](Troubleshooting.md) and/or open an issue specifying which version you're upgrading from and to.

