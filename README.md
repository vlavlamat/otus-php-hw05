Вот адаптированный `README.md` под домашнее задание с верификацией email, с сохранением архитектурного стиля предыдущего проекта:

---

# 📧 Домашнее задание №6 — Верификация Email и балансировка нагрузки

## 📦 Описание проекта

Учебный проект для закрепления практических навыков работы с Docker, PHP-FPM, Nginx, Redis Cluster и фронтендом на Vue.js.

Основная задача — реализовать веб-сервис, принимающий список email-адресов через POST-запрос, проверяющий их корректность с точки зрения формата и наличия DNS-записи типа MX.

Проект построен на микросервисной архитектуре с разделением на:

✅ балансировщик (nginx upstream)
✅ два backend-а (nginx + php-fpm)
✅ Redis Cluster для хранения сессий и статистики
✅ frontend-приложение (Vue + Vite), предоставляющее веб-форму для проверки

## 🧱 Стек технологий

* PHP 8.3 (FPM)
* Nginx (балансировщик и backend)
* Redis Cluster (для сессий и статистики)
* Docker / Docker Compose
* Composer (PSR-4 autoload)
* Vue 3 + Vite + Axios

## 📁 Структура проекта

```
otus-php-hw06/
├── balancer/
│   └── nginx.conf
├── docker/
│   ├── balancer/
│   │   └── balancer.Dockerfile
│   ├── frontend/
│   │   ├── vue.dev.Dockerfile
│   │   ├── vue.prod.Dockerfile
│   │   └── nginx.conf
│   ├── nginx/
│   │   └── nginx.Dockerfile
│   └── php/
│       ├── php.Dockerfile
│       ├── php.ini
│       ├── php-fpm.conf
│       ├── conf.d/
│       │   └── session.redis.ini
│       ├── www.conf
├── frontend/
│   ├── src/
│   │   ├── utils/
│   │   │   └── emailGenerator.js
│   │   ├── App.vue
│   │   └── main.js
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
├── nginx/
│   ├── conf.d/
│   │   └── default.conf
│   └── nginx.conf
├── public/
│   └── index.php
├── src/
│   ├── Router.php
│   ├── StatsCollector.php
│   └── EmailValidator.php
├── tests/
│   ├── Unit/
│   │   └── EmailValidatorTest.php
│   ├── manual_test.php
│   └── redis_test.php
├── vendor/
├── .gitignore
├── composer.json
├── docker-compose.yml
├── docker-compose.dev.yml
├── docker-compose.prod.yml
├── Makefile
└── README.md
```

## ⚙️ Как запустить проект

### Dev-режим

```bash
make dev-build
make dev-down
```

### Prod-режим

```bash
make prod-up
make prod-down
```

### Команды Makefile

* `make dev-up`
* `make dev-down`
* `make dev-build`
* `make dev-rebuild`
* `make dev-logs`
* `make prod-up`
* `make prod-down`
* `make prod-build-local`
* `make prod-push`
* `make prod-logs`
* `make ps`

## 🧪 Проверка работы

Тестирование выполняется через веб-интерфейс по адресу [http://localhost](http://localhost):

🔸 введите список email-адресов (по одному на строку)
🔸 отправьте на верификацию
🔸 получите список валидных/невалидных email с указанием причины
🔸 статус Redis Cluster отображается внизу страницы и обновляется каждые 30 секунд

## 🔍 Верификация Email

Логика реализована в `src/EmailValidator.php` и включает:

* Проверку формата email через регулярные выражения
* Проверку наличия DNS MX-записи домена
* Обработка пустых и некорректных строк

Формат ответа:

* ✅ 200 OK — возвращается список email с флагами "valid"/"invalid"
* ❌ 400 Bad Request — если входные данные невалидны (например, пустой список)

## 🌐 Архитектура Nginx

Балансировщик перенаправляет запросы между backend и frontend:

```nginx
upstream backend_upstream {
    server nginx-backend1:80;
    server nginx-backend2:80;
}

upstream frontend_upstream {
    server frontend:80;
}

location / {
    proxy_pass http://frontend_upstream;
    proxy_http_version 1.1;
}

location /api/ {
    proxy_pass http://backend_upstream;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

## 🛡️ Сессии и Redis Cluster

Сессии и статистика сохраняются в Redis Cluster:

```
session.save_handler = rediscluster
session.save_path = "seed[]=redis-node1:6379&seed[]=redis-node2:6379&seed[]=redis-node3:6379&prefix=otus_hw06:"
```

Redis используется для:

* Сохранения PHP-сессий
* Сбора статистики по email-проверкам через `StatsCollector`

## ✅ Выполненные требования

* [x] Docker-контейнеры: nginx, php-fpm, redis
* [x] POST-запрос `/api/verify-emails`
* [x] Валидация формата email и DNS-записей
* [x] Балансировка между backend-ами
* [x] Redis Cluster для сессий и статистики
* [x] Разделение dev и prod окружений
* [x] Frontend-интерфейс на Vue с генерацией email-данных
* [x] Сбор и хранение статистики
* [x] Мониторинг Redis Cluster

---

## 📮 Автор

**Vladimir Matkovskii** — [vlavlamat@icloud.com](mailto:vlavlamat@icloud.com)

---

Если хочешь — я позже помогу внести обновления, когда появятся точные имена API-эндпоинтов, классов или деталей фронтенда.
