FROM php:8.4-fpm-alpine

# Устанавливаем нужные пакеты
RUN apk add --no-cache unzip git curl zlib-dev autoconf bash $PHPIZE_DEPS \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && pecl install pcov \
 && docker-php-ext-enable pcov \
 && apk del $PHPIZE_DEPS

# apk --no-cache - установка пакетов без сохранения кеша, что уменьшает размер образа
# unzip - для распаковки zip-архивов
# git - для работы с Git-репозиториями
# curl - для HTTP-запросов
# zlib-dev - библиотека для сжатия данных, нужна для многих PHP-расширений
# $PHPIZE_DEPS - переменная окружения, содержащая список пакетов, необходимых для компиляции PHP-расширений
# pecl install redis - установка PHP-расширения для работы с Redis через PECL
# docker-php-ext-enable redis - активация установленного расширения Redis
# apk del PHPIZE_DEPS - удаление компиляторов и инструментов для сборки после установки, чтобы уменьшить размер образа

# Удаляем мешающий конфиг
RUN rm -f \
        /usr/local/etc/php-fpm.conf.default \
        /usr/local/etc/php-fpm.d/www.conf.default \
        /usr/local/etc/php-fpm.d/zz-docker.conf

# Копируем настройки PHP
COPY ./php/php.ini.prod /usr/local/etc/php/conf.d/local.ini
COPY ./php/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY ./php/conf.d/ /usr/local/etc/php/conf.d/
COPY ./php/www.conf /usr/local/etc/php-fpm.d/www.conf

# php.ini.dev - основной файл настройки PHP
# php-fpm.conf - основной конфигурационный файл для PHP-FPM
# conf.d/ - директория с дополнительными конфигурационными файлами PHP
# www.conf - конфигурация пула процессов PHP-FPM

# Копируем бинарник Composer из официального образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /app

# Копируем composer файлы отдельно (для кеша)
COPY composer.json composer.lock ./

# Передаем аргумет для управления dev/prod-зависимостями
ARG INSTALL_DEV=false

# Устанавливаем зависимости с условием
RUN if [ "$INSTALL_DEV" = "true" ]; then \
      composer update --no-interaction --prefer-dist --no-scripts; \
    else \
      composer install --no-interaction --prefer-dist --no-scripts --no-dev; \
    fi

# Если INSTALL_DEV=true, устанавливаются все зависимости (включая dev)
# Если INSTALL_DEV=false (по умолчанию), устанавливаются только prod-зависимости
# --no-interaction - выполнение без интерактивных вопросов
# --prefer-dist - предпочтение загрузки пакетов из дистрибутивов, а не из исходников
# --no-scripts - пропуск выполнения скриптов, определенных в composer.json
# --no-dev - пропуск установки dev-зависимостей (только для prod-варианта)

# Копируем оставишеся файлы проекта
COPY ./src /app/src
COPY ./public /app/public
COPY ./config /app/config

CMD ["php-fpm"]
