<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\TimeBooking;
use App\Entity\User;
use DateTimeImmutable;
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

    /**
     * Check if an overlapping time booking exists for a given user and project.
     * Two intervals [start, end) overlap if existing.startedAt < :end AND existing.endedAt > :start.
     */
    public function existsOverlap(User $user, Project $project, DateTimeImmutable $start, DateTimeImmutable $end, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('tb')
            ->select('1')
            ->where('tb.user = :user')
            ->andWhere('tb.project = :project')
            ->andWhere('tb.startedAt < :end')
            ->andWhere('tb.endedAt > :start')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->setParameter('start', $start)
            ->setParameter('end', $end);
        if ($excludeId !== null) {
            $qb->andWhere('tb.id <> :excludeId')->setParameter('excludeId', $excludeId);
        }
        return (bool) $qb->getQuery()->getOneOrNullResult();
    }
}
