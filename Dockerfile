# Stage 1:
# - Resolve PHP dependencies with Composer
FROM composer:latest as composer
COPY composer.json composer.lock /tmp/shaarli/
WORKDIR /tmp/shaarli
RUN composer --prefer-dist --no-dev install

# Stage 2:
# - Frontend dependencies
FROM node:12-alpine as app
COPY --chown=nginx:nginx . /tmp/shaarli/
WORKDIR /tmp/shaarli
RUN yarn install \
    && yarn run build \
    && rm -rf ./node_modules

# Stage 3:
# - Build documentation
FROM python:3-alpine as docs

COPY mkdocs.yml /tmp/shaarli/
COPY --chown=nginx:nginx doc /tmp/shaarli/doc/
WORKDIR /tmp/shaarli/
RUN pip install --no-cache-dir mkdocs \
    && mkdocs build --clean

# Stage 4:
# - Shaarli image
LABEL maintainer="Shaarli Community"

FROM alpine:3.14 as php
WORKDIR /var/www

# Invalidation cache while new release
COPY --chown=nginx:nginx shaarli_version.php /var/www/shaarli/

RUN apk --update add \
        ca-certificates \
        nginx \
        php7 \
        php7-ctype \
        php7-curl \
        php7-fpm \
        php7-gd \
        php7-iconv \
        php7-intl \
        php7-json \
        php7-mbstring \
        php7-openssl \
        php7-session \
        php7-xml \
        php7-simplexml \
        php7-zlib \
        s6

COPY --chown=nginx:nginx .docker/nginx.conf /etc/nginx/nginx.conf
COPY --chown=nginx:nginx .docker/php-fpm.conf /etc/php7/php-fpm.conf
COPY --chown=nginx:nginx .docker/services.d /etc/services.d

RUN rm -rf /etc/php7/php-fpm.d/www.conf \
    && sed -i 's/post_max_size.*/post_max_size = 10M/' /etc/php7/php.ini \
    && sed -i 's/upload_max_filesize.*/upload_max_filesize = 10M/' /etc/php7/php.ini

COPY --chown=nginx:nginx --from=composer /tmp/shaarli /var/www/shaarli/
COPY --chown=nginx:nginx --from=app /tmp/shaarli /var/www/shaarli/
COPY --chown=nginx:nginx --from=docs /tmp/shaarli /var/www/shaarli/

# disabled due to https://github.com/docker/for-linux/issues/388
#RUN chown -R nginx:nginx /var/www/shaarli/

RUN ln -sf /dev/stdout /var/log/nginx/shaarli.access.log \
    && ln -sf /dev/stderr /var/log/nginx/shaarli.error.log

VOLUME /var/www/shaarli/cache
VOLUME /var/www/shaarli/data

EXPOSE 80

ENTRYPOINT ["/bin/s6-svscan", "/etc/services.d"]
CMD []
