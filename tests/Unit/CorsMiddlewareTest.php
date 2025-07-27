<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use App\Http\Middleware\CorsMiddleware;
use ReflectionClass;
use ReflectionException;

/**
 * Тесты для класса CorsMiddleware
 *
 * Проверяет корректность обработки CORS заголовков и preflight запросов
 */
class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $corsMiddleware;
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();

        // Сохраняем оригинальные значения $_SERVER
        $this->originalServer = $_SERVER;

        $this->corsMiddleware = new CorsMiddleware();
    }

    protected function tearDown(): void
    {
        // Восстанавливаем оригинальные значения $_SERVER
        $_SERVER = $this->originalServer;

        parent::tearDown();
    }

    /**
     * Получает значение приватного свойства объекта
     *
     * @param object $object
     * @param string $propertyName
     * @return mixed
     * @throws ReflectionException
     */
    private function getPrivatePropertyValue(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        /** @noinspection PhpExpressionResultUnusedInspection */
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Тест метода handle() - установка CORS заголовков
     *
     * Поскольку мы не можем легко протестировать функцию header() в PHPUnit,
     * мы проверяем, что метод выполняется без исключений
     * @throws Exception
     */
    public function testHandle(): void
    {
        // Тест должен завершиться без исключений
        $this->expectNotToPerformAssertions();

        try {
            $this->corsMiddleware->handle();
        } catch (Exception $e) {
            // Игнорируем исключения, связанные с header() в тестовой среде
            if (!str_contains($e->getMessage(), 'headers already sent')) {
                throw $e;
            }
        }
    }

    /**
     * Тест метода handlePreflight() - создание Response для preflight запроса
     * @throws ReflectionException
     */
    public function testHandlePreflight(): void
    {
        $response = $this->corsMiddleware->handlePreflight();

        $this->assertEquals('', $this->getPrivatePropertyValue($response, 'content'));
        $this->assertEquals(200, $this->getPrivatePropertyValue($response, 'statusCode'));

        $expectedHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400'
        ];

        $this->assertEquals($expectedHeaders, $this->getPrivatePropertyValue($response, 'headers'));
    }

    /**
     * Тест метода isPreflight() с OPTIONS запросом и заголовком Access-Control-Request-Method
     */
    public function testIsPreflightWithValidPreflightRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';

        $result = $this->corsMiddleware->isPreflight();

        $this->assertTrue($result);
    }

    /**
     * Тест метода isPreflight() с OPTIONS запросом без заголовка Access-Control-Request-Method
     */
    public function testIsPreflightWithOptionsButNoRequestMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);

        $result = $this->corsMiddleware->isPreflight();

        $this->assertFalse($result);
    }

    /**
     * Тест метода isPreflight() с не-OPTIONS запросом
     */
    public function testIsPreflightWithNonOptionsRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';

        $result = $this->corsMiddleware->isPreflight();

        $this->assertFalse($result);
    }

    /**
     * Тест метода isPreflight() с GET запросом
     */
    public function testIsPreflightWithGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);

        $result = $this->corsMiddleware->isPreflight();

        $this->assertFalse($result);
    }

    /**
     * Тест метода isPreflight() с PUT запросом и заголовком Access-Control-Request-Method
     */
    public function testIsPreflightWithPutRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';

        $result = $this->corsMiddleware->isPreflight();

        $this->assertFalse($result);
    }

    /**
     * Тест полного цикла: проверка preflight и обработка
     */
    public function testFullPreflightCycle(): void
    {
        // Настраиваем preflight запрос
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';

        // Проверяем, что это preflight запрос
        $this->assertTrue($this->corsMiddleware->isPreflight());

        // Получаем ответ для preflight, чтобы убедиться, что он не вызывает ошибок.
        // Детальная проверка ответа находится в testHandlePreflight
        $response = $this->corsMiddleware->handlePreflight();
        $this->assertNotNull($response);
    }

    /**
     * Тест различных HTTP методов в заголовке Access-Control-Request-Method
     */
    public function testIsPreflightWithDifferentRequestMethods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

        $requestMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($requestMethods as $method) {
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = $method;
            $this->assertTrue($this->corsMiddleware->isPreflight(), "Failed for method: $method");
        }
    }
}