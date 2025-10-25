<?php

namespace App\Service;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivityService
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array<int, array{id:int,name:string}> */
    public function list(): array
    {
        $items = $this->activities->findBy([], ['id' => 'ASC']);
        return array_map(fn(Activity $a) => ['id' => $a->getId() ?? 0, 'name' => $a->getName()], $items);
    }

    public function get(int $id): ?array
    {
        $a = $this->activities->find($id);
        if (!$a) return null;
        return ['id' => $a->getId() ?? 0, 'name' => $a->getName()];
    }

    /** @param array{name?:string} $data */
    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Field "name" is required');
        }
        $a = (new Activity())->setName($name);
        $this->activities->save($a, true);
        return ['id' => $a->getId() ?? 0, 'name' => $a->getName()];
    }

    /** @param array{name?:string} $data */
    public function update(int $id, array $data): ?array
    {
        $a = $this->activities->find($id);
        if (!$a) return null;
        if (array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Field "name" must not be empty');
            }
            $a->setName($name);
        }
        $this->em->flush();
        return ['id' => $a->getId() ?? 0, 'name' => $a->getName()];
    }

    public function delete(int $id): bool
    {
        $a = $this->activities->find($id);
        if (!$a) return false;
        $this->activities->remove($a, true);
        return true;
    }
}
