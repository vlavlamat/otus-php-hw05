<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use App\Http\JsonResponse;
use ReflectionClass;
use ReflectionException;

/**
 * Тесты для класса JsonResponse
 *
 * Проверяет корректность создания и отправки JSON ответов
 * @covers \App\Http\JsonResponse
 */
class JsonResponseTest extends TestCase
{
    /**
     * Вспомогательный метод для тестирования метода send()
     * Обрабатывает исключения header() в тестовой среде
     *
     * @throws Exception
     */
    private function assertJsonSendOutput(JsonResponse $response, array $expectedData): void
    {
        ob_start();

        try {
            $response->send();
            $output = ob_get_clean();

            // Проверяем, что вывод является валидным JSON
            $this->assertJson($output);

            $decodedOutput = json_decode($output, true);
            $this->assertEquals($expectedData, $decodedOutput);
        } catch (Exception $e) {
            ob_end_clean();
            // Если исключение связано с header(), то проверяем через геттеры
            if (str_contains($e->getMessage(), 'headers already sent')) {
                // Проверяем данные через геттер
                $this->assertEquals($expectedData, $response->getData());
                // Дополнительно проверяем JSON содержимое
                $jsonContent = $response->getJsonContent();
                $this->assertJson($jsonContent);
                $this->assertEquals($expectedData, json_decode($jsonContent, true));
            } else {
                throw $e;
            }
        }
    }

    /**
     * Тест конструктора с параметрами по умолчанию
     */
    public function testConstructorWithDefaults(): void
    {
        $data = ['message' => 'test'];
        $response = new JsonResponse($data);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);
        $this->assertEquals($data, $actualData);

        // Проверяем статус код по умолчанию
        $statusCodeProperty = $reflection->getProperty('statusCode');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $statusCodeProperty->setAccessible(true);
        $statusCode = $statusCodeProperty->getValue($response);
        $this->assertEquals(200, $statusCode);

        // Проверяем заголовки по умолчанию
        $headersProperty = $reflection->getProperty('headers');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $headersProperty->setAccessible(true);
        $headers = $headersProperty->getValue($response);
        $this->assertEquals([], $headers);
    }

    /**
     * Тест конструктора с пользовательскими параметрами
     */
    public function testConstructorWithCustomParameters(): void
    {
        $data = ['error' => 'Not found'];
        $statusCode = 404;
        $headers = ['X-Custom-Header' => 'custom-value'];

        $response = new JsonResponse($data, $statusCode, $headers);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);
        $this->assertEquals($data, $actualData);

        // Проверяем статус код
        $statusCodeProperty = $reflection->getProperty('statusCode');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $statusCodeProperty->setAccessible(true);
        $actualStatusCode = $statusCodeProperty->getValue($response);
        $this->assertEquals($statusCode, $actualStatusCode);

        // Проверяем заголовки
        $headersProperty = $reflection->getProperty('headers');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $headersProperty->setAccessible(true);
        $actualHeaders = $headersProperty->getValue($response);
        $this->assertEquals($headers, $actualHeaders);
    }

    /**
     * Тест метода send() с базовыми данными
     * @throws Exception
     */
    public function testSendWithBasicData(): void
    {
        $data = ['message' => 'success'];
        $response = new JsonResponse($data); // Создаем объект, а не вызываем статически

        ob_start();
        try {
            $response->send(); // Вызываем метод на объекте
            $output = ob_get_clean();

            // Добавляем проверки
            $this->assertJson($output);
            $this->assertStringContainsString('success', $output);

            $decoded = json_decode($output, true);
            $this->assertEquals($data, $decoded);
        } catch (Exception $e) {
            ob_end_clean();
            if (str_contains($e->getMessage(), 'headers already sent')) {
                // Проверяем через геттеры
                $this->assertEquals($data, $response->getData());
                $this->assertJson($response->getJsonContent());
                $this->assertEquals($data, json_decode($response->getJsonContent(), true));
            } else {
                throw $e;
            }
        }
    }

    /**
     * Тест метода send() с пустыми данными
     * @throws Exception
     */
    public function testSendWithEmptyData(): void
    {
        $data = [];
        $response = new JsonResponse($data);

        $this->assertJsonSendOutput($response, $data);
    }

    /**
     * Тест статического метода success()
     * @throws ReflectionException
     */
    public function testSuccessMethod(): void
    {
        $data = ['user_id' => 123, 'username' => 'testuser'];
        $response = JsonResponse::success($data);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);
        $this->assertEquals($data, $actualData);

        // Проверяем статус код
        $statusCodeProperty = $reflection->getProperty('statusCode');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $statusCodeProperty->setAccessible(true);
        $statusCode = $statusCodeProperty->getValue($response);
        $this->assertEquals(200, $statusCode);
    }

    /**
     * Тест статического метода error() с параметрами по умолчанию
     * @throws ReflectionException
     */
    public function testErrorMethodWithDefaults(): void
    {
        $message = 'Something went wrong';
        $response = JsonResponse::error($message);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);

        $expectedData = [
            'error' => [
                'message' => $message,
                'code' => 'ERROR'
            ]
        ];

        $this->assertEquals($expectedData, $actualData);

        // Проверяем статус код по умолчанию
        $statusCodeProperty = $reflection->getProperty('statusCode');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $statusCodeProperty->setAccessible(true);
        $statusCode = $statusCodeProperty->getValue($response);
        $this->assertEquals(400, $statusCode);
    }

    /**
     * Тест статического метода error() с пользовательскими параметрами
     * @throws ReflectionException
     */
    public function testErrorMethodWithCustomParameters(): void
    {
        $message = 'Resource not found';
        $code = 'NOT_FOUND';
        $statusCode = 404;

        $response = JsonResponse::error($message, $code, $statusCode);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);

        $expectedData = [
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ];

        $this->assertEquals($expectedData, $actualData);

        // Проверяем статус код
        $statusCodeProperty = $reflection->getProperty('statusCode');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $statusCodeProperty->setAccessible(true);
        $actualStatusCode = $statusCodeProperty->getValue($response);
        $this->assertEquals($statusCode, $actualStatusCode);
    }

    /**
     * Тест статического метода status() без дополнительных деталей
     * @throws ReflectionException
     */
    public function testStatusMethodWithoutDetails(): void
    {
        $status = 'healthy';
        $response = JsonResponse::status($status);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);

        $this->assertArrayHasKey('status', $actualData);
        $this->assertArrayHasKey('timestamp', $actualData);
        $this->assertEquals($status, $actualData['status']);

        // Проверяем, что timestamp является валидной датой ISO 8601
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $actualData['timestamp']);
    }

    /**
     * Тест статического метода status() с дополнительными деталями
     * @throws ReflectionException
     */
    public function testStatusMethodWithDetails(): void
    {
        $status = 'degraded';
        $details = [
            'redis_status' => 'connected',
            'database_status' => 'slow',
            'response_time' => 1500
        ];

        $response = JsonResponse::status($status, $details);

        $reflection = new ReflectionClass($response);

        // Проверяем данные
        $dataProperty = $reflection->getProperty('data');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $dataProperty->setAccessible(true);
        $actualData = $dataProperty->getValue($response);

        $this->assertArrayHasKey('status', $actualData);
        $this->assertArrayHasKey('timestamp', $actualData);
        $this->assertEquals($status, $actualData['status']);

        // Проверяем дополнительные детали
        foreach ($details as $key => $value) {
            $this->assertArrayHasKey($key, $actualData);
            $this->assertEquals($value, $actualData[$key]);
        }
    }

    /**
     * Тест отправки ответа success()
     * @throws Exception
     */
    public function testSendSuccessResponse(): void
    {
        $data = ['id' => 1, 'name' => 'Test Item'];
        $response = JsonResponse::success($data);

        $this->assertJsonSendOutput($response, $data);
    }

    /**
     * Тест отправки ответа error()
     * @throws Exception
     */
    public function testSendErrorResponse(): void
    {
        $message = 'Validation failed';
        $code = 'VALIDATION_ERROR';
        $response = JsonResponse::error($message, $code, 422);

        $expectedData = [
            'error' => [
                'message' => $message,
                'code' => $code
            ]
        ];

        $this->assertJsonSendOutput($response, $expectedData);
    }

    /**
     * Тест отправки ответа status()
     * @throws Exception
     */
    public function testSendStatusResponse(): void
    {
        $status = 'operational';
        $details = ['uptime' => '99.9%'];
        $response = JsonResponse::status($status, $details);

        // Для status() нужна специальная проверка из-за timestamp
        ob_start();

        try {
            $response->send();
            $output = ob_get_clean();

            // Проверяем, что вывод является валидным JSON
            $this->assertJson($output);

            $decodedOutput = json_decode($output, true);
            $this->assertEquals($status, $decodedOutput['status']);
            $this->assertEquals('99.9%', $decodedOutput['uptime']);
            $this->assertArrayHasKey('timestamp', $decodedOutput);
        } catch (Exception $e) {
            ob_end_clean();
            // Если исключение связано с header(), то проверяем через геттеры
            if (str_contains($e->getMessage(), 'headers already sent')) {
                $data = $response->getData();
                $this->assertEquals($status, $data['status']);
                $this->assertEquals('99.9%', $data['uptime']);
                $this->assertArrayHasKey('timestamp', $data);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Тест с русскими символами в данных
     * @throws Exception
     */
    public function testSendWithRussianCharacters(): void
    {
        $data = [
            'сообщение' => 'Привет, мир!',
            'статус' => 'успех',
            'данные' => ['имя' => 'Тест', 'возраст' => 25]
        ];

        $response = new JsonResponse($data);

        $this->assertJsonSendOutput($response, $data);
    }

    /**
     * Тест с вложенными массивами и объектами
     * @throws Exception
     */
    public function testSendWithNestedData(): void
    {
        $data = [
            'user' => [
                'id' => 123,
                'profile' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'preferences' => [
                        'theme' => 'dark',
                        'notifications' => true
                    ]
                ]
            ],
            'metadata' => [
                'version' => '1.0',
                'timestamp' => time()
            ]
        ];

        $response = new JsonResponse($data);

        $this->assertJsonSendOutput($response, $data);
    }
}