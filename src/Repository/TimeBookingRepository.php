<?php

namespace App\Repository;

use App\Entity\TimeBooking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeBooking>
 */
class TimeBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeBooking::class);
    }

    /**
     * Persist a TimeBooking entity.
     *
     * @param TimeBooking $entity The time booking to persist
     * @param bool $flush Whether to flush immediately
     */
    public function save(TimeBooking $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Remove a TimeBooking entity.
     *
     * @param TimeBooking $entity The time booking to remove
     * @param bool $flush Whether to flush immediately
     */
    public function remove(TimeBooking $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }
}
