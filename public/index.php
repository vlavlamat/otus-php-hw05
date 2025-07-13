<?php

declare(strict_types=1);

/**
 * Главная точка входа для Email Validator API
 *
 * Этот файл является основным контроллером приложения, который:
 * - Настраивает автозагрузку классов через Composer
 * - Инициализирует маршрутизацию и контроллеры
 * - Обрабатывает HTTP запросы и возвращает JSON ответы
 * - Управляет CORS политиками для cross-origin запросов
 * - Предоставляет эндпоинты для валидации email и мониторинга статуса Redis
 */

// Подключаем автозагрузчик Composer
// Автозагрузчик необходим для автоматического подключения всех классов приложения
require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\EmailController;
use App\Controllers\RedisHealthController;
use App\Core\Router;

/**
 * Обработка preflight запросов (OPTIONS)
 *
 * Браузеры отправляют OPTIONS запросы перед основными запросами
 * для проверки CORS политик. Отвечаем успешно и завершаем выполнение.
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Инициализация сессий с поддержкой Redis Cluster
 *
 * session_start() автоматически использует настроенный Redis Cluster
 * для хранения сессий. Это обеспечивает масштабируемость и отказоустойчивость.
 */
session_start(); // ← автоматически использует RedisCluster!

/**
 * Инициализация основных компонентов приложения
 *
 * Создаем экземпляры основных классов:
 * - Router: для маршрутизации HTTP запросов
 * - EmailController: для обработки валидации email адресов
 * - RedisHealthController: для мониторинга состояния Redis Cluster
 */
$router = new Router();
$emailController = EmailController::createDefault();
$healthController = RedisHealthController::createDefault();

/**
 * Регистрация маршрута для валидации email адресов
 *
 * POST /verify - основной эндпоинт для валидации email
 * Принимает JSON с массивом email адресов и возвращает результат валидации
 */
$router->addRoute('POST', '/verify', [$emailController, 'verify']);

/**
 * Регистрация маршрута для проверки статуса системы
 *
 * GET /status - эндпоинт для мониторинга состояния сервиса
 * Возвращает информацию о:
 * - Общем статусе приложения
 * - Состоянии Redis Cluster
 * - Статистике подключенных узлов
 * - Соответствии кворуму для отказоустойчивости
 */
$router->addRoute('GET', '/status', [$healthController, 'getStatus']);

/**
 * Диспетчеризация HTTP запросов
 *
 * Получаем информацию о текущем запросе из суперглобальных переменных
 * и передаем в роутер для обработки соответствующим контроллером.
 *
 * Поддерживаемые методы: GET, POST, OPTIONS
 * Все неизвестные маршруты будут обработаны роутером с возвратом 404
 */
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Запускаем маршрутизацию и обработку запроса
$router->dispatch($method, $uri);