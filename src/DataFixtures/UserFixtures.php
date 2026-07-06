<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $managers = [
            ['email' => 'manager@taskflow.fr', 'firstName' => 'Manager', 'lastName' => 'Taskflow', 'roles' => [UserRole::User->value, UserRole::Manager->value], 'password' => 'TaskFlowManager123', 'isActive' => true],
            ['email' => 'sophie.martin@taskflow.fr', 'firstName' => 'Sophie', 'lastName' => 'Martin', 'roles' => [UserRole::User->value, UserRole::Manager->value], 'password' => 'TaskFlowManager123', 'isActive' => true],
        ];

        foreach ($managers as $data) {
            $manager->persist($this->createUser(
                email: $data['email'],
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                roles: $data['roles'],
                password: $data['password'],
                isActive: $data['isActive'],
            ));
        }

        $users = [
            ['email' => 'user@taskflow.fr', 'firstName' => 'User', 'lastName' => 'Taskflow', 'roles' => [UserRole::User->value], 'password' => 'TaskFlowUser123', 'isActive' => true],
            ['email' => 'alice.dupont@taskflow.fr', 'firstName' => 'Alice', 'lastName' => 'Dupont', 'roles' => [UserRole::User->value], 'password' => 'TaskFlowUser123', 'isActive' => true],
            ['email' => 'bob.leroy@taskflow.fr', 'firstName' => 'Bob', 'lastName' => 'Leroy', 'roles' => [UserRole::User->value], 'password' => 'TaskFlowUser123', 'isActive' => true],
            ['email' => 'claire.bernard@taskflow.fr', 'firstName' => 'Claire', 'lastName' => 'Bernard', 'roles' => [UserRole::User->value], 'password' => 'TaskFlowUser123', 'isActive' => true],
            ['email' => 'david.petit@taskflow.fr', 'firstName' => 'David', 'lastName' => 'Petit', 'roles' => [UserRole::User->value], 'password' => 'TaskFlowUser123', 'isActive' => true],
            ['email' => 'inactive.user@taskflow.fr', 'firstName' => 'Inactive', 'lastName' => 'User', 'roles' => [UserRole::User->value], 'password' => 'TaskFlowUser123', 'isActive' => false],
        ];

        foreach ($users as $data) {
            $manager->persist($this->createUser(
                email: $data['email'],
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                roles: $data['roles'],
                password: $data['password'],
                isActive: $data['isActive'],
            ));
        }

        $manager->flush();
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(
        string $email,
        string $firstName,
        string $lastName,
        array $roles,
        string $password,
        bool $isActive,
    ): User {
        $user = new User();
        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($roles)
            ->setIsActive($isActive)
            ->setPassword($this->passwordHasher->hashPassword($user, $password));

        return $user;
    }
}
