# Stage 1:
# - Copy Shaarli sources
# - Build documentation
FROM python:3-alpine as docs
ADD . /usr/src/app/shaarli
RUN cd /usr/src/app/shaarli \
    && apk add --no-cache gcc musl-dev \
    && pip install --no-cache-dir mkdocs \
    && mkdocs build --clean

# Stage 2:
# - Resolve PHP dependencies with Composer
FROM composer:latest as composer
COPY --from=docs /usr/src/app/shaarli /app/shaarli
RUN cd shaarli \
    && composer --prefer-dist --no-dev install

# Stage 3:
# - Frontend dependencies
FROM node:12-alpine as node
COPY --from=composer /app/shaarli shaarli
RUN cd shaarli \
    && yarnpkg install \
    && yarnpkg run build \
    && rm -rf node_modules

# Stage 4:
# - Shaarli image
FROM alpine:3.16
LABEL maintainer="Shaarli Community"

RUN apk --update --no-cache add \
        ca-certificates \
        nginx \
        php8 \
        php8-ctype \
        php8-curl \
        php8-fpm \
        php8-gd \
        php8-gettext \
        php8-iconv \
        php8-intl \
        php8-json \
        php8-ldap \
        php8-mbstring \
        php8-openssl \
        php8-session \
        php8-xml \
        php8-simplexml \
        php8-zlib \
        s6

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-fpm.conf /etc/php8/php-fpm.conf
COPY .docker/services.d /etc/services.d

RUN rm -rf /etc/php8/php-fpm.d/www.conf \
    && sed -i 's/post_max_size.*/post_max_size = 10M/' /etc/php8/php.ini \
    && sed -i 's/upload_max_filesize.*/upload_max_filesize = 10M/' /etc/php8/php.ini


WORKDIR /var/www
COPY --from=node /shaarli shaarli

RUN chown -R nginx:nginx . \
    && ln -sf /dev/stdout /var/log/nginx/shaarli.access.log \
    && ln -sf /dev/stderr /var/log/nginx/shaarli.error.log

VOLUME /var/www/shaarli/cache
VOLUME /var/www/shaarli/data

EXPOSE 80

ENTRYPOINT ["/bin/s6-svscan", "/etc/services.d"]
CMD []
