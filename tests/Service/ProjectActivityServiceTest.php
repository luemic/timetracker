<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\ProjectActivity;
use App\Repository\ActivityRepository;
use App\Repository\ProjectActivityRepository;
use App\Repository\ProjectRepository;
use App\Service\ProjectActivityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProjectActivityServiceTest extends TestCase
{
    private function makeService(
        ProjectActivityRepository $paRepo = null,
        ProjectRepository $projRepo = null,
        ActivityRepository $actRepo = null,
        EntityManagerInterface $em = null,
    ): ProjectActivityService {
        $paRepo ??= $this->createMock(ProjectActivityRepository::class);
        $projRepo ??= $this->createMock(ProjectRepository::class);
        $actRepo ??= $this->createMock(ActivityRepository::class);
        $em ??= $this->createMock(EntityManagerInterface::class);
        return new ProjectActivityService($paRepo, $projRepo, $actRepo, $em);
    }

    public function testListMapsEntities(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = new Activity(); $this->setId($a, 2);
        $pa = (new ProjectActivity())->setProject($p)->setActivity($a)->setFactor(1.5);
        $this->setId($pa, 7);

        $repo = $this->createMock(ProjectActivityRepository::class);
        $repo->expects($this->once())->method('findBy')->with([], ['id' => 'ASC'])->willReturn([$pa]);

        $svc = $this->makeService($repo);
        $out = $svc->list();
        $this->assertSame([
            ['id'=>7,'projectId'=>1,'activityId'=>2,'factor'=>1.5],
        ], $out);
    }

    public function testGetNullWhenNotFound(): void
    {
        $repo = $this->createMock(ProjectActivityRepository::class);
        $repo->method('find')->with(9)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->get(9));
    }

    public function testGetReturnsMappedArray(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = new Activity(); $this->setId($a, 2);
        $pa = (new ProjectActivity())->setProject($p)->setActivity($a)->setFactor(2.0);
        $this->setId($pa, 3);
        $repo = $this->createMock(ProjectActivityRepository::class);
        $repo->method('find')->with(3)->willReturn($pa);
        $svc = $this->makeService($repo);
        $this->assertSame(['id'=>3,'projectId'=>1,'activityId'=>2,'factor'=>2.0], $svc->get(3));
    }

    public function testCreateValidatesIds(): void
    {
        $svc = $this->makeService();
        $this->expectException(\InvalidArgumentException::class);
        $svc->create(['projectId'=>null,'activityId'=>null]);
    }

    public function testCreateFailsIfRefsMissing(): void
    {
        $projRepo = $this->createMock(ProjectRepository::class);
        $projRepo->method('find')->with(1)->willReturn(null);
        $svc = $this->makeService(null, $projRepo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project not found');
        $svc->create(['projectId'=>1,'activityId'=>2]);
    }

    public function testCreateFailsIfActivityMissing(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $projRepo = $this->createMock(ProjectRepository::class);
        $projRepo->method('find')->with(1)->willReturn($p);
        $actRepo = $this->createMock(ActivityRepository::class);
        $actRepo->method('find')->with(2)->willReturn(null);
        $svc = $this->makeService(null, $projRepo, $actRepo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Activity not found');
        $svc->create(['projectId'=>1,'activityId'=>2]);
    }

    public function testCreateUpsertsExisting(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = new Activity(); $this->setId($a, 2);
        $existing = (new ProjectActivity())->setProject($p)->setActivity($a)->setFactor(1.0);
        $this->setId($existing, 9);

        $paRepo = $this->createMock(ProjectActivityRepository::class);
        $paRepo->method('findOneBy')->with(['project'=>$p,'activity'=>$a])->willReturn($existing);
        $projRepo = $this->createMock(ProjectRepository::class); $projRepo->method('find')->with(1)->willReturn($p);
        $actRepo = $this->createMock(ActivityRepository::class); $actRepo->method('find')->with(2)->willReturn($a);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $svc = $this->makeService($paRepo, $projRepo, $actRepo, $em);
        $out = $svc->create(['projectId'=>1,'activityId'=>2,'factor'=>2.5]);
        $this->assertSame(['id'=>9,'projectId'=>1,'activityId'=>2,'factor'=>2.5], $out);
    }

    public function testCreateInsertsNew(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = new Activity(); $this->setId($a, 2);
        $paRepo = $this->createMock(ProjectActivityRepository::class);
        $paRepo->method('findOneBy')->willReturn(null);
        $paRepo->expects($this->once())->method('save')->with($this->isInstanceOf(ProjectActivity::class), true);
        $projRepo = $this->createMock(ProjectRepository::class); $projRepo->method('find')->with(1)->willReturn($p);
        $actRepo = $this->createMock(ActivityRepository::class); $actRepo->method('find')->with(2)->willReturn($a);

        $svc = $this->makeService($paRepo, $projRepo, $actRepo);
        $out = $svc->create(['projectId'=>1,'activityId'=>2,'factor'=>1.25]);
        $this->assertSame(1, $out['projectId']);
        $this->assertSame(2, $out['activityId']);
        $this->assertSame(1.25, $out['factor']);
    }

    public function testUpdateReturnsNullWhenNotFound(): void
    {
        $paRepo = $this->createMock(ProjectActivityRepository::class);
        $paRepo->method('find')->with(5)->willReturn(null);
        $svc = $this->makeService($paRepo);
        $this->assertNull($svc->update(5, ['factor'=>2]));
    }

    public function testUpdateCanChangeRefsAndFactor(): void
    {
        $pOld = new Project(); $this->setId($pOld, 1);
        $aOld = new Activity(); $this->setId($aOld, 2);
        $pa = (new ProjectActivity())->setProject($pOld)->setActivity($aOld)->setFactor(1.0);
        $this->setId($pa, 6);

        $pNew = new Project(); $this->setId($pNew, 3);
        $aNew = new Activity(); $this->setId($aNew, 4);

        $paRepo = $this->createMock(ProjectActivityRepository::class);
        $paRepo->method('find')->with(6)->willReturn($pa);
        $projRepo = $this->createMock(ProjectRepository::class); $projRepo->method('find')->with(3)->willReturn($pNew);
        $actRepo = $this->createMock(ActivityRepository::class); $actRepo->method('find')->with(4)->willReturn($aNew);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $svc = $this->makeService($paRepo, $projRepo, $actRepo, $em);
        $out = $svc->update(6, ['projectId'=>3, 'activityId'=>4, 'factor'=>3.5]);
        $this->assertSame(['id'=>6,'projectId'=>3,'activityId'=>4,'factor'=>3.5], $out);
    }

    public function testUpdateValidatesNumericIds(): void
    {
        $p = new Project(); $this->setId($p, 1);
        $a = new Activity(); $this->setId($a, 2);
        $pa = (new ProjectActivity())->setProject($p)->setActivity($a)->setFactor(1.0);
        $this->setId($pa, 6);
        $paRepo = $this->createMock(ProjectActivityRepository::class);
        $paRepo->method('find')->with(6)->willReturn($pa);
        $svc = $this->makeService($paRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('projectId must be numeric');
        $svc->update(6, ['projectId'=>'abc']);
    }

    public function testDeleteBehavior(): void
    {
        $paRepo = $this->createMock(ProjectActivityRepository::class);
        $paRepo->method('find')->with(3)->willReturn(null);
        $svc = $this->makeService($paRepo);
        $this->assertFalse($svc->delete(3));

        $pa = new ProjectActivity();
        $paRepo2 = $this->createMock(ProjectActivityRepository::class);
        $paRepo2->method('find')->with(1)->willReturn($pa);
        $paRepo2->expects($this->once())->method('remove')->with($pa, true);
        $svc2 = $this->makeService($paRepo2);
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
