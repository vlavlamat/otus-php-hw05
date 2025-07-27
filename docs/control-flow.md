# Control Flow — Потоки управления для EmailПроверка+

---

## **Описание проекта**

**EmailПроверка+** — это сервис для валидации email адресов с поддержкой современных TLD, MX-проверки и Redis кластера для кэширования. Сервис предоставляет REST API для валидации массивов email адресов и мониторинга состояния инфраструктуры.

**Архитектурные слои:**
- **Entry Point** — public/index.php
- **Application** — App, Router, Middleware
- **Controllers** — EmailController, RedisHealthController  
- **Services** — EmailVerificationService
- **Domain** — EmailValidator, ValidationResult
- **Validators** — SyntaxValidator, TldValidator, MxValidator
- **Infrastructure** — RedisHealthChecker, RedisCacheAdapter

---

## **Модуль "Email Validation" — Валидация email адресов**

### *[Email Validation — Валидация массива email адресов (Web/API)]*

```
1. public/index.php (Entry Point)
2. → App::run()
3. → CorsMiddleware::handle() (обработка CORS заголовков)
4. → Router::dispatch('POST', '/verify')
5. → EmailController::verify()
6. → EmailController::getRequestData() (парсинг JSON из php://input)
7. → InputValidator::validateTextLength($inputText)
8. → EmailController::parseEmails($inputText) (парсинг по разделителям)
9. → InputValidator::validateArraySize($emails)
10. → EmailVerificationService::verifyForApi($emails)
11. → EmailVerificationService::verify($emails) (цикл по каждому email)
12. → EmailValidator::validate($email) (для каждого email)
13. → SyntaxValidator::validate($email) (проверка синтаксиса RFC 5322)
14. → TldValidator::validate($email) (проверка TLD через IANA + Redis кэш)
15. → MxValidator::validate($email) (проверка MX записи DNS)
16. → ValidationResult::valid/invalidFormat/invalidTld/invalidMx (создание результата)
17. → ValidationResult::toArray() (преобразование в массив для JSON)
18. ← JsonResponse::success($results) (возврат результатов клиенту)
```

**Детализация по подпотокам:**

#### *[Подпоток — Синтаксическая валидация]*
```
1. SyntaxValidator::validate($email)
2. → SyntaxValidator::validateParts($localPart, $domainPart, $fullEmail)
3. → SyntaxValidator::validateLocalPart($localPart) (длина, точки, спецсимволы)
4. → SyntaxValidator::validateDomainPart($domainPart) (длина, точки, формат)
5. → filter_var($fullEmail, FILTER_VALIDATE_EMAIL) (финальная проверка PHP)
6. ← ValidationResult::valid/invalidFormat($email, $reason)
```

#### *[Подпоток — TLD валидация с Redis кэшированием]*
```
1. TldValidator::validate($email)
2. → TldValidator::validateDomain($domainPart, $fullEmail)
3. → TldValidator::extractTld($domain) (извлечение TLD)
4. → TldValidator::isTldValid($tld)
5. → TldValidator::loadValidTlds() (если кэш пуст)
6. → RedisCacheAdapter::get(REDIS_TLD_CACHE_KEY) (попытка загрузки из Redis)
7. → TldValidator::fetchTldsFromIana() (если кэш пуст - загрузка с IANA API)
8. → RedisCacheAdapter::set(REDIS_TLD_CACHE_KEY, $tlds) (сохранение в кэш)
9. ← ValidationResult::valid/invalidTld($email, $reason)
```

#### *[Подпоток — MX валидация]*
```
1. MxValidator::validate($email)
2. → MxValidator::extractDomain($email) (извлечение домена)
3. → checkdnsrr($domain, 'MX') (проверка MX записи через DNS)
4. ← ValidationResult::valid/invalidMx($email, $reason)
```

---

## **Модуль "Infrastructure Monitoring" — Мониторинг инфраструктуры**

### *[Health Check — Проверка состояния Redis кластера (Web/API)]*

```
1. public/index.php (Entry Point)
2. → App::run()
3. → CorsMiddleware::handle() (обработка CORS заголовков)
4. → Router::dispatch('GET', '/status')
5. → RedisHealthController::getStatus()
6. → RedisHealthChecker::isConnected() (проверка подключения к кластеру)
7. → RedisHealthChecker::getClusterStatus() (статус каждого узла кластера)
8. → RedisHealthChecker::getRequiredQuorum() (минимальное количество узлов)
9. → RedisHealthController (подсчет статистики: connectedCount, totalNodes)
10. → RedisHealthController (формирование детального ответа с метаданными)
11. ← JsonResponse::status('OK', $statusData) (возврат статуса клиенту)
```

**В случае ошибки:**
```
1-5. (аналогично успешному сценарию)
6. → RedisHealthChecker::isConnected() (исключение при подключении)
7. → catch (Throwable $e)
8. ← JsonResponse::status('error', $errorData) (возврат ошибки клиенту)
```

---

## **Модуль "Application Bootstrap" — Инициализация приложения**

### *[Application Startup — Запуск приложения (System)]*

```
1. public/index.php (Entry Point)
2. → require vendor/autoload.php (загрузка Composer автолоадера)
3. → EnvironmentLoader::load() (загрузка переменных окружения)
4. → App::__construct()
5. → CorsMiddleware::__construct() (инициализация CORS middleware)
6. → Router::__construct() (инициализация маршрутизатора)
7. → App::setupRoutes() (регистрация маршрутов)
8. → EmailController::createDefault() (создание контроллера с зависимостями)
9. → RedisHealthController::createDefault() (создание контроллера мониторинга)
10. → Router::addRoute('POST', '/verify', [EmailController, 'verify'])
11. → Router::addRoute('GET', '/status', [RedisHealthController, 'getStatus'])
12. → App::run() (запуск обработки запросов)
```

---

## **Обработка ошибок и исключений**

### *[Exception Handling — Централизованная обработка ошибок (System)]*

```
1. App::run() (try-catch блок)
2. → Router::dispatch() (может выбросить исключение)
3. → Controller::method() (может выбросить исключение)
4. → catch (Throwable $e)
5. → App::handleException($e) (централизованная обработка)
6. → http_response_code(500)
7. → header('Content-Type: application/json; charset=utf-8')
8. → json_encode($error) (формирование JSON ошибки)
9. ← echo $errorJson (возврат ошибки клиенту)
```

**В development режиме добавляется отладочная информация:**
```
5. → App::handleException($e)
6. → getenv('APP_ENV') === 'development' (проверка режима)
7. → $error['debug'] = [...] (добавление файла, строки, стека вызовов)
8-9. (аналогично production)
```

---

## **Вспомогательные потоки**

### *[CORS Preflight — Обработка предварительных CORS запросов (Web)]*

```
1. App::run()
2. → CorsMiddleware::isPreflight() (проверка OPTIONS запроса)
3. → CorsMiddleware::handlePreflight() (если preflight)
4. → Response::send() (отправка CORS заголовков)
5. ← return (завершение без дальнейшей обработки)
```

### *[Input Validation — Валидация входных данных (Service)]*

```
1. InputValidator::validateTextLength($text)
2. → strlen($text) > MAX_TEXT_LENGTH (проверка длины)
3. → throw InvalidArgumentException (если превышен лимит)

1. InputValidator::validateArraySize($array, $type)
2. → count($array) > MAX_ARRAY_SIZE (проверка размера массива)
3. → throw InvalidArgumentException (если превышен лимит)
```

---

## **Архитектурные особенности**

### **Fail-Fast стратегия валидации:**
EmailValidator использует fail-fast подход — останавливается на первой найденной ошибке:
1. SyntaxValidator (если невалиден → возврат)
2. TldValidator (если невалиден → возврат)  
3. MxValidator (если невалиден → возврат)
4. ValidationResult::valid() (если все проверки пройдены)

### **Redis кэширование:**
TldValidator использует двухуровневое кэширование:
1. Память (массив $validTlds)
2. Redis кластер (ключ REDIS_TLD_CACHE_KEY)
3. Fallback на IANA API при отсутствии кэша

### **Dependency Injection:**
Все контроллеры используют фабричные методы createDefault() для инициализации зависимостей, что обеспечивает слабую связанность и тестируемость.

---

**Все control flows покрывают полный жизненный цикл запросов от входной точки до возврата результата клиенту, включая обработку ошибок и edge-cases.**