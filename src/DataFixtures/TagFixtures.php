<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TagFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var array<string, list<string>> $tagsByProjectRef */
        $tagsByProjectRef = [
            'project_1' => ['urgent', 'frontend', 'backend'],
            'project_2' => ['mobile', 'ios'],
            'project_3' => ['cloturé'],
            'project_4' => ['en cours', 'fermé'],
            'project_5' => ['reporting', 'kpi'],
            'project_6' => ['cloturé'],
            'project_7' => ['sécurité', 'audit'],
            'project_8' => ['en cours', 'fermé'],
            'project_9' => ['devops', 'ci'],
            'project_10' => ['cloturé'],
        ];

        foreach ($tagsByProjectRef as $projectRef => $labels) {
            $project = $this->getReference($projectRef, Project::class);

            foreach ($labels as $label) {
                $tag = new Tag();
                $tag->setLabel($label)
                    ->setProject($project);

                $manager->persist($tag);
                $this->addReference($this->buildTagReference($projectRef, $label), $tag);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [ProjectFixtures::class];
    }

    public static function buildTagReference(string $projectRef, string $label): string
    {
        return sprintf('tag_%s_%s', $projectRef, str_replace(' ', '_', $label));
    }
}
