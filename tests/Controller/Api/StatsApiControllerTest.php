<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-level checks of the daily-stats API's security boundary and request
 * validation. These paths short-circuit before any DB access (auth rejection
 * and param validation), so they exercise the firewall + controller wiring
 * without needing seeded test databases. APP_API_TOKEN=test-api-token comes
 * from phpunit.xml.dist.
 */
class StatsApiControllerTest extends WebTestCase
{
    public function testRejectsRequestWithoutToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/daily');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
        $this->assertSame(
            ['error' => 'Unauthorized'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testRejectsWrongToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/stats/daily', server: ['HTTP_AUTHORIZATION' => 'Bearer wrong']);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testValidTokenButInvalidSourceReturns400(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/stats/daily?source=bogus',
            server: ['HTTP_AUTHORIZATION' => 'Bearer test-api-token'],
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode());
        $body = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $body);
    }
}
