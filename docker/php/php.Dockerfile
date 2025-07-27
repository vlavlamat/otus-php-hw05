FROM php:8.4-fpm-alpine

# Устанавливаем нужные пакеты
RUN apk add --no-cache \
      unzip git curl zlib-dev autoconf bash \
      gcc g++ make libc-dev pkgconf re2c \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && pecl install pcov \
 && docker-php-ext-enable pcov \
 && apk del gcc g++ make libc-dev pkgconf re2c autoconf

# apk --no-cache — установка пакетов без сохранения кеша, уменьшает размер образа
# unzip — для распаковки zip-архивов (например, composer)
# git — для работы с git-репозиториями (при composer install/update)
# curl — для HTTP-запросов
# zlib-dev — библиотека для работы со сжатием, требуется многим расширениям PHP
# autoconf, gcc, g++, make, libc-dev, pkgconf, re2c — необходимые инструменты и компиляторы для сборки PHP-расширений через pecl
# После установки расширений инструментальные пакеты удаляются для минимизации размера образа
# pecl install redis — установка расширения Redis для PHP
# docker-php-ext-enable redis — активация установленного расширения Redis
# pecl install pcov — установка расширения покрытия кода pcov для PHP
# docker-php-ext-enable pcov — активация установленного расширения pcov

# Удаляем стандартные примеры конфигураций, чтобы не мешали кастомным
RUN rm -f \
        /usr/local/etc/php-fpm.conf.default \
        /usr/local/etc/php-fpm.d/www.conf.default \
        /usr/local/etc/php-fpm.d/zz-docker.conf

# Копируем настройки PHP
COPY ./php/php.ini.prod /usr/local/etc/php/conf.d/local.ini
COPY ./php/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY ./php/conf.d/ /usr/local/etc/php/conf.d/
COPY ./php/www.conf /usr/local/etc/php-fpm.d/www.conf

# php.ini.prod — основной файл настройки PHP
# php-fpm.conf — основной файл конфигурации PHP-FPM
# conf.d/ — директория с дополнительными настройками PHP
# www.conf — конфигурация пула процессов PHP-FPM

# Копируем бинарник Composer из официального образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /app

# Копируем composer-файлы отдельно для кеширования зависимостей
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

# Копируем оставшиеся файлы проекта
COPY ./src /app/src
COPY ./public /app/public
COPY ./config /app/config

CMD ["php-fpm"]
