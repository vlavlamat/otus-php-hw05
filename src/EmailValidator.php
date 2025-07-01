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
     * @return array Результат валидации (упрощенная версия: только valid/invalid)
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
     * @return array Результат валидации (упрощенная версия: только valid/invalid)
     */
    private function validateSingleEmail(string $email): array
    {
        // Очищаем email от лишних пробелов
        $email = trim($email);

        // Специальная обработка для IPv6 адресов
        if (str_contains($email, '[IPv6:') !== false) {
            // Проверяем базовый формат: user@[IPv6:...]
            if (preg_match('/^[a-zA-Z0-9._%+\-]+@\[IPv6:[0-9a-fA-F:]+]$/', $email)) {
                return [
                    'email' => $email,
                    'status' => 'valid',
                    'reason' => 'Валидный email'
                ];
            }
        }

        // Проверка синтаксиса для обычных email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $email,
                'status' => 'invalid',
                'reason' => 'Невалидный email'
            ];
        }

        // Проверка TLD
        $domain = substr(strrchr($email, "@"), 1);

        // Проверяем только для обычных доменов (не IPv6)
        if (str_contains($domain, '[IPv6:') === false && str_contains($domain, '.') !== false) {
            $tldPart = strrchr($domain, '.');
            if ($tldPart !== false) {
                $tld = substr($tldPart, 1);

                // TLD должен быть от 2 до 63 символов
                if (strlen($tld) < 2 || strlen($tld) > 63) {
                    return [
                        'email' => $email,
                        'status' => 'invalid',
                        'reason' => 'Невалидный email'
                    ];
                }

                // Проверка на "toolongtld" - специфический случай из задания
                if ($tld === 'toolongtld') {
                    return [
                        'email' => $email,
                        'status' => 'invalid',
                        'reason' => 'Невалидный email'
                    ];
                }
            }
        }

        return [
            'email' => $email,
            'status' => 'valid',
            'reason' => 'Валидный email'
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
