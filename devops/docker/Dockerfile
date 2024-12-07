FROM techcto/alpine-php-fpm-8.1

RUN set -ex

ARG COMPOSER_AUTH
ENV COMPOSER_AUTH ${COMPOSER_AUTH}

ARG APP_ENV=dev
ENV APP_ENV ${APP_ENV}
ARG SOURCE_DIR=.

#Set Environment
ENV APP_DIR=/var/www \
    COMPOSER_CACHE_DIR=/var/cache/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR ${APP_DIR}

#Install App
COPY public ./html
COPY src ./src
COPY views ./views

#Configure Tmp & Session
RUN mkdir -p /var/lib/php/tmp /var/lib/php/session ./tmp ./data \
    && mkdir -p ${COMPOSER_CACHE_DIR} \
    && chown -Rf www-data:www-data ${APP_DIR} /var/lib/php/session /var/lib/php/tmp ${COMPOSER_CACHE_DIR} \
    && chmod -Rf 770 ${APP_DIR} /var/lib/php/session /var/lib/php/tmp ${COMPOSER_CACHE_DIR}

COPY $SOURCE_DIR/composer.* ${APP_DIR}/
RUN if [ -f composer.json ]; then \
    composer install --no-scripts --no-interaction --optimize-autoloader --no-progress --prefer-dist \
    && rm -rf ${COMPOSER_CACHE_DIR}/* ; fi

#Entrypoint
COPY devops/docker/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod a+rx /docker-entrypoint.sh
ENTRYPOINT ["sh", "/docker-entrypoint.sh"]