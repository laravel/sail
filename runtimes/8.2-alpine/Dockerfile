FROM php:8.2-cli-alpine3.18

LABEL maintainer="Artefak <the4rtefak@gmail.com>"

ARG WWWGROUP
ARG NODE_VERSION=20.9.0
ARG POSTGRES_VERSION=15
ARG FSWATCH_VERSION=1.17.1

# Bun Dependency
ARG GLIBC_VERSION=2.34-r0
ARG GLIBC_VERSION_AARCH64=2.26-r1

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apk --no-cache add gnupg su-exec curl ca-certificates zip unzip git \
        supervisor sqlite libcap libpng-dev bind-tools librsvg bash shadow php82-dev \
    && apk --no-cache add gcc make g++ zlib-dev \
    && curl https://github.com/emcrisostomo/fswatch/releases/download/$FSWATCH_VERSION/fswatch-$FSWATCH_VERSION.tar.gz \
        -fsSL -o /tmp/fswatch-$FSWATCH_VERSION.tar.gz \
    && cd /tmp/ \
    && tar -xzf fswatch-$FSWATCH_VERSION.tar.gz \
    && cd fswatch-$FSWATCH_VERSION \
    && ./configure \
    && make \
    && make install && cd .. \
    && curl https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -sSL -o - | sh -s \
            pgsql sqlite3 gd imagick curl imap mysqli mbstring \
            xml zip bcmath soap intl readline ldap \
            msgpack igbinary redis swoole \
            memcached pcov xdebug @composer \
    && apk --no-cache add mysql-client postgresql$POSTGRES_VERSION-client \
    && mkdir -p /opt/nodejs \
    && curl -fsSL https://unofficial-builds.nodejs.org/download/release/v$NODE_VERSION/node-v$NODE_VERSION-linux-x64-musl.tar.gz \
        -o /tmp/node-v$NODE_VERSION-linux-x64-musl.tar.gz \
    && cd /tmp/ \
    && tar -zxvf node-v$NODE_VERSION-linux-x64-musl.tar.gz --directory /opt/nodejs --strip-components=1 \
    && rm node-v$NODE_VERSION-linux-x64-musl.tar.gz \
    && ln -s /opt/nodejs/bin/node /usr/local/bin/node \
    && ln -s /opt/nodejs/bin/npm /usr/local/bin/npm \
    && npm install -g npm pnpm yarn \
    && arch="$(apk --print-arch)" \
    && case "${arch##*-}" in \
      x86_64) \
        curl "https://github.com/sgerrand/alpine-pkg-glibc/releases/download/${GLIBC_VERSION}/glibc-${GLIBC_VERSION}.apk" \
            -fsSL -o /tmp/glibc.apk \
        && curl "https://github.com/sgerrand/alpine-pkg-glibc/releases/download/${GLIBC_VERSION}/glibc-bin-${GLIBC_VERSION}.apk" \
            -fsSL -o /tmp/glibc-bin.apk ;; \
      aarch64) \
        curl "https://raw.githubusercontent.com/squishyu/alpine-pkg-glibc-aarch64-bin/master/glibc-${GLIBC_VERSION_AARCH64}.apk" \
            -fsSL -o /tmp/glibc.apk \
        && curl "https://raw.githubusercontent.com/squishyu/alpine-pkg-glibc-aarch64-bin/master/glibc-bin-${GLIBC_VERSION_AARCH64}.apk" \
            -fsSL -o /tmp/glibc-bin.apk;; \
      *) echo "error: unsupported architecture: $arch"; exit 1 ;; \
    esac \
    && apk --no-cache --force-overwrite --allow-untrusted add \
        /tmp/glibc.apk /tmp/glibc-bin.apk \
    && npm install -g bun \
    && ln -s /opt/nodejs/bin/bun /usr/local/bin/bun \
    && ln -s /opt/nodejs/bin/bunx /usr/local/bin/bunx \
    && apk del --purge gcc make g++ zlib-dev \
    && rm -rf /usr/src/* /var/lib/apt/lists/* /var/cache/apk/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/local/bin/php

RUN addgroup -g $WWWGROUP sail
RUN adduser -D -s /bin/bash -G sail -u 1337 sail

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini $PHP_INI_DIR/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 8000

ENTRYPOINT ["start-container"]
