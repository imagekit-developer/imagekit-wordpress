FROM php:8.4.16-apache

# --------------------------------------------------
# PHP base configuration
# --------------------------------------------------
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# set php memory limit to -1
RUN sed -i 's/memory_limit = .*/memory_limit = -1/' $PHP_INI_DIR/php.ini

# Enable .htaccess files & mod_rewrite
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride all/' /etc/apache2/apache2.conf
RUN a2enmod rewrite

# --------------------------------------------------
# System packages (WordPress-like setup)
# --------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ghostscript \
        git \
        less \
        ssh-client \
        mariadb-client \
        curl \
        ca-certificates \
        bash \
    ; \
    rm -rf /var/lib/apt/lists/*

# --------------------------------------------------
# PHP extensions + Xdebug
# --------------------------------------------------
RUN set -ex; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg-dev \
        libmagickwand-dev \
        libpng-dev \
        libzip-dev \
        libyaml-dev \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j "$(nproc)" \
        bcmath \
        exif \
        gd \
        mysqli \
        opcache \
        zip \
    ; \
    pecl install imagick-3.8.0; \
    pecl install xdebug-3.4.3; \
    pecl install yaml; \
    pear install PHP_CodeSniffer; \
    docker-php-ext-enable imagick xdebug yaml; \
    rm -rf /var/lib/apt/lists/*

# --------------------------------------------------
# Xdebug config
# --------------------------------------------------
RUN echo "[xdebug]\n\
xdebug.mode = debug\n\
xdebug.start_with_request = 1" \
> $PHP_INI_DIR/conf.d/xdebug.ini

# --------------------------------------------------
# OPcache recommended settings (WP)
# --------------------------------------------------
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# --------------------------------------------------
# WP-CLI
# --------------------------------------------------
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# --------------------------------------------------
# Composer
# --------------------------------------------------
WORKDIR /tmp
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# --------------------------------------------------
# Create vscode user
# --------------------------------------------------
RUN useradd -ms /bin/bash vscode \
    && usermod -aG www-data vscode

# --------------------------------------------------
# NVM + Node.js (for vscode user)
# --------------------------------------------------
USER vscode

ENV NVM_DIR=/home/vscode/.nvm
ENV NODE_VERSION=20

RUN curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash \
    && . "$NVM_DIR/nvm.sh" \
    && nvm install $NODE_VERSION \
    && nvm alias default $NODE_VERSION \
    && nvm use default

# Load nvm automatically in shells
RUN echo 'export NVM_DIR="$HOME/.nvm"' >> /home/vscode/.bashrc \
 && echo '[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"' >> /home/vscode/.bashrc \
 && echo '[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"' >> /home/vscode/.bashrc

# Make node available for non-login shells (VS Code, CI, etc.)
ENV PATH=/home/vscode/.nvm/versions/node/v20.*/bin:$PATH

# --------------------------------------------------
# WordPress setup
# --------------------------------------------------
WORKDIR /var/www/html
USER www-data

# Download WordPress core
RUN wp core download

# Create plugin directory
RUN mkdir -p /var/www/html/wp-content/plugins/imagekit

# --------------------------------------------------
# Permissions
# --------------------------------------------------
USER root
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod g+w -R /var/www/html/ \
    && find /var/www/html/ -type d -exec chmod g+s {} \;
