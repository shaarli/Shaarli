FROM docker.io/alpine:3.23.2
MAINTAINER Shaarli Community

RUN apk --update --no-cache add \
        ca-certificates \
        curl \
        make \
        php84 \
        php84-ctype \
        php84-curl \
        php84-dom \
        php84-gd \
        php84-gettext \
        php84-iconv \
        php84-intl \
        php84-json \
        php84-mbstring \
        php84-openssl \
        php84-phar \
        php84-session \
        php84-simplexml \
        php84-tokenizer \
        php84-xdebug \
        php84-xmlwriter \
        php84-xml \
        php84-zlib \
        rsync

RUN curl -sS https://getcomposer.org/installer | php84 -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir /shaarli
WORKDIR /shaarli
VOLUME /shaarli

ENTRYPOINT ["make"]
CMD []
