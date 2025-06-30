<?php

declare(strict_types=1);

namespace App;

/**
 * Класс EmailValidator
 * 
 * Расширенная версия валидатора email-адресов с поддержкой
 * различных типов проверок и детальной информации об ошибках
 */
class EmailValidator
{
    /**
     * Список актуальных TLD от IANA (упрощенная версия)
     * В реальном проекте этот список следует загружать из IANA
     */
    private const VALID_TLDS = [
        'com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'arpa',
        'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'ao', 'aq',
        'ar', 'as', 'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd',
        'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br',
        'bs', 'bt', 'bw', 'by', 'bz', 'ca', 'cc', 'cd', 'cf', 'cg',
        'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co', 'cr', 'cu', 'cv',
        'cw', 'cx', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do', 'dz',
        'ec', 'ee', 'eg', 'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk',
        'fm', 'fo', 'fr', 'ga', 'gb', 'gd', 'ge', 'gf', 'gg', 'gh',
        'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr', 'gs', 'gt', 'gu',
        'gw', 'gy', 'hk', 'hm', 'hn', 'hr', 'ht', 'hu', 'id', 'ie',
        'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it', 'je', 'jm',
        'jo', 'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr',
        'kw', 'ky', 'kz', 'la', 'lb', 'lc', 'li', 'lk', 'lr', 'ls',
        'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'md', 'me', 'mg', 'mh',
        'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt',
        'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc', 'ne', 'nf',
        'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa',
        'pe', 'pf', 'pg', 'ph', 'pk', 'pl', 'pm', 'pn', 'pr', 'ps',
        'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru', 'rw', 'sa',
        'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sk', 'sl', 'sm',
        'sn', 'so', 'sr', 'ss', 'st', 'sv', 'sx', 'sy', 'sz', 'tc',
        'td', 'tf', 'tg', 'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to',
        'tr', 'tt', 'tv', 'tw', 'tz', 'ua', 'ug', 'uk', 'us', 'uy',
        'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf', 'ws',
        'ye', 'yt', 'za', 'zm', 'zw'
    ];

    /**
     * Валидирует список email-адресов
     * 
     * @param array $emails Массив email-адресов для проверки
     * @return array Результат валидации с детальной информацией
     */
    public function validate(array $emails): array
    {
        $results = [];

        foreach ($emails as $email) {
            $email = trim($email);

            // Пропускаем пустые строки
            if (empty($email)) {
                continue;
            }

            $results[] = $this->validateSingleEmail($email);
        }

        return $results;
    }

    /**
     * Валидирует один email-адрес
     * 
     * @param string $email Email-адрес для проверки
     * @return array Результат валидации
     */
    private function validateSingleEmail(string $email): array
    {
        // Проверка синтаксиса
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $email,
                'status' => 'invalid_format',
                'reason' => 'Некорректный формат email-адреса'
            ];
        }

        // Получаем домен
        $domain = substr(strrchr($email, "@"), 1);

        // Проверка TLD
        if (!$this->hasValidTld($domain)) {
            return [
                'email' => $email,
                'status' => 'invalid_tld',
                'reason' => 'Недопустимая доменная зона'
            ];
        }

        // Проверка MX-записи
        if (!$this->hasValidMxRecord($domain)) {
            return [
                'email' => $email,
                'status' => 'invalid_mx',
                'reason' => 'Домен не имеет MX-записи'
            ];
        }

        return [
            'email' => $email,
            'status' => 'valid',
            'reason' => 'Email-адрес валиден'
        ];
    }

    /**
     * Проверяет наличие MX-записей у домена
     * 
     * @param string $domain Доменное имя
     * @return bool true, если MX-записи найдены
     */
    private function hasValidMxRecord(string $domain): bool
    {
        try {
            return checkdnsrr($domain, 'MX');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Проверяет валидность TLD домена
     * 
     * @param string $domain Доменное имя
     * @return bool true, если TLD валиден
     */
    private function hasValidTld(string $domain): bool
    {
        $tld = strtolower(substr(strrchr($domain, '.'), 1));
        return in_array($tld, self::VALID_TLDS, true);
    }
}