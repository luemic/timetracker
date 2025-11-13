<?php

namespace App\Repository;

use App\Entity\TicketSystem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketSystem>
 */
class TicketSystemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketSystem::class);
    }

    public function save(TicketSystem $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }

    public function remove(TicketSystem $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }
}
