# Official image + server-level hardening.
# Headers are set by Apache (not only PHP) so they also apply to page-cache
# hits, which WP Super Cache serves before WordPress loads.
FROM wordpress:php8.2-apache

RUN a2enmod headers
COPY security-headers.conf /etc/apache2/conf-enabled/zz-encore-security.conf
