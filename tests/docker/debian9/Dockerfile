FROM docker.io/debian:stretch
MAINTAINER Shaarli Community

ENV TERM dumb
ENV DEBIAN_FRONTEND noninteractive
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US:en

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
       ca-certificates \
       curl \
       locales \
       make \
       php7.0 \
       php7.0-curl \
       php7.0-gd \
       php7.0-intl \
       php7.0-xml \
       php-xdebug \
       rsync \
    && apt-get clean

RUN locale-gen en_US.UTF-8 \
    && locale-gen de_DE.UTF-8 \
    && locale-gen fr_FR.UTF-8

ADD https://getcomposer.org/composer.phar /usr/local/bin/composer
RUN chmod 755 /usr/local/bin/composer

RUN mkdir /shaarli
WORKDIR /shaarli
VOLUME /shaarli

ENTRYPOINT ["make"]
CMD []
