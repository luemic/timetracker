<?php

namespace App\Repository;

use App\Entity\ProjectActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectActivity>
 */
class ProjectActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectActivity::class);
    }

    /**
     * Persist a ProjectActivity entity.
     *
     * @param ProjectActivity $entity The link entity to persist
     * @param bool $flush Whether to flush immediately
     */
    public function save(ProjectActivity $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Remove a ProjectActivity entity.
     *
     * @param ProjectActivity $entity The link entity to remove
     * @param bool $flush Whether to flush immediately
     */
    public function remove(ProjectActivity $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }
}
