FROM php:7.0-apache
MAINTAINER kevogod <https://github.com/kevogod/cachechu>
LABEL description "https://github.com/kevogod/cachechu"

EXPOSE 80

WORKDIR /var/www/html

ENV COMPOSER_VERSION 1.2.0
ENV CACHECHU_VERSION 1.6

# Install composer
ADD https://getcomposer.org/installer /tmp/composer-setup.php
RUN php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --version=${COMPOSER_VERSION} && rm -rf /tmp/composer-setup.php

RUN curl https://codeload.github.com/kevogod/cachechu/tar.gz/cachechu-${CACHECHU_VERSION} | tar --strip-components=1 -xzC /var/www/html

RUN composer install --no-dev --no-progress