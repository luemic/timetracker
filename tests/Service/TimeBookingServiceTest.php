<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\TimeBooking;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeBookingRepository;
use App\Service\TimeBookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class TimeBookingServiceTest extends TestCase
{
    private function makeService(
        TimeBookingRepository $tbRepo = null,
        ProjectRepository $projRepo = null,
        ActivityRepository $actRepo = null,
        EntityManagerInterface $em = null,
        Security $security = null,
    ): TimeBookingService {
        $tbRepo ??= $this->createMock(TimeBookingRepository::class);
        $projRepo ??= $this->createMock(ProjectRepository::class);
        $actRepo ??= $this->createMock(ActivityRepository::class);
        $em ??= $this->createMock(EntityManagerInterface::class);
        return new TimeBookingService($tbRepo, $projRepo, $actRepo, $em, $security);
    }

    public function testListMapsEntities(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = (new Activity())->setName('Dev'); $this->setId($a, 2);
        $tb = (new TimeBooking())
            ->setProject($p)
            ->setActivity($a)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T-1')
            ->setDurationMinutes(60);
        $this->setId($tb, 5);

        $repo = $this->createMock(TimeBookingRepository::class);
        $repo->expects($this->once())->method('findBy')->with([], ['startedAt' => 'DESC', 'id' => 'DESC'])->willReturn([$tb]);

        $svc = $this->makeService($repo);
        $out = $svc->list();
        $this->assertSame(1, $out[0]['projectId']);
        $this->assertSame(2, $out[0]['activityId']);
        $this->assertSame('T-1', $out[0]['ticketNumber']);
        $this->assertSame(60, $out[0]['durationMinutes']);
    }

    public function testGetNullWhenNotFound(): void
    {
        $repo = $this->createMock(TimeBookingRepository::class);
        $repo->method('find')->with(9)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->get(9));
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $svc = $this->makeService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->create(['projectId' => null, 'ticketNumber' => '', 'startedAt' => '', 'endedAt' => '']);
    }

    public function testCreateFailsIfProjectMissing(): void
    {
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn(null);
        $svc = $this->makeService(null, $projects);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project not found');
        $svc->create(['projectId'=>1,'ticketNumber'=>'T','startedAt'=>'2024-01-01T10:00:00+00:00','endedAt'=>'2024-01-01T11:00:00+00:00']);
    }

    public function testCreateFailsIfActivityNotFound(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $activities = $this->createMock(ActivityRepository::class);
        $activities->method('find')->with(2)->willReturn(null);
        $svc = $this->makeService(null, $projects, $activities);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Activity not found');
        $svc->create(['projectId'=>1,'activityId'=>2,'ticketNumber'=>'T','startedAt'=>'2024-01-01T10:00:00+00:00','endedAt'=>'2024-01-01T11:00:00+00:00']);
    }

    public function testCreateComputesDurationIfMissing(): void
    {
        $p = new Project(); $this->setId($p, 3);
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(3)->willReturn($p);
        $repo = $this->createMock(TimeBookingRepository::class);
        $repo->expects($this->once())->method('save')->with($this->isInstanceOf(TimeBooking::class), true);
        $svc = $this->makeService($repo, $projects);
        $out = $svc->create([
            'projectId'=>3,
            'ticketNumber'=>'T-99',
            'startedAt'=>'2024-01-01T10:00:00+00:00',
            'endedAt'=>'2024-01-01T11:30:00+00:00',
        ]);
        $this->assertSame(90, $out['durationMinutes']);
    }

    public function testCreateRejectsInvalidDates(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $svc = $this->makeService(null, $projects);
        $this->expectException(\InvalidArgumentException::class);
        $svc->create(['projectId'=>1,'ticketNumber'=>'T','startedAt'=>'bad','endedAt'=>'also-bad']);
    }

    public function testCreateRejectsOverlap(): void
    {
        // Arrange
        $p = new Project(); $this->setId($p, 1);
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $tbRepo = $this->createMock(TimeBookingRepository::class);
        $security = $this->createMock(Security::class);
        $user = new User();
        $security->method('getUser')->willReturn($user);
        $tbRepo->method('existsOverlap')->willReturn(true);
        $svc = $this->makeService($tbRepo, $projects, null, null, $security);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zeitüberschneidung: Der Zeitraum überlappt mit einer bestehenden Buchung (gleiches Projekt, gleicher Benutzer).');

        // Act
        $svc->create([
            'projectId'=>1,
            'ticketNumber'=>'T-OL',
            'startedAt'=>'2024-01-01T10:00:00+00:00',
            'endedAt'=>'2024-01-01T11:00:00+00:00',
        ]);
    }

    public function testCreateRejectsEndedBeforeStarted(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $svc = $this->makeService(null, $projects);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('endedAt must be after startedAt');
        $svc->create(['projectId'=>1,'ticketNumber'=>'T','startedAt'=>'2024-01-01T10:00:00+00:00','endedAt'=>'2024-01-01T09:59:00+00:00']);
    }

    public function testUpdateReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(TimeBookingRepository::class);
        $repo->method('find')->with(8)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->update(8, ['ticketNumber'=>'X']));
    }

    public function testUpdateCanChangeProjectAndActivityAndFields(): void
    {
        $pOld = new Project(); $this->setId($pOld, 1);
        $pNew = new Project(); $this->setId($pNew, 2);
        $aOld = new Activity(); $this->setId($aOld, 10);
        $aNew = new Activity(); $this->setId($aNew, 20);
        $tb = (new TimeBooking())
            ->setProject($pOld)
            ->setActivity($aOld)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T10:30:00+00:00'))
            ->setTicketNumber('T-1')
            ->setDurationMinutes(30);
        $this->setId($tb, 7);

        $tbRepo = $this->createMock(TimeBookingRepository::class);
        $tbRepo->method('find')->with(7)->willReturn($tb);
        $projRepo = $this->createMock(ProjectRepository::class);
        $projRepo->method('find')->with(2)->willReturn($pNew);
        $actRepo = $this->createMock(ActivityRepository::class);
        $actRepo->method('find')->with(20)->willReturn($aNew);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $svc = $this->makeService($tbRepo, $projRepo, $actRepo, $em);
        $out = $svc->update(7, [
            'projectId'=>2,
            'activityId'=>20,
            'startedAt'=>'2024-01-01T11:00:00+00:00',
            'endedAt'=>'2024-01-01T12:00:00+00:00',
            'ticketNumber'=>'T-2',
            'durationMinutes'=>60,
        ]);
        $this->assertSame(2, $out['projectId']);
        $this->assertSame(20, $out['activityId']);
        $this->assertSame('T-2', $out['ticketNumber']);
        $this->assertSame(60, $out['durationMinutes']);
    }

    public function testUpdateAllowsNullActivity(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = new Activity(); $this->setId($a, 10);
        $tb = (new TimeBooking())
            ->setProject($p)
            ->setActivity($a)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T')
            ->setDurationMinutes(60);
        $tbRepo = $this->createMock(TimeBookingRepository::class);
        $tbRepo->method('find')->with(1)->willReturn($tb);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $svc = $this->makeService($tbRepo, null, null, $em);
        $out = $svc->update(1, ['activityId'=>null]);
        $this->assertNull($out['activityId']);
    }

    public function testUpdateValidationErrors(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $tb = (new TimeBooking())
            ->setProject($p)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T')
            ->setDurationMinutes(60);
        $tbRepo = $this->createMock(TimeBookingRepository::class);
        $tbRepo->method('find')->with(1)->willReturn($tb);
        $svc = $this->makeService($tbRepo);

        $this->expectException(\InvalidArgumentException::class);
        $svc->update(1, ['ticketNumber'=>'   ']);
    }

    public function testUpdateRejectsEndedBeforeStartedWhenOnlyEndedChanges(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $tb = (new TimeBooking())
            ->setProject($p)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T')
            ->setDurationMinutes(60);
        $tbRepo = $this->createMock(TimeBookingRepository::class);
        $tbRepo->method('find')->with(1)->willReturn($tb);
        $svc = $this->makeService($tbRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('endedAt must be after startedAt');
        $svc->update(1, ['endedAt'=>'2024-01-01T09:59:00+00:00']);
    }

    public function testUpdateRejectsStartedAfterEndedWhenOnlyStartedChanges(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $tb = (new TimeBooking())
            ->setProject($p)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T')
            ->setDurationMinutes(60);
        $tbRepo = $this->createMock(TimeBookingRepository::class);
        $tbRepo->method('find')->with(1)->willReturn($tb);
        $svc = $this->makeService($tbRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('endedAt must be after startedAt');
        $svc->update(1, ['startedAt'=>'2024-01-01T11:00:01+00:00']);
    }

    public function testUpdateRejectsOverlap(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $tb = (new TimeBooking())
            ->setProject($p)
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T')
            ->setDurationMinutes(60);
        $this->setId($tb, 42);
        $tbRepo = $this->createMock(TimeBookingRepository::class);
        // When user is present, service uses findOneBy with id+user; but mocking the method call directly is complex,
        // we can bypass by not relying on that path: instead, provide Security and make findOneBy return the entity.
        $tbRepo->method('findOneBy')->willReturn($tb);
        $tbRepo->method('existsOverlap')->willReturn(true);
        $security = $this->createMock(Security::class);
        $user = new User();
        $security->method('getUser')->willReturn($user);

        $svc = $this->makeService($tbRepo, null, null, null, $security);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zeitüberschneidung: Der Zeitraum überlappt mit einer bestehenden Buchung (gleiches Projekt, gleicher Benutzer).');
        $svc->update(42, [
            // no field changes required; service will check with current values
        ]);
    }

    public function testDeleteBehavior(): void
    {
        $repo = $this->createMock(TimeBookingRepository::class);
        $repo->method('find')->with(3)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertFalse($svc->delete(3));

        $tb = (new TimeBooking())
            ->setProject((new Project()))
            ->setStartedAt(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'))
            ->setEndedAt(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'))
            ->setTicketNumber('T')
            ->setDurationMinutes(60);
        $repo2 = $this->createMock(TimeBookingRepository::class);
        $repo2->method('find')->with(1)->willReturn($tb);
        $repo2->expects($this->once())->method('remove')->with($tb, true);
        $svc2 = $this->makeService($repo2);
        $this->assertTrue($svc2->delete(1));
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        if ($ref->hasProperty('id')) {
            $prop = $ref->getProperty('id');
            $prop->setAccessible(true);
            $prop->setValue($entity, $id);
        }
    }
}
