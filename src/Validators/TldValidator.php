<?php

declare(strict_types=1);

namespace App\Validators;

use App\Interfaces\ValidatorInterface;
use App\Interfaces\DomainValidatorInterface;
use App\ValidationResult;

/**
 * Валидатор TLD (Top Level Domain) email адресов
 *
 * Проверяет доменную часть email адреса на соответствие официальному списку
 * доменов верхнего уровня, поддерживаемых IANA (Internet Assigned Numbers Authority).
 *
 * Особенности работы:
 * - Загружает актуальный список TLD с официального сайта IANA
 * - Кэширует данные для повышения производительности
 * - Имеет резервный список TLD на случай недоступности IANA
 * - Поддерживает как новое API (validateDomain), так и старое (validate)
 *
 * @package App\Validators
 * @author Vladimir Matkovskii and Claude 4 Sonnet
 * @version 1.0
 */
class TldValidator implements ValidatorInterface, DomainValidatorInterface
{
    /**
     * URL для загрузки официального списка TLD от IANA
     * Этот список обновляется IANA по мере добавления новых доменов
     */
    private const IANA_TLD_URL = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    /**
     * Путь к файлу кэша для хранения списка TLD
     * Используется для избежания повторных запросов к IANA
     */
    private const TLD_CACHE_FILE = __DIR__ . '/../../cache/tlds.txt';

    /**
     * Время жизни кэша в секундах (24 часа)
     * После истечения этого времени кэш считается устаревшим
     */
    private const CACHE_DURATION = 24 * 60 * 60;

    /**
     * Таймаут для HTTP запросов к IANA в секундах
     * Предотвращает зависание при медленном соединении
     */
    private const HTTP_TIMEOUT = 10;

    /**
     * User-Agent для HTTP запросов
     * Идентифицирует наше приложение в логах IANA
     */
    private const USER_AGENT = 'EmailValidator/1.0 (TLD Checker)';

    /**
     * Массив валидных TLD в верхнем регистре
     * Загружается при инициализации класса
     *
     * @var array<string> Список валидных TLD
     */
    private array $validTlds = [];

    /**
     * Конструктор класса
     *
     * Автоматически загружает список валидных TLD при создании экземпляра класса.
     * Сначала пытается загрузить из кэша, затем с IANA, в крайнем случае - резервный список.
     */
    public function __construct()
    {
        // Инициализируем список TLD при создании объекта
        $this->loadValidTlds();
    }

    /**
     * Валидирует доменную часть email на соответствие официальному списку TLD
     *
     * Это основной метод для нового API. Принимает уже извлеченную доменную часть
     * и проверяет её TLD против списка IANA.
     *
     * @param string $domain Доменная часть email (например, "example.com")
     * @param string $fullEmail Полный email адрес для контекста в сообщениях об ошибках
     * @return ValidationResult Результат валидации с детальной информацией
     */
    public function validateDomain(string $domain, string $fullEmail): ValidationResult
    {
        // Проверяем, что домен не пустой
        if (empty(trim($domain))) {
            return ValidationResult::invalidTld($fullEmail, 'Доменная часть email не может быть пустой');
        }

        // Извлекаем TLD (домен верхнего уровня) из доменной части
        $tld = $this->extractTld($domain);

        // Проверяем, удалось ли извлечь TLD
        if (empty($tld)) {
            return ValidationResult::invalidTld(
                $fullEmail,
                'Невозможно определить TLD домена. Домен должен содержать хотя бы одну точку'
            );
        }

        // Проверяем валидность TLD против списка IANA
        if (!$this->isTldValid($tld)) {
            return ValidationResult::invalidTld(
                $fullEmail,
                "TLD '$tld' не найден в списке официальных доменов IANA. " .
                "Возможно, это опечатка или несуществующий домен верхнего уровня"
            );
        }

        // Все проверки пройдены успешно
        return ValidationResult::valid($fullEmail);
    }

    /**
     * Валидирует email адрес (метод для обратной совместимости)
     *
     * Этот метод поддерживает старое API. Он извлекает доменную часть из email
     * и вызывает основной метод validateDomain.
     *
     * @param string $email Полный email адрес для валидации
     * @return ValidationResult Результат валидации
     */
    public function validate(string $email): ValidationResult
    {
        // Базовая проверка формата email
        if (empty(trim($email))) {
            return ValidationResult::invalidTld($email, 'Email адрес не может быть пустым');
        }

        // Проверяем наличие символа @ (простая проверка формата)
        if (substr_count($email, '@') !== 1) {
            return ValidationResult::invalidTld($email, 'Email должен содержать ровно один символ @');
        }

        // Извлекаем доменную часть из email (часть после @)
        [, $domainPart] = explode('@', $email, 2);

        // Делегируем валидацию основному методу
        return $this->validateDomain($domainPart, $email);
    }

    /**
     * Извлекает TLD (домен верхнего уровня) из доменной части
     *
     * Принимает доменную часть (например, "subdomain.example.com") и возвращает
     * TLD в верхнем регистре (например, "COM").
     *
     * @param string $domain Доменная часть для извлечения TLD
     * @return string Извлеченный TLD в верхнем регистре
     */
    private function extractTld(string $domain): string
    {
        // Разбиваем домен по точкам
        $parts = explode('.', trim($domain));

        // Получаем последнюю часть (это и есть TLD)
        $tld = end($parts);

        // Возвращаем TLD в верхнем регистре для унификации
        return strtoupper($tld);
    }

    /**
     * Проверяет, является ли TLD валидным согласно списку IANA
     *
     * Выполняет поиск TLD в предварительно загруженном списке валидных доменов.
     * Сравнение происходит без учета регистра.
     *
     * @param string $tld TLD для проверки (например, "COM", "RU", "NET")
     * @return bool true если TLD найден в списке IANA, false - если нет
     */
    private function isTldValid(string $tld): bool
    {
        // Приводим TLD к верхнему регистру и ищем в массиве
        // Используем строгое сравнение (strict mode) для точности
        return in_array(strtoupper($tld), $this->validTlds, true);
    }

    /**
     * Основной метод загрузки списка валидных TLD
     *
     * Реализует стратегию загрузки с fallback:
     * 1. Сначала пытается загрузить из кэша
     * 2. Если кэш недоступен или устарел - загружает с IANA
     * 3. Если IANA недоступна - использует резервный список
     *
     * @return void
     */
    private function loadValidTlds(): void
    {
        // Этап 1: Попытка загрузки из кэша
        if ($this->loadFromCache()) {
            // Кэш успешно загружен, выходим
            return;
        }

        // Этап 2: Попытка загрузки с IANA
        if ($this->loadFromIana()) {
            // Данные загружены с IANA, сохраняем в кэш для будущих использований
            $this->saveToCache();
            return;
        }

        // Этап 3: Fallback - используем встроенный резервный список
        $this->loadFallbackTlds();
    }

    /**
     * Загружает список TLD из файла кэша
     *
     * Проверяет существование файла кэша, его актуальность и загружает данные.
     * Если кэш устарел или поврежден - возвращает false.
     *
     * @return bool true если кэш успешно загружен, false - если кэш недоступен или устарел
     */
    private function loadFromCache(): bool
    {
        // Проверяем существование файла кэша
        if (!file_exists(self::TLD_CACHE_FILE)) {
            return false;
        }

        // Получаем время последнего изменения файла
        $cacheTime = filemtime(self::TLD_CACHE_FILE);

        // Вычисляем время истечения кэша
        $expiryTime = time() - self::CACHE_DURATION;

        // Проверяем, не устарел ли кэш
        if ($cacheTime < $expiryTime) {
            return false; // Кэш устарел
        }

        // Читаем содержимое файла кэша
        $content = file_get_contents(self::TLD_CACHE_FILE);
        if ($content === false) {
            return false; // Ошибка чтения файла
        }

        // Разбираем содержимое кэша на отдельные TLD
        $this->validTlds = array_filter(
            explode("\n", trim($content)),
            fn($tld) => !empty(trim($tld)) // Убираем пустые строки
        );

        // Проверяем, что загрузили хотя бы несколько TLD
        return !empty($this->validTlds);
    }

    /**
     * Загружает актуальный список TLD с официального сайта IANA
     *
     * Выполняет HTTP запрос к IANA, парсит полученные данные и фильтрует
     * комментарии и пустые строки.
     *
     * @return bool true если данные успешно загружены с IANA, false - при ошибке
     */
    private function loadFromIana(): bool
    {
        // Настраиваем контекст для HTTP запроса с таймаутом и User-Agent
        $context = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'method' => 'GET',
                'header' => [
                    'Accept: text/plain',
                    'Cache-Control: no-cache'
                ]
            ]
        ]);

        // Выполняем HTTP запрос к IANA с подавлением ошибок
        $content = @file_get_contents(self::IANA_TLD_URL, false, $context);

        // Проверяем успешность запроса
        if ($content === false) {
            return false; // Ошибка загрузки (сеть, таймаут, 404 и т.д.)
        }

        // Разбиваем содержимое на строки
        $lines = explode("\n", trim($content));
        $tlds = [];

        // Обрабатываем каждую строку
        foreach ($lines as $line) {
            $line = trim($line);

            // Пропускаем комментарии (строки начинающиеся с #) и пустые строки
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Добавляем TLD в верхнем регистре
            $tlds[] = strtoupper($line);
        }

        // Проверяем, что получили разумное количество TLD
        if (count($tlds) < 100) {
            // Слишком мало TLD - возможно, сервер IANA вернул ошибку или неполные данные
            return false;
        }

        // Сохраняем загруженные TLD
        $this->validTlds = $tlds;
        return true;
    }

    /**
     * Сохраняет текущий список TLD в файл кэша
     *
     * Создает необходимые директории и записывает список TLD в файл
     * для быстрого доступа при следующих запусках.
     *
     * @return void
     */
    private function saveToCache(): void
    {
        // Получаем директорию для кэша
        $cacheDir = dirname(self::TLD_CACHE_FILE);

        // Создаем директорию если она не существует
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        // Сохраняем TLD в файл (каждый TLD на отдельной строке)
        $content = implode("\n", $this->validTlds);
        @file_put_contents(self::TLD_CACHE_FILE, $content);
    }

    /**
     * Загружает резервный список TLD
     *
     * Используется как fallback когда IANA недоступна и кэш отсутствует.
     * Содержит наиболее популярные и стабильные TLD.
     *
     * @return void
     */
    private function loadFallbackTlds(): void
    {
        // Резервный список популярных и стабильных TLD
        // Этот список используется только в крайнем случае
        $this->validTlds = [
            // Общие TLD (gTLD)
            'COM', 'NET', 'ORG', 'EDU', 'GOV', 'MIL', 'INT', 'INFO', 'BIZ', 'NAME',

            // Новые общие TLD
            'TECH', 'ONLINE', 'SITE', 'WEBSITE', 'STORE', 'APP', 'BLOG', 'NEWS',

            // Страновые коды (ccTLD) - основные
            'AC', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT',
            'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ',
            'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA',
            'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU',
            'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
            'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB',
            'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS',
            'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL',
            'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG',
            'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI',
            'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG',
            'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV',
            'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP',
            'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN',
            'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB',
            'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR',
            'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK',
            'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US',
            'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT',
            'ZA', 'ZM', 'ZW'
        ];
    }

}
