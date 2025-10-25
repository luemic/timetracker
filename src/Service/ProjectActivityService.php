<?php

namespace App\Service;

use App\Entity\ProjectActivity;
use App\Repository\ActivityRepository;
use App\Repository\ProjectActivityRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectActivityService
{
    public function __construct(
        private readonly ProjectActivityRepository $projectActivities,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array<int, array{id:int,projectId:int,activityId:int,factor:float}> */
    public function list(): array
    {
        $items = $this->projectActivities->findBy([], ['id' => 'ASC']);
        return array_map(fn(ProjectActivity $pa) => $this->toArray($pa), $items);
    }

    public function get(int $id): ?array
    {
        $pa = $this->projectActivities->find($id);
        return $pa ? $this->toArray($pa) : null;
    }

    /** @param array{projectId?:int,activityId?:int,factor?:float} $data */
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
        $pa = (new ProjectActivity())
            ->setProject($project)
            ->setActivity($activity)
            ->setFactor($factor);
        $this->projectActivities->save($pa, true);
        return $this->toArray($pa);
    }

    /** @param array{projectId?:int,activityId?:int,factor?:float} $data */
    public function update(int $id, array $data): ?array
    {
        $pa = $this->projectActivities->find($id);
        if (!$pa) return null;
        if (array_key_exists('projectId', $data)) {
            $pid = $data['projectId'];
            if (!is_numeric($pid)) { throw new \InvalidArgumentException('projectId must be numeric'); }
            $project = $this->projects->find((int)$pid);
            if (!$project) { throw new \RuntimeException('Project not found'); }
            $pa->setProject($project);
        }
        if (array_key_exists('activityId', $data)) {
            $aid = $data['activityId'];
            if (!is_numeric($aid)) { throw new \InvalidArgumentException('activityId must be numeric'); }
            $activity = $this->activities->find((int)$aid);
            if (!$activity) { throw new \RuntimeException('Activity not found'); }
            $pa->setActivity($activity);
        }
        if (array_key_exists('factor', $data)) {
            $pa->setFactor((float)$data['factor']);
        }
        $this->em->flush();
        return $this->toArray($pa);
    }

    public function delete(int $id): bool
    {
        $pa = $this->projectActivities->find($id);
        if (!$pa) return false;
        $this->projectActivities->remove($pa, true);
        return true;
    }

    private function toArray(ProjectActivity $pa): array
    {
        return [
            'id' => $pa->getId() ?? 0,
            'projectId' => $pa->getProject()?->getId() ?? 0,
            'activityId' => $pa->getActivity()?->getId() ?? 0,
            'factor' => $pa->getFactor(),
        ];
    }
}
