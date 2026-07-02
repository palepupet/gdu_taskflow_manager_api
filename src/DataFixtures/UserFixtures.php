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
        $user = new User();
        $user->setEmail('manager@taskflow.fr')
            ->setFirstName('Manager')
            ->setLastName('Taskflow')
            ->setRoles([UserRole::Manager->value])
            ->setPassword($this->passwordHasher->hashPassword($user, 'TaskFlowManager123'));

        $manager->persist($user);
        $manager->flush();
    }
}
