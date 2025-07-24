<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\JsonResponse;
use App\Services\EmailVerificationService;
use App\Validators\InputValidator;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Контроллер для валидации email адресов
 */
class EmailController
{
    private EmailVerificationService $verificationService;
    private InputValidator $inputValidator;

    public function __construct(
        EmailVerificationService $verificationService,
        InputValidator           $inputValidator
    )
    {
        $this->verificationService = $verificationService;
        $this->inputValidator = $inputValidator;
    }

    public function verify(): void
    {
        try {
            $requestData = $this->getRequestData();

            if (!isset($requestData['text']) || !is_string($requestData['text'])) {
                JsonResponse::error('Поле "text" обязательно и должно быть строкой')
                    ->send();
                return;
            }

            $inputText = $requestData['text'];

            $this->inputValidator->validateTextLength($inputText);

            $emails = $this->parseEmails($inputText);

            $this->inputValidator->validateArraySize($emails, 'email адресов');

            $results = $this->verificationService->verifyForApi($emails);

            JsonResponse::success([
                'success' => true,
                'results' => $results,
                'total' => count($results),
                'parsed_count' => count($emails),
                'original_text' => $this->truncateText($inputText)
            ])->send();

        } catch (InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage())->send();
        } catch (JsonException $e) {
            JsonResponse::error('Некорректный JSON в запросе: ' . $e->getMessage())
                ->send();
        } catch (Throwable $e) {
            JsonResponse::error(
                'Внутренняя ошибка сервера: ' . $e->getMessage(),
                'INTERNAL_ERROR',
                500
            )->send();
        }
    }

    /**
     * Получает и парсит данные из тела HTTP запроса
     */
    private function getRequestData(): array
    {
        // Читаем сырые данные из входящего запроса
        $input = file_get_contents('php://input');

        // Возвращаем пустой массив для пустых запросов
        if (empty($input)) {
            return [];
        }

        // Парсим JSON с включенным исключением при ошибках
        $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

        // Гарантируем, что возвращаем массив (на случай, если JSON содержит не объект)
        return is_array($data) ? $data : [];
    }

    /**
     * Обрезает текст до фиксированной длины для включения в ответ
     */
    private function truncateText(string $text): string
    {
        $maxLength = 100;

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }

    /**
     * Парсит текст с email адресами в массив
     *
     * Разбивает входной текст по всем поддерживаемым разделителям,
     * нормализует данные и возвращает очищенный массив уникальных email адресов.
     */
    private function parseEmails(string $text): array
    {
        // Разделители для парсинга email адресов в порядке приоритета
        $separators = [
            "\r\n",  // Windows переносы строк (должен быть перед \n)
            "\n",    // Unix/Linux переносы строк
            ",\n",   // Запятая перед переносом строки
            ";\n",   // Точка с запятой перед переносом строки
            ", ",    // Запятая с пробелом (стандартный CSV формат)
            "; ",    // Точка с запятой с пробелом
            ",",     // Одиночная запятая
            ";",     // Точка с запятой
            " ",     // Пробелы (обрабатывается последним)
        ];

        // Начинаем с исходного текста как единого элемента
        $items = [$text];

        // Последовательно разбиваем по каждому разделителю
        foreach ($separators as $separator) {
            $newItems = [];
            foreach ($items as $item) {
                if (is_string($item) && str_contains($item, $separator)) {
                    $splitItems = explode($separator, $item);
                    $newItems = array_merge($newItems, $splitItems);
                } else {
                    $newItems[] = $item;
                }
            }
            $items = $newItems;
        }

        // Нормализуем данные: trim + фильтрация пустых + удаление дубликатов
        $cleaned = [];
        foreach ($items as $item) {
            $cleanItem = trim((string)$item);
            $cleanItem = trim($cleanItem, ",; \n\r");
            if ($cleanItem !== '') {
                $cleaned[] = $cleanItem;
            }
        }

        return array_values(array_unique($cleaned));
    }

    /**
     * Фабричный метод для создания контроллера с зависимостями по умолчанию
     */
    public static function createDefault(): EmailController
    {
        return new self(
            EmailVerificationService::createDefault(),
            new InputValidator()
        );
    }
}
