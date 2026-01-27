ARG PHP_IMAGE_TAG=8.2-fpm-alpine3.19

FROM php:$PHP_IMAGE_TAG

COPY entrypoint.sh /

WORKDIR /var/www/html

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "copy('https://composer.github.io/installer.sig', 'composer-setup.sig');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('composer-setup.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php'); unlink('composer-setup.sig');" && \
    mv composer.phar /usr/local/bin/composer

# Install PDO extension for MySQL/MariaDB
RUN docker-php-ext-install pdo_mysql

# Requirements for production deployment
RUN apk add findutils gnupg lftp tzdata

# Set timezone
RUN cp /usr/share/zoneinfo/Europe/Prague /etc/localtime && \
    echo "Europe/Prague" > /etc/timezone
