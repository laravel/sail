FROM ubuntu:21.10

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=16
ARG POSTGRES_VERSION=14
ARG PHP_VERSION=7.4

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN    curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add - \
    && mkdir -p ~/.gnupg \
    && chmod 600 ~/.gnupg \
    && echo "disable-ipv6" >> ~/.gnupg/dirmngr.conf \
    && apt-key adv --homedir ~/.gnupg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C \
    && echo "deb https://ppa.launchpadcontent.net/ondrej/php/ubuntu impish main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && echo "deb http://apt.postgresql.org/pub/repos/apt impish-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && curl -sL https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python2 \
       php$PHP_VERSION-cli php$PHP_VERSION-dev \
       php$PHP_VERSION-pgsql php$PHP_VERSION-sqlite3 php$PHP_VERSION-gd \
       php$PHP_VERSION-curl \
       php$PHP_VERSION-imap php$PHP_VERSION-mysql php$PHP_VERSION-mbstring \
       php$PHP_VERSION-xml php$PHP_VERSION-zip php$PHP_VERSION-bcmath php$PHP_VERSION-soap \
       php$PHP_VERSION-intl php$PHP_VERSION-readline \
       php$PHP_VERSION-ldap \
       php$PHP_VERSION-msgpack php$PHP_VERSION-igbinary php$PHP_VERSION-redis php$PHP_VERSION-swoole \
       php$PHP_VERSION-memcached php$PHP_VERSION-pcov php$PHP_VERSION-xdebug \
       nodejs \
       yarn \
       mysql-client \
       postgresql-client \
    && php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer \
    && npm install -g npm \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php7.4

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/7.4/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 8000

ENTRYPOINT ["start-container"]
