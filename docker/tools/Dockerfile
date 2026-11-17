# Dev tooling image: same PHP version as the site (8.2) so PHPCS and PHPUnit
# run against the PHP we deploy on, not whatever the host has (or lacks).
FROM php:8.2-cli

RUN apt-get update \
	&& apt-get install -y --no-install-recommends git unzip \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
