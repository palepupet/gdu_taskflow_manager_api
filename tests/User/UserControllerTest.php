<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Enum\UserRole;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class UserControllerTest extends ApiTestCase
{
    public function testGetMeWithValidToken(): void
    {
        $token = $this->loginAsManager();

        $this->getMe($token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(self::EMAIL_MANAGER, $data['email']);
    }

    public function testGetMeWithInvalidTokenShouldReturn401(): void
    {
        $this->getMe();
        self::assertResponseStatusCodeSame(401);
    }

    public function testManagerCanCreateUserWithDefaultUserRole(): void
    {
        $token = $this->loginAsManager();

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
        $token = $this->loginAsManager();

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
        $token = $this->loginAsUser();

        $this->postUser($this->getValidCreateUserPayload(), $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanListAllUsers(): void
    {
        $this->createUser();
        $token = $this->loginAsManager();

        $this->getUsers($token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(2, $data);

        $emails = array_column($data, 'email');
        self::assertContains(self::EMAIL_MANAGER, $emails);
        self::assertContains(self::EMAIL_USER, $emails);

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
        $token = $this->loginAsUser();

        $this->getUsers($token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanGetUserById(): void
    {
        $user = $this->createUser();
        $token = $this->loginAsManager();

        $userId = $user->getId();
        self::assertNotNull($userId);

        $this->getUserById($userId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame($userId, $data['id']);
        self::assertSame(self::EMAIL_USER, $data['email']);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testUserCannotGetUserById(): void
    {
        $manager = $this->createManager();
        $this->createUser();
        $token = $this->loginAsUser();

        $managerId = $manager->getId();
        self::assertNotNull($managerId);

        $this->getUserById($managerId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanDeactivateUser(): void
    {
        $user = $this->createUser();
        $token = $this->loginAsManager();

        $userId = $user->getId();
        self::assertNotNull($userId);

        $this->updateUserById($userId, ['isActive' => false], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame($userId, $data['id']);
        self::assertFalse($data['isActive']);
    }

    public function testUserCannotUpdateAnotherUser(): void
    {
        $userToDeactivate = $this->createUser(self::EMAIL_OTHER);
        $this->createUser();
        $token = $this->loginAsUser();

        $userToDeactivateId = $userToDeactivate->getId();
        self::assertNotNull($userToDeactivateId);

        $this->updateUserById($userToDeactivateId, ['firstName' => 'Hack'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanDeleteUser(): void
    {
        $user = $this->createUser();
        $token = $this->loginAsManager();

        $userId = $user->getId();
        self::assertNotNull($userId);

        $this->deleteUserById($userId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->getUserById($userId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUserCannotDeleteUser(): void
    {
        $userToDeactivate = $this->createUser(self::EMAIL_OTHER);
        $this->createUser();
        $token = $this->loginAsUser();

        $targetId = $userToDeactivate->getId();
        self::assertNotNull($targetId);

        $this->deleteUserById($targetId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
