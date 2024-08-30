FROM banderson/symfony

COPY symfony/assets             /var/www/symfony/assets
COPY symfony/bin                /var/www/symfony/bin
COPY symfony/config             /var/www/symfony/config
COPY symfony/migrations         /var/www/symfony/migrations
COPY symfony/public             /var/www/symfony/public
COPY symfony/src                /var/www/symfony/src
COPY symfony/templates          /var/www/symfony/templates
COPY symfony/tests              /var/www/symfony/tests
COPY symfony/translations       /var/www/symfony/translations
COPY symfony/.env               /var/www/symfony/.env
COPY symfony/composer.lock      /var/www/symfony/composer.lock
COPY symfony/composer.json      /var/www/symfony/composer.json
COPY symfony/importmap.php      /var/www/symfony/importmap.php
COPY symfony/phpunit.xml.dist   /var/www/symfony/phpunit.xml.dist
COPY symfony/symfony.lock       /var/www/symfony/symfony.lock

RUN mkdir -p /var/www/symfony/var \
    && chown -R www-data:www-data /var/www/symfony/var \
    && mkdir /messages \
    && mkdir /var/www/symfony/credentials \
    && chown -R www-data:www-data /var/www/symfony/credentials

RUN composer install
