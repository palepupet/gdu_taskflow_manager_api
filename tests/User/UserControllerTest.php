<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Enum\UserRole;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

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

    public function testManagerCanCreateUserWithDefaultUserRole(): void
    {
        $this->createManager();
        $token = $this->loginAndGetToken('manager@taskflow.fr', 'TaskFlowManager123');

        $this->postUser($this->getValidCreateUserPayload(), $token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->getJsonResponse();
        self::assertSame('john.doe@taskflow.fr', $data['email']);
        self::assertSame('John', $data['firstName']);
        self::assertSame('Doe', $data['lastName']);
        self::assertSame([UserRole::User->value], $data['roles']);
        self::assertTrue($data['isActive']);
        self::assertArrayHasKey('createdAt', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testManagerCanCreateUserWithManagerRole(): void
    {
        $this->createManager();
        $token = $this->loginAndGetToken('manager@taskflow.fr', 'TaskFlowManager123');

        $payload = $this->getValidCreateUserPayload();
        $payload['email'] = 'bob.smith@taskflow.fr';
        $payload['firstName'] = 'Bob';
        $payload['lastName'] = 'Smith';
        $payload['roles'] = [UserRole::Manager->value];

        $this->postUser($payload, $token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->getJsonResponse();

        self::assertSame([UserRole::Manager->value, UserRole::User->value], $data['roles']);
    }

    public function testUserCannotCreateUser(): void
    {
        $this->createUser();
        $token = $this->loginAndGetToken('user@taskflow.fr', 'TaskFlowUser123');

        $this->postUser($this->getValidCreateUserPayload(), $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanListAllUsers(): void
    {
        $this->createManager();
        $this->createUser();
        $token = $this->loginAndGetToken('manager@taskflow.fr', 'TaskFlowManager123');

        $this->getUsers($token);

        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(2, $data);

        $emails = array_column($data, 'email');
        self::assertContains('manager@taskflow.fr', $emails);
        self::assertContains('user@taskflow.fr', $emails);

        foreach ($data as $user) {
            self::assertIsArray($user);
            self::assertArrayHasKey('id', $user);
            self::assertArrayHasKey('email', $user);
            self::assertArrayNotHasKey('password', $user);
        }
    }

    public function testUserCannotListAllUsers(): void
    {
        $this->createUser();
        $token = $this->loginAndGetToken('user@taskflow.fr', 'TaskFlowUser123');

        $this->getUsers($token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
