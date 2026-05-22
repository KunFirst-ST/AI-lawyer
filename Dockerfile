FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && docker-php-ext-install pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker/apache/app.conf /etc/apache2/conf-available/ai-lawyer-app.conf
COPY docker/start-apache.sh /usr/local/bin/start-apache

RUN a2enconf ai-lawyer-app \
    && chmod +x /usr/local/bin/start-apache \
    && mkdir -p storage/logs storage/sessions uploads/case_documents uploads/lawyer_documents uploads/profile_images uploads/slips uploads/message_media \
    && chown -R www-data:www-data storage uploads \
    && find storage uploads -type d -exec chmod 775 {} \; \
    && find storage uploads -type f -exec chmod 664 {} \;

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=10000

EXPOSE 10000

CMD ["start-apache"]
