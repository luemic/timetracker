<?php

namespace App\Service;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing activities.
 */
class ActivityService
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Return all activities sorted by ID ascending.
     *
     * @return array<int, array{id:int,name:string}>
     */
    public function list(): array
    {
        $items = $this->activities->findBy([], ['id' => 'ASC']);
        return array_map(
            fn(Activity $activity) => ['id' => $activity->getId() ?? 0, 'name' => $activity->getName()],
            $items
        );
    }

    /**
     * Get an activity by ID, mapped for JSON.
     */
    public function get(int $id): ?array
    {
        $activity = $this->activities->find($id);
        if (!$activity) {
            return null;
        }

        return ['id' => $activity->getId() ?? 0, 'name' => $activity->getName()];
    }

    /**
     * Create an activity from request data.
     *
     * @param array{name?:string} $data
     * @return array{id:int,name:string}
     */
    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Field "name" is required');
        }
        $activity = (new Activity())->setName($name);
        $this->activities->save($activity, true);

        return ['id' => $activity->getId() ?? 0, 'name' => $activity->getName()];
    }

    /**
     * Update an activity with partial data.
     *
     * @param array{name?:string} $data
     * @return array{id:int,name:string}|null
     */
    public function update(int $id, array $data): ?array
    {
        $activity = $this->activities->find($id);
        if (!$activity) {
            return null;
        }
        if (array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Field "name" must not be empty');
            }
            $activity->setName($name);
        }
        $this->em->flush();

        return ['id' => $activity->getId() ?? 0, 'name' => $activity->getName()];
    }

    /**
     * Delete an activity.
     */
    public function delete(int $id): bool
    {
        $activity = $this->activities->find($id);
        if (!$activity) {
            return false;
        }
        $this->activities->remove($activity, true);

        return true;
    }
}
