# Unit tests

Shaarli uses the [PHPUnit](https://phpunit.de/) test framework; it can be installed with [Composer](../Installation.md#from-sources), which is a dependency management tool.

## Install composer

You can either use:

- a system-wide version, e.g. installed through your distro's package manager
- a local version, downloadable [here](https://getcomposer.org/download/).

```bash
# for Debian-based distros
sudo apt install composer
```


## Install Shaarli dev dependencies

After installing [required PHP extensions](../Server-configuration.md#php), install development dependencies:

```bash
$ cd /path/to/shaarli
$ make composer_dependencies_dev
```

## Install and enable Xdebug to generate PHPUnit coverage reports


[Xdebug](http://xdebug.org/docs/install) is a PHP extension which provides debugging and profiling capabilities. Install Xdebug:

```bash
# for Debian-based distros:
sudo apt install php-xdebug

# for ArchLinux:
pacman -S xdebug

# then add the following line to /etc/php/php.ini
zend_extension=xdebug.so
```

## Run unit tests

Ensure tests pass successfully:

```bash
make test
# ...
# OK (36 tests, 65 assertions)
```

In case of failure the test suite will point you to actual errors and output a summary:

```bash
make test
# ...
# FAILURES!
# Tests: 36, Assertions: 63, Errors: 1, Failures: 2.
```

By default, PHPUnit will run all suitable tests found under the `tests` directory. Each test has 3 possible outcomes:

- `.` - success
- `F` - failure: the test was run but its results are invalid
    - the code does not behave as expected
    - dependencies to external elements: globals, session, cache...
- `E` - error: something went wrong and the tested code has crashed
    - typos in the code, or in the test code
    - dependencies to missing external elements

If Xdebug has been installed and activated, two coverage reports will be generated:

- a summary in the console
- a detailed HTML report with metrics for tested code
    - to open it in a web browser: `firefox coverage/index.html &`


### Executing specific tests

Add a [`@group`](https://phpunit.de/manual/current/en/appendixes.annotations.html#appendixes.annotations.group) annotation in a test class or method comment:

```php
/**
 * Netscape bookmark import
 * @group WIP
 */
class BookmarkImportTest extends PHPUnit_Framework_TestCase
{
   [...]
}
```

To run all tests annotated with `@group WIP`:
```bash
$ vendor/bin/phpunit --group WIP tests/
```

## Running tests inside Docker containers

Unit tests can be run inside [Docker](../Docker.md) containers.

Test Dockerfiles are located under `tests/docker/<distribution>/Dockerfile`, and can be used to build Docker images to run Shaarli test suites under commonLinux environments. Dockerfiles are provided for the following environments:

- [`alpine316`](https://github.com/shaarli/Shaarli/blob/master/tests/docker/alpine36/Dockerfile) - [Alpine Linux 3.16](https://www.alpinelinux.org/downloads/)
- [`debian8`](https://github.com/shaarli/Shaarli/blob/master/tests/docker/debian8/Dockerfile) - [Debian 8 Jessie](https://www.debian.org/DebianJessie) (oldoldstable)
- [`debian9`](https://github.com/shaarli/Shaarli/blob/master/tests/docker/debian9/Dockerfile) - [Debian 9 Stretch](https://wiki.debian.org/DebianStretch) (oldstable)
- [`ubuntu16`](https://github.com/shaarli/Shaarli/blob/master/tests/docker/ubuntu16/Dockerfile) - [Ubuntu 16.04 Xenial Xerus](http://releases.ubuntu.com/16.04/) (old LTS)

Each image provides:
- a base Linux OS
- Shaarli PHP dependencies (OS packages)
- test PHP dependencies (OS packages)
- Composer
- Tests that run inside the container using a standard Linux user account (running tests as `root` would bypass permission checks and may hide issues)

Build a test image:

```bash
# build the Debian 9 Docker image
cd /path/to/shaarli/tests/docker/debian9
docker build -t shaarli-test:debian9 .
```

Run unit tests in a container:

```bash
cd /path/to/shaarli
# install/update 3rd-party test dependencies
composer install --prefer-dist
# run tests using the freshly built image
docker run -v $PWD:/shaarli shaarli-test:debian9 docker_test
# run the full test campaign
docker run -v $PWD:/shaarli shaarli-test:debian9 docker_all_tests
```
