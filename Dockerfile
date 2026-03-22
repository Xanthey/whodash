FROM trafex/php-nginx:latest

USER root

# Install PDO and MySQL extensions
RUN apk --no-cache add php84-pdo php84-pdo_mysql php84-mysqli

# Copy config files
COPY default.conf /etc/nginx/conf.d/default.conf
COPY php.ini /etc/php84/php.ini

# Copy web files
COPY html/ /var/www/html/
RUN rm -f /var/www/html/index.php
# Fix ownership
RUN chown -R nobody:nobody /var/www/html

USER nobody