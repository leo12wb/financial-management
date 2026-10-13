<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration tests for the authentication (JWT login) endpoint.
 *
 * The login route is handled by LexikJWTAuthenticationBundle via the Symfony
 * security firewall — there is no explicit controller action. These tests
 * verify that the endpoint is reachable and behaves correctly.
 *
 * Run only these tests:
 *   vendor/bin/phpunit --group integration tests/Integration/Controller/AuthControllerTest.php
 *
 * @group integration
 */
class AuthControllerTest extends WebTestCase
{
    public function testLoginEndpointExists(): void
    {
        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'nonexistent@example.com', 'password' => 'wrongpassword']),
        );

        $statusCode = $client->getResponse()->getStatusCode();

        // The endpoint must exist: a missing route would return 404 or 405.
        // An authentication failure returns 401, which confirms the endpoint is active.
        $this->assertNotSame(
            404,
            $statusCode,
            'The /api/login endpoint must be registered and reachable.',
        );
        $this->assertNotSame(
            405,
            $statusCode,
            'POST must be an accepted method on /api/login.',
        );
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email'    => 'invalid@example.com',
                'password' => 'wrongpassword',
            ]),
        );

        $this->assertSame(
            401,
            $client->getResponse()->getStatusCode(),
            'Posting invalid credentials to /api/login must return HTTP 401.',
        );
    }

    public function testLoginResponseContentTypeIsJson(): void
    {
        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email'    => 'nobody@example.com',
                'password' => 'badpassword',
            ]),
        );

        $contentType = $client->getResponse()->headers->get('Content-Type') ?? '';

        $this->assertStringContainsString(
            'application/json',
            $contentType,
            'The /api/login endpoint must return a JSON response.',
        );
    }

    public function testLoginWithEmptyBodyReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        );

        $statusCode = $client->getResponse()->getStatusCode();

        // Empty body has no credentials — must be rejected
        $this->assertSame(401, $statusCode);
    }

    public function testLoginWithMissingPasswordReturns401(): void
    {
        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com']),
        );

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * Verifies that a successful login returns a JSON body containing a "token" key.
     *
     * Requires a real user in the test database (credentials via env vars).
     *
     * @group integration
     */
    public function testSuccessfulLoginReturnsToken(): void
    {
        $email    = $_ENV['TEST_USER_EMAIL']    ?? null;
        $password = $_ENV['TEST_USER_PASSWORD'] ?? null;

        if ($email === null || $password === null) {
            $this->markTestSkipped(
                'Set TEST_USER_EMAIL and TEST_USER_PASSWORD env vars to run this test.',
            );
        }

        $client = static::createClient();

        $client->request(
            method:  'POST',
            uri:     '/api/login',
            server:  ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password]),
        );

        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $body = json_decode((string) $client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $body, 'A successful login response must include a "token" field.');
        $this->assertNotEmpty($body['token'], 'The returned JWT token must not be empty.');
    }
}
