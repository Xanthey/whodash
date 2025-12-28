FROM trafex/php-nginx:latest

USER root

# Install PDO and MySQL extensions
RUN apk --no-cache add php84-pdo php84-pdo_mysql php84-mysqli

# (Optional) Install other extensions you might need
# RUN apk --no-cache add php8-sqlite3 php8-pdo_sqlite

USER nobody