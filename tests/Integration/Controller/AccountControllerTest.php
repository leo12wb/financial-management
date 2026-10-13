<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for the Account API endpoints.
 *
 * Tests tagged with @group integration require a live database and valid JWT
 * configuration. They can be skipped in environments without these resources:
 *
 *   vendor/bin/phpunit --testsuite Unit
 *
 * To run only integration tests:
 *
 *   vendor/bin/phpunit --group integration
 *
 * @group integration
 */
class AccountControllerTest extends WebTestCase
{
    public function testListAccountsRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/accounts');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testCreateAccountRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/accounts',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([]),
        );

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testShowAccountRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/accounts/1');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * Verifies that posting an empty JSON body to a protected endpoint
     * returns 422 Unprocessable Entity with a violations array.
     *
     * This test requires a valid JWT token. It will be skipped automatically
     * if no test database / JWT secrets are configured.
     *
     * @group integration
     */
    public function testCreateAccountValidation(): void
    {
        if (!$this->isIntegrationEnvironmentAvailable()) {
            $this->markTestSkipped('Integration environment (DB + JWT) not available.');
        }

        $client = static::createClient();
        $token  = $this->obtainJwtToken($client);

        $client->request(
            method:  'POST',
            uri:     '/api/accounts',
            server:  [
                'CONTENT_TYPE'  => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([]),
        );

        $this->assertSame(422, $client->getResponse()->getStatusCode());

        $body = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('violations', $body);
        $this->assertNotEmpty($body['violations']);
    }

    /**
     * @group integration
     */
    public function testCreateAccountSuccess(): void
    {
        if (!$this->isIntegrationEnvironmentAvailable()) {
            $this->markTestSkipped('Integration environment (DB + JWT) not available.');
        }

        $client = static::createClient();
        $token  = $this->obtainJwtToken($client);

        $payload = [
            'name'            => 'Conta Corrente Teste',
            'institutionName' => 'Banco Teste',
            'accountType'     => 'checking',
            'currency'        => 'BRL',
            'initialBalance'  => '1000.00',
        ];

        $client->request(
            method:  'POST',
            uri:     '/api/accounts',
            server:  [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode($payload),
        );

        $this->assertSame(201, $client->getResponse()->getStatusCode());

        $body = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertSame('Conta Corrente Teste', $body['data']['name']);
        $this->assertSame('Banco Teste', $body['data']['institutionName']);
        $this->assertSame('checking', $body['data']['accountType']);
        $this->assertSame('BRL', $body['data']['currency']);
        $this->assertSame('1000.00', $body['data']['initialBalance']);
    }

    /**
     * @group integration
     */
    public function testListAccountsReturnsDataArrayWhenAuthenticated(): void
    {
        if (!$this->isIntegrationEnvironmentAvailable()) {
            $this->markTestSkipped('Integration environment (DB + JWT) not available.');
        }

        $client = static::createClient();
        $token  = $this->obtainJwtToken($client);

        $client->request(
            method: 'GET',
            uri:    '/api/accounts',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $body = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Checks whether the integration environment is available by inspecting
     * the DATABASE_URL and JWT_SECRET_KEY environment variables.
     */
    private function isIntegrationEnvironmentAvailable(): bool
    {
        $dbUrl     = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '';
        $jwtSecret = $_ENV['JWT_SECRET_KEY'] ?? $_SERVER['JWT_SECRET_KEY'] ?? '';

        return $dbUrl !== '' && $jwtSecret !== '';
    }

    /**
     * Obtains a JWT token by logging in with the test user credentials defined
     * in the TEST_USER_EMAIL / TEST_USER_PASSWORD environment variables.
     *
     * Falls back to 'test@example.com' / 'password' when not set.
     */
    private function obtainJwtToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $email    = $_ENV['TEST_USER_EMAIL']    ?? 'test@example.com';
        $password = $_ENV['TEST_USER_PASSWORD'] ?? 'password';

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password]),
        );

        $response = json_decode((string) $client->getResponse()->getContent(), true);

        return (string) ($response['token'] ?? '');
    }
}
