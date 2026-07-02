<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Tests\ApiTestCase;

class UserControllerTest extends ApiTestCase
{
    public function testGetMeWithValidToken(): void
    {
        $this->createManager();
        $token = $this->loginAndGetToken('manager@taskflow.fr', 'TaskFlowManager123');

        $this->client->request(
            'GET',
            '/me',
            server: ['HTTP_Authorization' => 'Bearer '.$token]
        );

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{email: string} $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('manager@taskflow.fr', $data['email']);
    }

    public function testGetMeWithInvalidTokenShouldReturn401(): void
    {
        $this->client->request(
            'GET',
            '/me',
        );

        self::assertResponseStatusCodeSame(401);
    }
}
