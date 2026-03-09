FROM php:8.2-cli-alpine

RUN apk add --no-cache unzip curl && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-interaction --no-progress

COPY phpunit.xml phpstan.neon ./
COPY packages/php/src/ ./packages/php/src/
COPY packages/php/tests/ ./packages/php/tests/

CMD ["vendor/bin/phpunit", "--testdox"]
