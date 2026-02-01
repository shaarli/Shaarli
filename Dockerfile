# Stage 1:
# - Copy Shaarli sources
# - Build documentation
FROM docker.io/python:3-alpine as docs
ADD . /usr/src/app/shaarli
RUN cd /usr/src/app/shaarli \
    && apk add --no-cache gcc musl-dev make bash \
    && make htmldoc

# Stage 2:
# - Resolve PHP dependencies with Composer
FROM docker.io/composer:latest as composer
COPY --from=docs /usr/src/app/shaarli /app/shaarli
RUN cd shaarli \
    && composer --prefer-dist --no-dev install

# Stage 3:
# - Frontend dependencies
FROM docker.io/node:18-alpine as node
COPY --from=composer /app/shaarli shaarli
RUN cd shaarli \
    && yarnpkg install \
    && yarnpkg run build \
    && rm -rf node_modules

# Stage 4:
# - Shaarli image
FROM docker.io/alpine:3.23.3
LABEL maintainer="Shaarli Community"

RUN apk --no-cache del icu-data-en \
    && apk --update --no-cache add \
        ca-certificates \
        icu-data-full \
        nginx \
        php84 \
        php84-ctype \
        php84-curl \
        php84-fpm \
        php84-gd \
        php84-gettext \
        php84-iconv \
        php84-intl \
        php84-json \
        php84-ldap \
        php84-mbstring \
        php84-openssl \
        php84-session \
        php84-xml \
        php84-simplexml \
        php84-zlib \
        s6

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-fpm.conf /etc/php84/php-fpm.conf
COPY .docker/services.d /etc/services.d

RUN rm -rf /etc/php84/php-fpm.d/www.conf \
    && sed -i 's/post_max_size.*/post_max_size = 10M/' /etc/php84/php.ini \
    && sed -i 's/upload_max_filesize.*/upload_max_filesize = 10M/' /etc/php84/php.ini


WORKDIR /var/www
COPY --from=node /shaarli shaarli

RUN chown -R nginx:nginx . \
    && ln -sf /dev/stdout /var/log/nginx/shaarli.access.log \
    && ln -sf /dev/stderr /var/log/nginx/shaarli.error.log

VOLUME /var/www/shaarli/cache
VOLUME /var/www/shaarli/data

EXPOSE 80

ENTRYPOINT ["/usr/bin/s6-svscan", "/etc/services.d"]
CMD []
