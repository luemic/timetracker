<?php

namespace App\Service;

use App\Entity\ProjectActivity;
use App\Repository\ActivityRepository;
use App\Repository\ProjectActivityRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing the relation between projects and activities.
 */
class ProjectActivityService
{
    public function __construct(
        private readonly ProjectActivityRepository $projectActivities,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Return all project-activity links sorted by ID ascending.
     *
     * @return array<int, array{id:int,projectId:int,activityId:int,factor:float}>
     */
    public function list(): array
    {
        $items = $this->projectActivities->findBy([], ['id' => 'ASC']);
        return array_map(fn(ProjectActivity $projectActivity) => $this->toArray($projectActivity), $items);
    }

    /** Get a project-activity link by ID. */
    public function get(int $id): ?array
    {
        $projectActivity = $this->projectActivities->find($id);
        return $projectActivity ? $this->toArray($projectActivity) : null;
    }

    /**
     * Create a project-activity link (upserts when pair exists).
     *
     * @param array{projectId?:int,activityId?:int,factor?:float} $data
     * @return array{id:int,projectId:int,activityId:int,factor:float}
     */
    public function create(array $data): array
    {
        $projectId = $data['projectId'] ?? null;
        $activityId = $data['activityId'] ?? null;
        $factor = isset($data['factor']) ? (float)$data['factor'] : 1.0;
        if (!is_numeric($projectId) || !is_numeric($activityId)) {
            throw new \InvalidArgumentException('Fields "projectId" and "activityId" are required');
        }
        $project = $this->projects->find((int)$projectId);
        if (!$project) { throw new \RuntimeException('Project not found'); }
        $activity = $this->activities->find((int)$activityId);
        if (!$activity) { throw new \RuntimeException('Activity not found'); }
        // upsert-like check for uniqueness
        $existing = $this->projectActivities->findOneBy(['project' => $project, 'activity' => $activity]);
        if ($existing) {
            $existing->setFactor($factor);
            $this->em->flush();
            return $this->toArray($existing);
        }
        $projectActivity = (new ProjectActivity())
            ->setProject($project)
            ->setActivity($activity)
            ->setFactor($factor);
        $this->projectActivities->save($projectActivity, true);
        return $this->toArray($projectActivity);
    }

    /**
     * Update a project-activity link with partial data.
     *
     * @param array{projectId?:int,activityId?:int,factor?:float} $data
     * @return array{id:int,projectId:int,activityId:int,factor:float}|null
     */
    public function update(int $id, array $data): ?array
    {
        $projectActivity = $this->projectActivities->find($id);
        if (!$projectActivity) return null;
        if (array_key_exists('projectId', $data)) {
            $projectId = $data['projectId'];
            if (!is_numeric($projectId)) { throw new \InvalidArgumentException('projectId must be numeric'); }
            $project = $this->projects->find((int)$projectId);
            if (!$project) { throw new \RuntimeException('Project not found'); }
            $projectActivity->setProject($project);
        }
        if (array_key_exists('activityId', $data)) {
            $activityId = $data['activityId'];
            if (!is_numeric($activityId)) { throw new \InvalidArgumentException('activityId must be numeric'); }
            $activity = $this->activities->find((int)$activityId);
            if (!$activity) { throw new \RuntimeException('Activity not found'); }
            $projectActivity->setActivity($activity);
        }
        if (array_key_exists('factor', $data)) {
            $projectActivity->setFactor((float)$data['factor']);
        }
        $this->em->flush();
        return $this->toArray($projectActivity);
    }

    /** Delete a project-activity link. */
    public function delete(int $id): bool
    {
        $projectActivity = $this->projectActivities->find($id);
        if (!$projectActivity) return false;
        $this->projectActivities->remove($projectActivity, true);
        return true;
    }

    /**
     * Map a ProjectActivity entity to array for JSON output.
     *
     * @return array{id:int,projectId:int,activityId:int,factor:float}
     */
    private function toArray(ProjectActivity $projectActivity): array
    {
        return [
            'id' => $projectActivity->getId() ?? 0,
            'projectId' => $projectActivity->getProject()?->getId() ?? 0,
            'activityId' => $projectActivity->getActivity()?->getId() ?? 0,
            'factor' => $projectActivity->getFactor(),
        ];
    }
}
