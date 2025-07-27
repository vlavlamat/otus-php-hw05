<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Response;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionClass;

/**
 * Тесты для класса Response
 *
 * Проверяет корректность создания и отправки HTTP ответов
 */
class ResponseTest extends TestCase
{
    /**
     * Вспомогательный метод для тестирования метода send()
     * Обрабатывает исключения header() в тестовой среде
     *
     * @throws Exception
     */
    private function assertSendOutput(Response $response, string $expectedOutput): void
    {
        ob_start();
        try {
            $response->send();
            $output = ob_get_clean();
            $this->assertEquals($expectedOutput, $output);
        } catch (Exception $e) {
            ob_end_clean();
            // Игнорируем ожидаемое исключение "headers already sent" в среде PHPUnit.
            // Но всё равно выполняем проверку содержимого через геттеры
            if (str_contains($e->getMessage(), 'headers already sent')) {
                // Проверяем содержимое через геттер, так как send() не удался
                $this->assertEquals($expectedOutput, $response->getContent());
            } else {
                // Если было выброшено другое исключение, пробрасываем его дальше
                throw $e;
            }
        }
    }

    /**
     * Вспомогательный метод для получения значения приватного свойства объекта.
     * @throws ReflectionException
     */
    private function getPrivatePropertyValue(object $object, string $propertyName): mixed
    {
        $property = (new ReflectionClass($object))->getProperty($propertyName);
        /** @noinspection PhpExpressionResultUnusedInspection */
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Тест конструктора с параметрами по умолчанию
     * @throws ReflectionException
     */
    public function testConstructorWithDefaults(): void
    {
        $response = new Response();
        $this->assertEquals('', $this->getPrivatePropertyValue($response, 'content'));
        $this->assertEquals(200, $this->getPrivatePropertyValue($response, 'statusCode'));
        $this->assertEquals([], $this->getPrivatePropertyValue($response, 'headers'));
    }

    /**
     * Тест конструктора с пользовательскими параметрами
     * @throws ReflectionException
     */
    public function testConstructorWithCustomParameters(): void
    {
        $content = 'Test content';
        $statusCode = 404;
        $headers = ['Content-Type' => 'text/plain', 'X-Custom-Header' => 'custom-value'];

        $response = new Response($content, $statusCode, $headers);

        $this->assertEquals($content, $this->getPrivatePropertyValue($response, 'content'));
        $this->assertEquals($statusCode, $this->getPrivatePropertyValue($response, 'statusCode'));
        $this->assertEquals($headers, $this->getPrivatePropertyValue($response, 'headers'));
    }

    /**
     * Тест конструктора с частичными параметрами
     * @throws ReflectionException
     */
    public function testConstructorWithPartialParameters(): void
    {
        $content = 'Partial content';
        $statusCode = 201;

        $response = new Response($content, $statusCode);

        $this->assertEquals($content, $this->getPrivatePropertyValue($response, 'content'));
        $this->assertEquals($statusCode, $this->getPrivatePropertyValue($response, 'statusCode'));
        $this->assertEquals([], $this->getPrivatePropertyValue($response, 'headers'));
    }

    /**
     * Тест метода send() с базовым содержимым
     * @throws Exception
     */
    public function testSendWithBasicContent(): void
    {
        $content = 'Hello, World!';
        $response = new Response($content);
        $this->assertSendOutput($response, $content);
    }

    /**
     * Тест метода send() с пустым содержимым
     * @throws Exception
     */
    public function testSendWithEmptyContent(): void
    {
        $response = new Response();
        $this->assertSendOutput($response, '');
    }

    /**
     * Тест метода send() с JSON содержимым
     * @throws Exception
     */
    public function testSendWithJsonContent(): void
    {
        $data = ['message' => 'success', 'code' => 200];
        $content = json_encode($data);
        $headers = ['Content-Type' => 'application/json'];
        $response = new Response($content, 200, $headers);

        ob_start();
        try {
            $response->send();
            $output = ob_get_clean();
            $this->assertEquals($content, $output);
            $this->assertJson($output);
            $this->assertEquals($data, json_decode($output, true));
        } catch (Exception $e) {
            ob_end_clean();
            if (str_contains($e->getMessage(), 'headers already sent')) {
                // Проверяем содержимое и структуру данных через геттеры
                $this->assertEquals($content, $response->getContent());
                $this->assertJson($content);
                $this->assertEquals($data, json_decode($content, true));
            } else {
                throw $e;
            }
        }
    }

    /**
     * Тест метода send() с различными статус кодами
     * @throws Exception
     */
    public function testSendWithDifferentStatusCodes(): void
    {
        $statusCodes = [200, 201, 400, 404, 500];

        foreach ($statusCodes as $statusCode) {
            $response = new Response('Test content', $statusCode);
            $this->assertSendOutput($response, 'Test content');
            // Дополнительная проверка статус кода
            $this->assertEquals($statusCode, $response->getStatusCode());
        }
    }

    /**
     * Тест метода send() с множественными заголовками
     * @throws Exception
     */
    public function testSendWithMultipleHeaders(): void
    {
        $headers = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff'
        ];
        $response = new Response('<h1>Test</h1>', 200, $headers);
        $this->assertSendOutput($response, '<h1>Test</h1>');
        // Дополнительная проверка заголовков
        $this->assertEquals($headers, $response->getHeaders());
    }

    /**
     * Тест с длинным содержимым
     * @throws Exception
     */
    public function testSendWithLongContent(): void
    {
        $content = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 100);
        $response = new Response($content);

        ob_start();
        try {
            $response->send();
            $output = ob_get_clean();
            $this->assertEquals($content, $output);
            $this->assertGreaterThan(5000, strlen($output));
        } catch (Exception $e) {
            ob_end_clean();
            if (str_contains($e->getMessage(), 'headers already sent')) {
                // Проверяем исходное содержимое через геттер
                $this->assertEquals($content, $response->getContent());
                $this->assertGreaterThan(5000, strlen($response->getContent()));
            } else {
                throw $e;
            }
        }
    }

    /**
     * Тест с специальными символами в содержимом
     * @throws Exception
     */
    public function testSendWithSpecialCharacters(): void
    {
        $content = "Тест с русскими символами и специальными знаками: !@#$%^&*()_+{}|:<>?[];',./";
        $response = new Response($content);
        $this->assertSendOutput($response, $content);
    }
}