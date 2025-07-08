# 📧 Верификация Email и балансировка нагрузки

## 📦 Описание проекта

Учебный проект для закрепления практических навыков работы с Docker, PHP-FPM, Nginx, Redis Cluster и фронтендом на Vue.js.

Основная задача — реализовать веб-сервис, принимающий список email-адресов через POST-запрос, проверяющий их корректность с точки зрения формата и наличия DNS-записи типа MX.

Проект построен на микросервисной архитектуре с разделением на:

✅ балансировщик (nginx upstream)
✅ два backend-а (nginx + php-fpm)
✅ Redis Cluster для хранения сессий и статистики
✅ frontend-приложение (Vue + Vite), предоставляющее веб-форму для проверки

## 🧱 Стек технологий

* PHP 8.4 (FPM)
* Nginx (proxy и backend)
* Redis Cluster (для сессий)
* Docker / Docker Compose
* Composer (PSR-4 autoload)
* Vue 3 + Vite + Axios

## 📁 Структура проекта

```
otus-php-hw05/
├── balancer/
│   └── nginx.conf
├── config/
│   └── redis.php
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
├── scripts/
│   ├── test_redis_connection.php
│   └── test_tld_cache.php
├── src/
│   ├── Cache/
│   │   └── RedisCacheAdapter.php
│   ├── Interfaces/
│   │   ├── DomainValidatorInterface.php
│   │   ├── PartsValidatorInterface.php
│   │   └── ValidatorInterface.php
│   ├── Validators/
│   │   ├── MxValidator.php
│   │   ├── SyntaxValidator.php
│   │   └── TldValidator.php
│   ├── EmailController.php
│   ├── EmailValidator.php
│   ├── EmailVerificationService.php
│   ├── RedisHealthChecker.php
│   ├── Router.php
│   ├── StatsCollector.php
│   ├── ValidationRequest.php
│   └── ValidationResult.php
├── tests/
│   ├── Unit/
│   │   ├── RedisHealthCheckerTest.php
│   │   └── RouterTest.php
│   ├── comprehensive_validation_test.php
│   ├── detailed_validation_test.php
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

### Архитектура проекта

Проект представляет собой **REST API сервис** для валидации email адресов с использованием многоуровневой архитектуры и Redis кластера для кэширования.

### Основные классы и их назначение

#### **ValidationResult** (DTO класс)
**Назначение**: Объект передачи данных для результатов валидации.
**Методы**:
- `__construct(string $email, string $status, ?string $reason = null)` — создание результата
- `static valid(string $email): ValidationResult` — создание успешного результата
- `static invalidFormat(string $email, string $reason): ValidationResult` — результат для неверного формата
- `static invalidTld(string $email, string $reason): ValidationResult` — результат для неверного TLD
- `static invalidMx(string $email, string $reason): ValidationResult` — результат для неверной MX записи

#### **EmailValidator** (Композитный валидатор)
**Назначение**: Главный координатор валидации, объединяющий все специализированные валидаторы.
**Методы**:
- `__construct(SyntaxValidator $syntaxValidator, TldValidator $tldValidator, MxValidator $mxValidator)` — инициализация с валидаторами
- `validate(string $email): ValidationResult` — быстрая валидация с стратегией "fail-fast"
- `static createDefault(): EmailValidator` — фабричный метод для создания валидатора по умолчанию
- `getValidators(): array` — получение доступа к внутренним валидаторам

#### **SyntaxValidator** (Валидатор синтаксиса)
**Назначение**: Проверка синтаксиса email адресов согласно RFC 5322.
**Интерфейсы**: `ValidatorInterface`, `PartsValidatorInterface`
**Методы**:
- `validate(string $email): ValidationResult` — валидация полного email
- `validateParts(string $localPart, string $domainPart, string $fullEmail): ValidationResult` — валидация частей email
- `validateLocalPart(string $localPart): array` — валидация локальной части
- `validateDomainPart(string $domainPart): array` — валидация доменной части

#### **TldValidator** (Валидатор доменов верхнего уровня)
**Назначение**: Проверка TLD против официального списка IANA с Redis кэшированием.
**Интерфейсы**: `ValidatorInterface`, `DomainValidatorInterface`
**Методы**:
- `validate(string $email): ValidationResult` — валидация полного email
- `validateDomain(string $domain, string $fullEmail): ValidationResult` — валидация доменной части
- `getTldsList(): array` — получение списка валидных TLD
- `loadTldsFromIana(): array` — загрузка TLD с официального сайта IANA
- `loadTldsFromCache(): ?array` — загрузка TLD из Redis кэша

#### **MxValidator** (Валидатор MX записей)
**Назначение**: Проверка наличия MX записей в DNS для доменной части.
**Интерфейсы**: `ValidatorInterface`, `DomainValidatorInterface`
**Методы**:
- `validate(string $email): ValidationResult` — валидация полного email
- `validateDomain(string $domain, string $fullEmail): ValidationResult` — валидация доменной части
- `checkMxRecords(string $domain): array` — проверка MX записей в DNS
- `checkARecord(string $domain): bool` — проверка A записи как fallback

#### **EmailVerificationService** (Сервисный слой)
**Назначение**: Бизнес-логический слой для массовой валидации email адресов.
**Методы**:
- `__construct(EmailValidator $emailValidator)` — инициализация с валидатором
- `verifyForApi(array $emails): array` — валидация для API с форматированием результатов
- `verifyEmails(array $emails): array` — базовая валидация массива email
- `verifyEmailsWithStats(array $emails): array` — валидация с генерацией статистики
- `generateStats(array $results): array` — генерация статистики результатов

#### **EmailController** (HTTP контроллер)
**Назначение**: Обработка HTTP запросов для REST API валидации.
**Методы**:
- `__construct(EmailVerificationService $verificationService)` — инициализация с сервисом
- `verify(): void` — основной эндпоинт для валидации массива email
- `getRequestData(): array` — получение и парсинг данных из запроса
- `setJsonHeaders(): void` — установка HTTP заголовков для JSON
- `sendSuccessResponse(array $data): void` — отправка успешного ответа
- `sendErrorResponse(string $message, int $code = 400): void` — отправка ошибки

#### **Router** (Маршрутизатор)
**Назначение**: Простой маршрутизатор для обработки HTTP запросов.
**Методы**:
- `addRoute(string $method, string $path, callable $handler): void` — добавление маршрута
- `dispatch(): void` — диспетчеризация запроса
- `isValidPath(string $path): bool` — валидация пути
- `parseUri(): array` — парсинг URI запроса

#### **ValidationRequest** (Валидатор запросов)
**Назначение**: Валидация и обработка входящих HTTP запросов.
**Методы**:
- `validate(array $data): array` — валидация данных запроса
- `extractEmails(string $text): array` — извлечение email из текста
- `validateTextLength(string $text): bool` — проверка длины текста
- `sanitizeInput(array $data): array` — очистка входных данных

#### **RedisHealthChecker** (Мониторинг Redis)
**Назначение**: Проверка состояния Redis Cluster для мониторинга.
**Методы**:
- `__construct(?array $config = null)` — инициализация с подключением к Redis
- `getClusterStatus(): array` — получение статуса всех узлов кластера
- `isConnected(): bool` — проверка общего состояния кластера
- `getRequiredQuorum(): int` — получение требуемого кворума

### Взаимодействие классов

**Иерархия вызовов**:
```
HTTP Request → Router → EmailController → EmailVerificationService → EmailValidator → [SyntaxValidator, TldValidator, MxValidator] → ValidationResult
```

**Связи между классами**:
1. **EmailController** использует **EmailVerificationService** через конструктор (dependency injection)
2. **EmailVerificationService** использует **EmailValidator** через конструктор
3. **EmailValidator** использует все три валидатора через конструктор:
   - **SyntaxValidator**
   - **TldValidator**
   - **MxValidator**
4. **TldValidator** использует **RedisCacheAdapter** для кэширования TLD списков
5. **Router** вызывает методы **EmailController**
6. Все валидаторы возвращают **ValidationResult**

**Интерфейсы**:
- **ValidatorInterface** — базовый интерфейс для всех валидаторов
- **DomainValidatorInterface** — специализированный интерфейс для валидации доменов
- **PartsValidatorInterface** — интерфейс для валидации частей email

### Особенности архитектуры
1. **Композитный паттерн** в EmailValidator для объединения валидаторов
2. **Dependency Injection** для слабой связанности компонентов
3. **Стратегия "fail-fast"** для быстрой валидации
4. **Redis кэширование** для производительности TLD валидации
5. **Интерфейсы** для обеспечения контрактов между компонентами
6. **DTO паттерн** в ValidationResult для передачи данных
7. **Сервисный слой** для инкапсуляции бизнес-логики

Формат ответа API:

* ✅ 200 OK — возвращается список email с флагами "valid"/"invalid" и причинами
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
