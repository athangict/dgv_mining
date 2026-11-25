# The code uses PHP 8.0 syntax; laminas-form 2.x (pinned in composer.json)
# supports PHP up to 8.0, so 8.0 is the target runtime.
FROM php:8.0-apache

# php:8.0 is based on Debian bullseye (EOL) - use the Debian archive mirrors
RUN printf '%s\n' \
      'deb http://archive.debian.org/debian bullseye main' \
      'deb http://archive.debian.org/debian-security bullseye-security main' \
      > /etc/apt/sources.list \
 && rm -f /etc/apt/sources.list.d/*

# System libs + PHP extensions used by the app (PDO MySQL, intl, gd, zip, opcache)
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libicu-dev libzip-dev zlib1g-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli intl gd zip opcache \
 && rm -rf /var/lib/apt/lists/*

# Apache: serve /var/www/public and honour public/.htaccess rewrites
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && rm -rf /var/www/html

# Also serve the app under /dgv.mining.com/public so URLs match the SSO
# redirect URI registered for the old local setup
# (http://localhost/dgv.mining.com/public/auth/callback).
ARG APP_URL_PREFIX=/dgv.mining.com/public
RUN echo "Alias ${APP_URL_PREFIX} /var/www/public" > /etc/apache2/conf-available/app-prefix.conf \
 && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
 && a2enconf app-prefix servername

# Behind Dokploy's Traefik proxy TLS ends at the proxy: mark requests that
# arrived over HTTPS so PHP sees $_SERVER['HTTPS']=on. Hide version banners.
RUN echo 'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on' > /etc/apache2/conf-available/proxy-https.conf \
 && a2enconf proxy-https \
 && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

# PHP error display, controlled from the environment. public/index.php calls
# ini_set('display_errors', 1); php_admin_flag cannot be overridden by
# ini_set, so production (PHP_DISPLAY_ERRORS=Off) never shows errors to
# visitors - without editing index.php. Errors still go to the container log.
ENV PHP_DISPLAY_ERRORS=On
RUN { \
      echo 'php_admin_flag display_errors ${PHP_DISPLAY_ERRORS}'; \
      echo 'php_admin_flag log_errors On'; \
    } > /etc/apache2/conf-available/php-errors.conf \
 && a2enconf php-errors

# PHP settings
RUN { \
      echo 'upload_max_filesize=64M'; \
      echo 'post_max_size=64M'; \
      echo 'memory_limit=512M'; \
      echo 'max_execution_time=300'; \
      echo 'date.timezone=Asia/Thimphu'; \
      echo 'output_buffering=4096'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.revalidate_freq=60'; \
    } > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Install PHP dependencies first (cached layer). The committed vendor/ is
# incomplete, so it is excluded via .dockerignore and rebuilt here.
# --no-plugins/--no-scripts keep laminas installers from rewriting config.
# The skeleton/component installers are project-setup tools (not used at
# runtime) and do not support PHP 8, so they are dropped for the image build,
# as is the dev-only laminas-test; the "php": "^7.3" constraint is widened to match the code.
COPY composer.json ./
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/* \
 && composer remove --no-update --no-plugins --no-scripts \
      laminas/laminas-skeleton-installer laminas/laminas-component-installer \
 && composer remove --dev --no-update --no-plugins --no-scripts laminas/laminas-test \
 && composer require --no-update --no-plugins --no-scripts 'php:^7.3 || ^8.0' \
 && composer update --no-dev --no-plugins --no-scripts --no-interaction --prefer-dist --no-autoloader

COPY . /var/www
# Deployment-level config overrides (see the file header)
COPY docker/config/zz-docker.local.php /var/www/config/autoload/zz-docker.local.php
RUN composer dump-autoload --optimize --no-dev --no-plugins --no-scripts

# Writable dirs. Uploads live in data/uploads (private, config
# file_manager.dir), public/upload (file_manager.public_dir) and
# public/uploads (HR photos) - mount these as persistent volumes.
RUN mkdir -p data/cache data/uploads public/upload public/uploads \
 && chown -R www-data:www-data data public

EXPOSE 80

# /auth/login touches PHP, the app config and the database (sessions)
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD curl -fsS -o /dev/null http://localhost/auth/login || exit 1
