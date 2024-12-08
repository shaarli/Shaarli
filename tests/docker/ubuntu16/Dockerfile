FROM docker.io/ubuntu:16.04
MAINTAINER Shaarli Community

ENV TERM dumb
ENV DEBIAN_FRONTEND noninteractive
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US:en

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
       ca-certificates \
       curl \
       language-pack-de \
       language-pack-en \
       language-pack-fr \
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

ADD https://getcomposer.org/composer.phar /usr/local/bin/composer
RUN chmod 755 /usr/local/bin/composer

RUN useradd -m dev \
    && mkdir /shaarli
USER dev
WORKDIR /shaarli

ENTRYPOINT ["make"]
CMD []
