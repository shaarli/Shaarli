
# Docker

[Docker](https://docs.docker.com/get-started/overview/) is an open platform for developing, shipping, and running applications in lightweight containers.

## Install Docker

Install [Docker](https://docs.docker.com/engine/install/), by following the instructions relevant to your OS / distribution, and start the service.

_Optional:_ In order to run Docker commands as a non-root user (i.e. without `sudo`), you must add your user account to the `docker` group. Keep in mind that this effectively gives this user account [full root privileges](https://docs.docker.com/engine/security/#docker-daemon-attack-surface), without password.

```bash
# add your user to the docker group
sudo usermod -aG docker your-user
# reboot or logout
# then verify that Docker is properly configured, as "your-user"
docker run hello-world
```

## Get and run a Shaarli image

Shaarli images are available on [GitHub Container Registry](https://github.com/shaarli/Shaarli/pkgs/container/shaarli) `ghcr.io/shaarli/shaarli`:

- `latest`: master (development) branch
- `vX.Y.Z`: shaarli [releases](https://github.com/shaarli/Shaarli/releases)
- `release`: always points to the last release

These images are built automatically on Github Actions and rely on:

- [Alpine Linux](https://www.alpinelinux.org/)
- [PHP-FPM](https://php-fpm.org/)
- [Nginx](https://nginx.org/)

These images are suitable for the `amd64`, `arm/v7` and `arm64` CPU architectures.

```{note}
Additional Dockerfiles are provided for the `arm32v7` platform, relying on [Linuxserver.io Alpine armhf images](https://hub.docker.com/r/lsiobase/alpine.armhf/). These images must be built using [`docker build`](https://docs.docker.com/engine/reference/commandline/build/) on an `arm32v7` machine or using an emulator such as [qemu](https://blog.balena.io/building-arm-containers-on-any-x86-machine-even-dockerhub/).
```

To run the `latest` Shaarli image using Docker:

```bash
# download the 'latest' image from GitHub Container Registry
docker pull ghcr.io/shaarli/shaarli:latest

# create directories for persistent data/cache storage
sudo mkdir /opt/shaarli
sudo mkdir /opt/shaarli/data
sudo mkdir /opt/shaarli/cache

# create a new container using the Shaarli image
# --detach: run the container in background
# --name: name of the created container/instance
# --publish: map the host's :8000 port to the container's :80 port
# --rm: automatically remove the container when it exits
# --volume: mount persistent volumes in the container ($volume_name:$volume_mountpoint)
docker run --detach \
           --name myshaarli \
           --publish 8000:80 \
           --rm \
           --volume /opt/shaarli/data:/var/www/shaarli/data \
           --volume /opt/shaarli/cache:/var/www/shaarli/cache \
           ghcr.io/shaarli/shaarli:latest

# verify that the container is running
docker ps | grep myshaarli
```

Your Shaarli instance should be available on the host machine at [http://localhost:8000](http://localhost:8000). In order to access your instance through a reverse proxy, we recommend using our [Docker Compose](#docker-compose) build.

Stopping the container will also completely remove it (but not persistent volumes) since it was started with `--rm`:

```bash
docker stop myshaarli # stop the running container
docker ps -a | grep myshaarli # verify the container has been destroyed
```

## Docker Compose

A [Compose file](https://docs.docker.com/compose/compose-file/) is a common format for defining and running multi-container Docker applications.

Shaarli provides a `docker-compose.yml` file which can be used to run a persistent/autostarted shaarli service using [Docker Compose](https://docs.docker.com/compose/) or in a [Docker stack](https://docs.docker.com/engine/reference/commandline/stack_deploy/). It sets up a Shaarli instance, a [Træfik](https://traefik.io/traefik/) reverse proxy instance with [Let's Encrypt](https://letsencrypt.org/) certificates, a Docker network, and volumes for Shaarli data and Træfik TLS configuration and certificates.

* Download docker-compose from the [release page](https://docs.docker.com/compose/install/).
* Run the following commands to start Shaarli and its reverse proxy:

```bash
# create a new directory to store your configuration and data
$ sudo mkdir /opt/shaarli
$ sudo mkdir /opt/shaarli/data
$ cd /opt/shaarli
# download the latest version of Shaarli's docker-compose.yml
$ curl -L https://raw.githubusercontent.com/shaarli/Shaarli/latest/docker-compose.yml -o docker-compose.yml
# create the .env file and fill in your VPS and domain information
# (replace <shaarli.mydomain.org>, <admin@mydomain.org> and <latest> with your actual information)
# available Docker tags can be found at https://github.com/shaarli/Shaarli/pkgs/container/shaarli/versions?filters%5Bversion_type%5D=tagged
$ echo 'SHAARLI_VIRTUAL_HOST=shaarli.mydomain.org' | sudo tee .env
$ echo 'SHAARLI_LETSENCRYPT_EMAIL=admin@mydomain.org' | sudo tee -a .env
$ echo 'SHAARLI_DOCKER_TAG=latest' | sudo tee -a .env
# pull the required images
$ docker-compose pull
# run!
$ docker-compose up -d
```

After a few seconds, you should be able to access your Shaarli instance at [https://shaarli.mydomain.org](https://shaarli.mydomain.org).


## Running dockerized Shaarli as a systemd service

It is possible to start a dockerized Shaarli instance as a systemd service (systemd is the service management tool on several distributions), that will start automatically on system boot:

As root, create `/etc/systemd/system/docker.shaarli.service`:

```ini
[Unit]
Description=Shaarli Bookmark Manager Container
After=docker.service
Requires=docker.service

[Service]
Restart=always

# Put any environment you want in an included file, like $host- or $domainname in this example
EnvironmentFile=/opt/shaarli/environment

# It's just an example..
ExecStart=/usr/bin/docker run \
  -p 28010:80 \
  --name ${hostname}-shaarli \
  --hostname shaarli.${domainname} \
  -v /opt/shaarli/data:/var/www/shaarli/data:rw \
  -v /etc/localtime:/etc/localtime:ro \
  ghcr.io/shaarli/shaarli:latest

ExecStop=/usr/bin/docker rm -f ${hostname}-shaarli

[Install]
WantedBy=multi-user.target
```

```bash
# reload systemd service definitions
systemctl daemon-reload
# start the service and enable it a boot time
systemctl enable docker.shaarli.service --now
# verify that the service is running
systemctl status docker.*
# inspect system log if needed
journalctl -f
```

## Docker cheatsheet

```bash
# pull/update an image
$ docker pull ghcr.io/shaarli/shaarli:release
# run a container from an image
$ docker run ghcr.io/shaarli/shaarli:latest
# list available images
$ docker images ls
# list running containers
$ docker ps
# list running AND stopped containers
$ docker ps -a
# run a command in a running container
$ docker exec -ti <container-name-or-first-letters-of-id> bash
# follow logs of a running container
$ docker logs -f <container-name-or-first-letters-of-id>
# delete unused images to free up disk space
$ docker system prune --images
# delete unused volumes to free up disk space (CAUTION all data in unused volumes will be lost)
$ docker system prune --volumes
# delete unused containers
$ docker system prune
```


## References

- [Docker: using volumes](https://docs.docker.com/storage/volumes/)
- [Dockerfile best practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [Dockerfile reference](https://docs.docker.com/engine/reference/builder/)
- [GitHub Container Registry](https://github.com/features/packages)
- [GithHub Packages documentation](https://docs.github.com/en/packages)
- [DockerHub: Teams and organizations](https://docs.docker.com/docker-hub/orgs/), [End of Docker free teams](https://www.docker.com/blog/we-apologize-we-did-a-terrible-job-announcing-the-end-of-docker-free-teams/)
- [Get Docker CE for Debian](https://docs.docker.com/engine/install/debian/)
- [Install Docker Compose](https://docs.docker.com/compose/install/)
- [Service management: Nginx in the foreground](https://nginx.org/en/docs/ngx_core_module.html#daemon)
- [Service management: Run multiple services in a container](https://docs.docker.com/config/containers/multi-service_container/)
- [Volumes](https://docs.docker.com/storage/volumes/)
- [Where are Docker images stored?](https://blog.thoward37.me/articles/where-are-docker-images-stored/)
- [docker create](https://docs.docker.com/engine/reference/commandline/create/)
- [Docker Documentation](https://docs.docker.com/)
- [docker exec](https://docs.docker.com/engine/reference/commandline/exec/)
- [docker images](https://docs.docker.com/engine/reference/commandline/images/)
- [docker logs](https://docs.docker.com/engine/reference/commandline/logs/)
- [Docker Overview](https://docs.docker.com/get-started/overview/)
- [docker ps](https://docs.docker.com/engine/reference/commandline/ps/)
- [docker pull](https://docs.docker.com/engine/reference/commandline/pull/)
- [docker run](https://docs.docker.com/engine/reference/commandline/run/)
- Træfik: [Documentation](https://doc.traefik.io/traefik/), [Docker image](https://hub.docker.com/_/traefik/)
