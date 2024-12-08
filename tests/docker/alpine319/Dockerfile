FROM docker.io/alpine:3.19.4
MAINTAINER Shaarli Community

RUN apk --update --no-cache add \
        ca-certificates \
        curl \
        make \
        php82 \
        php82-ctype \
        php82-curl \
        php82-dom \
        php82-gd \
        php82-gettext \
        php82-iconv \
        php82-intl \
        php82-json \
        php82-mbstring \
        php82-openssl \
        php82-phar \
        php82-session \
        php82-simplexml \
        php82-tokenizer \
        php82-xdebug \
        php82-xmlwriter \
        php82-xml \
        php82-zlib \
        rsync

RUN curl -sS https://getcomposer.org/installer | php82 -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir /shaarli
WORKDIR /shaarli
VOLUME /shaarli

ENTRYPOINT ["make"]
CMD []
