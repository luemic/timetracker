<?php

namespace App\Controller;

use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * REST endpoints for managing projects.
 */
#[Route('/api/projects', name: 'api_projects_')]
#[IsGranted('ROLE_USER')]
class ProjectController extends AbstractController
{
    /** List all projects. */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ProjectService $svc): JsonResponse
    {
        return $this->json($svc->list());
    }

    /** Get a single project by ID. */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getOne(int $id, ProjectService $svc): JsonResponse
    {
        $item = $svc->get($id);
        if (!$item) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json($item);
    }

    /** Create a new project. */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ProjectService $svc): JsonResponse
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

    /** Update an existing project. */
    #[Route('/{id}', name: 'update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $request, ProjectService $svc): JsonResponse
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

    /** Delete a project by ID. */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, ProjectService $svc): JsonResponse
    {
        $ok = $svc->delete($id);
        if (!$ok) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json(null, 204);
    }
}
