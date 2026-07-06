<?php

declare(strict_types=1);

namespace App\Controller\Project;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\Project\CreateProjectRequest;
use App\Dto\Project\ProjectResponse;
use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Http\RequestPayloadParser;
use App\Repository\ProjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

    #[Route('/projects', name:'project_list', methods: ['GET'])]
    public function list(ProjectRepositoryInterface $projectRepository): JsonResponse
    {
        $user = $this->getCurrentUser();

        $projects = $projectRepository->findAccessibleByUser($user);

        $data = array_map(
            fn (Project $project): array => ProjectResponse::fromProject($project)->toArray(),
            $projects,
        );

        return new JsonResponse($data);
    }

    #[Route('/project', name:'project_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $owner = $this->getCurrentUser();

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(CreateProjectRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var CreateProjectRequest $dto */
        $project = new Project();
        $project
            ->setTitle((string) $dto->title)
            ->setDescription($dto->description)
            ->setStatus(ProjectStatus::IN_PROGRESS)
            ->setOwner($owner)
            ->setStartAt($dto->getStartAtAsDateTime())
            ->setEndAt($dto->getEndAtAsDateTime());

        $error = $this->requestPayloadParser->validateEntity($project);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->persist($project);
        $entityManager->flush();

        return new JsonResponse(
            ProjectResponse::fromProject($project)->toArray(),
            Response::HTTP_CREATED
        );
    }
}
