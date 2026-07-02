<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Tests\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testManagerCanLoginAndReceiveJwtToken(): void
    {
        $this->createManager();

        $token = $this->loginAndGetToken('manager@taskflow.fr', 'TaskFlowManager123');

        self::assertNotEmpty($token);
    }

    public function testLoginWithWrongPasswordShouldReturn401(): void
    {
        $this->createManager();

        $this->client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(
                [
                    'email' => 'manager@taskflow.fr',
                    'password' => 'WrongPassword',
                ],
                JSON_THROW_ON_ERROR
            ),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $this->createInactiveUser('inactive@taskflow.fr');

        $this->client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(
                [
                    'email' => 'inactive@taskflow.fr',
                    'password' => 'InactiveManager123',
                ],
                JSON_THROW_ON_ERROR
            ),
        );

        self::assertResponseStatusCodeSame(401);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayNotHasKey('token', $data);
    }
}
