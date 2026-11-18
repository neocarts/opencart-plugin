# One image for every OpenCart branch: the PHP base and the release are build args.
ARG PHP_IMAGE=php:8.3-apache
FROM ${PHP_IMAGE}

ARG OC_URL
ARG OC_SHA256
# 2.3's CLI installer refuses to run without mcrypt (bundled with PHP 7.1 only).
ARG WITH_MCRYPT=0

RUN set -eux; \
    if grep -q stretch /etc/os-release 2>/dev/null; then \
      sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org|archive.debian.org|g; /stretch-updates/d' /etc/apt/sources.list; \
    elif grep -q buster /etc/os-release 2>/dev/null; then \
      sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org|archive.debian.org|g; /buster-updates/d' /etc/apt/sources.list 2>/dev/null || true; \
    fi; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip curl ca-certificates default-mysql-client \
      libpng-dev libjpeg-dev libfreetype6-dev libzip-dev zlib1g-dev $( [ "$WITH_MCRYPT" = 1 ] && echo libmcrypt-dev ); \
    if php -r 'exit(PHP_VERSION_ID < 70400 ? 0 : 1);'; then \
      docker-php-ext-configure gd --with-freetype-dir=/usr --with-jpeg-dir=/usr; \
    else \
      docker-php-ext-configure gd --with-freetype --with-jpeg; \
    fi; \
    docker-php-ext-install -j"$(nproc)" mysqli gd zip $( [ "$WITH_MCRYPT" = 1 ] && echo mcrypt ); \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    curl -fsSL -o /tmp/oc.zip "$OC_URL"; \
    echo "$OC_SHA256  /tmp/oc.zip" | sha256sum -c -; \
    unzip -q /tmp/oc.zip -d /tmp/oc; \
    mv /tmp/oc/upload /opt/opencart; \
    rm -rf /tmp/oc /tmp/oc.zip

# The MariaDB 11 client insists on TLS; the local db has none.
RUN printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/zz-no-ssl.cnf 2>/dev/null \
    || (mkdir -p /etc/mysql/conf.d && printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/zz-no-ssl.cnf)
COPY php.ini /usr/local/etc/php/conf.d/zz-opencart.ini
COPY entrypoint.sh /usr/local/bin/oc-entrypoint
RUN chmod +x /usr/local/bin/oc-entrypoint

ENTRYPOINT ["oc-entrypoint"]
CMD ["apache2-foreground"]
