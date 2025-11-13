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

    /**
     * Sum of durationMinutes for a given project.
     */
    public function sumMinutesByProject(Project $project): int
    {
        $qb = $this->createQueryBuilder('tb')
            ->select('COALESCE(SUM(tb.durationMinutes), 0) as total')
            ->where('tb.project = :project')
            ->setParameter('project', $project);
        $res = $qb->getQuery()->getSingleScalarResult();
        return (int) $res;
    }

    /**
     * Aggregiere gebuchte Minuten je Projekt im Zeitraum [start, end).
     * Liefert zusätzlich Budgettyp und Stundensatz des Projekts für Umsatzberechnung.
     *
     * @return array<int, array{projectId:int, projectName:string, minutes:int, budgetType:string, hourlyRate:?string}>
     */
    public function aggregateByProjectInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $qb = $this->createQueryBuilder('tb')
            ->select('p.id AS projectId, p.name AS projectName, COALESCE(SUM(tb.durationMinutes), 0) AS minutes, p.budgetType AS budgetType, p.hourlyRate AS hourlyRate')
            ->join('tb.project', 'p')
            ->where('tb.startedAt >= :start')
            ->andWhere('tb.startedAt < :end')
            ->groupBy('p.id')
            ->addGroupBy('p.name')
            ->addGroupBy('p.budgetType')
            ->addGroupBy('p.hourlyRate')
            ->orderBy('p.name', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        $rows = $qb->getQuery()->getArrayResult();
        // Cast minutes to int explicitly
        foreach ($rows as &$r) {
            $r['minutes'] = (int) $r['minutes'];
        }
        return $rows;
    }
}
