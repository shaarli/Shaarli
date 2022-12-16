FROM alpine:3.16
MAINTAINER Shaarli Community

RUN apk --update --no-cache add \
        ca-certificates \
        curl \
        make \
        php8 \
        php8-ctype \
        php8-curl \
        php8-dom \
        php8-gd \
        php8-gettext \
        php8-iconv \
        php8-intl \
        php8-json \
        php8-mbstring \
        php8-openssl \
        php8-phar \
        php8-session \
        php8-simplexml \
        php8-tokenizer \
        php8-xdebug \
        php8-xmlwriter \
        php8-xml \
        php8-zlib \
        rsync

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir /shaarli
WORKDIR /shaarli
VOLUME /shaarli

ENTRYPOINT ["make"]
CMD []
