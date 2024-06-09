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
FROM docker.io/alpine:3.19.1
LABEL maintainer="Shaarli Community"

RUN apk --update --no-cache add \
        ca-certificates \
        nginx \
        php82 \
        php82-ctype \
        php82-curl \
        php82-fpm \
        php82-gd \
        php82-gettext \
        php82-iconv \
        php82-intl \
        php82-json \
        php82-ldap \
        php82-mbstring \
        php82-openssl \
        php82-session \
        php82-xml \
        php82-simplexml \
        php82-zlib \
        s6

COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/php-fpm.conf /etc/php82/php-fpm.conf
COPY .docker/services.d /etc/services.d

RUN rm -rf /etc/php82/php-fpm.d/www.conf \
    && sed -i 's/post_max_size.*/post_max_size = 10M/' /etc/php82/php.ini \
    && sed -i 's/upload_max_filesize.*/upload_max_filesize = 10M/' /etc/php82/php.ini


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
