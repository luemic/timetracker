<?php

namespace App\Controller;

use App\Service\ProjectActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * REST endpoints for managing project-activity relations.
 */
#[Route('/api/project-activities', name: 'api_project_activities_')]
#[IsGranted('ROLE_USER')]
class ProjectActivityController extends AbstractController
{
    /** List all project-activity links. */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ProjectActivityService $svc): JsonResponse
    {
        return $this->json($svc->list());
    }

    /** Get a single project-activity link by ID. */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getOne(int $id, ProjectActivityService $svc): JsonResponse
    {
        $item = $svc->get($id);
        if (!$item) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json($item);
    }

    /** Create a new project-activity link (upsert if exists). */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ProjectActivityService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->create($data);
            return $this->json($item, 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 404);
        }
    }

    /** Update an existing project-activity link. */
    #[Route('/{id}', name: 'update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $request, ProjectActivityService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->update($id, $data);
            if (!$item) { return $this->json(['error' => 'Not found'], 404); }
            return $this->json($item);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 404);
        }
    }

    /** Delete a project-activity link by ID. */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, ProjectActivityService $svc): JsonResponse
    {
        $ok = $svc->delete($id);
        if (!$ok) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json(null, 204);
    }
}
