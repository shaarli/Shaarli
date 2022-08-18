# Podman

[Podman](https://docs.podman.io/en/latest/) is a daemonless container engine for developing, managing, and running OCI Containers on your Linux System. Containers can either be run as root or in rootless mode.

## Install Podman

[Install Podman](https://podman.io/getting-started/installation), by following the instructions relevant to your OS / distribution. For example on Debian:

~~~
sudo apt-get -y install podman
~~~

*The podman package is available in the Debian 11 (Bullseye) repositories and later.*

## Setup Podman

The following two tutorials show you how to set up Podman and perform some basic commands with the utility:

 * [Basic Setup and Use of Podman](https://github.com/containers/podman/blob/main/docs/tutorials/podman_tutorial.md)
 * [Basic Setup and Use of Podman in a Rootless environment](https://github.com/containers/podman/blob/main/docs/tutorials/rootless_tutorial.md)

## Get and run a Shaarli image

Shaarli images are available on [DockerHub](https://hub.docker.com/r/shaarli/shaarli/) `shaarli/shaarli`:

- `latest`: master (development) branch
- `vX.Y.Z`: shaarli [releases](https://github.com/shaarli/Shaarli/releases)
- `release`: always points to the last release
- `stable` and `master`: **deprecated**. These tags are no longer maintained and may be removed without notice

These images are built automatically on DockerHub and rely on:

- [Alpine Linux](https://www.alpinelinux.org/)
- [PHP7-FPM](http://php-fpm.org/)
- [Nginx](http://nginx.org/)

Here is an example of how to run Shaarli latest image using Podman:

```bash
# download the image from dockerhub
podman pull shaarli/shaarli

# create persistent data volumes/directories on the host
podman volume create shaarli-data
podman volume create shaarli-cache

# create a new container using the Shaarli image
# --detach: run the container in background
# --name: name of the created container/instance
# --publish: map the host's :8000 port to the container's :80 port
# --rm: automatically remove the container when it exits
# --volume: mount persistent volumes in the container ($volume_name:$volume_mountpoint)
podman run --detach \
           --name myshaarli \
           --publish 8000:80 \
           --rm \
           --volume shaarli-data:/var/www/shaarli/data \
           --volume shaarli-cache:/var/www/shaarli/cache \
           shaarli/shaarli:release
           
# verify that the container is running
podman ps | grep myshaarli

# to completely remove the container
podman stop myshaarli # stop the running container
podman ps | grep myshaarli # verify the container is no longer running
podman ps -a | grep myshaarli # verify the container is stopped
podman rm myshaarli # destroy the container
podman ps -a | grep myshaarli # verify th container has been destroyed
```

After running `podman run` command, your Shaarli instance should be available on the host machine at [localhost:8000](http://localhost:8000/). In order to access your instance through a reverse proxy, see [reverse proxy](https://shaarli.readthedocs.io/en/master/Reverse-proxy/).

## Generating systemd service units for containerized Shaarli

Podman is able to create a systemd unit file that can be used to control a container or pod.
