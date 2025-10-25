<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ActivityServiceTest extends TestCase
{
    private function makeService(
        ActivityRepository $repo = null,
        EntityManagerInterface $em = null,
    ): ActivityService {
        $repo ??= $this->createMock(ActivityRepository::class);
        $em ??= $this->createMock(EntityManagerInterface::class);
        return new ActivityService($repo, $em);
    }

    public function testListMapsEntities(): void
    {
        $a1 = (new Activity())->setName('Dev');
        $a2 = (new Activity())->setName('QA');
        $this->setEntityId($a1, 1);
        $this->setEntityId($a2, 2);

        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects($this->once())
            ->method('findBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn([$a1, $a2]);

        $svc = $this->makeService($repo);
        $this->assertSame([
            ['id' => 1, 'name' => 'Dev'],
            ['id' => 2, 'name' => 'QA'],
        ], $svc->list());
    }

    public function testGetNullWhenNotFound(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->method('find')->with(99)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->get(99));
    }

    public function testGetReturnsMappedArray(): void
    {
        $a = (new Activity())->setName('Support');
        $this->setEntityId($a, 5);
        $repo = $this->createMock(ActivityRepository::class);
        $repo->method('find')->with(5)->willReturn($a);
        $svc = $this->makeService($repo);
        $this->assertSame(['id' => 5, 'name' => 'Support'], $svc->get(5));
    }

    public function testCreateRequiresName(): void
    {
        $svc = $this->makeService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "name" is required');
        $svc->create(['name' => '   ']);
    }

    public function testCreatePersists(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Activity && $entity->getName() === 'Coding';
            }), true);
        $svc = $this->makeService($repo);
        $this->assertSame(['id' => 0, 'name' => 'Coding'], $svc->create(['name' => ' Coding ']));
    }

    public function testUpdateValidatesNonEmptyName(): void
    {
        $existing = (new Activity())->setName('Old');
        $this->setEntityId($existing, 2);
        $repo = $this->createMock(ActivityRepository::class);
        $repo->method('find')->with(2)->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $svc = $this->makeService($repo, $em);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "name" must not be empty');
        $svc->update(2, ['name' => '  ']);
    }

    public function testUpdateFlushesAndReturns(): void
    {
        $existing = (new Activity())->setName('Old');
        $this->setEntityId($existing, 8);
        $repo = $this->createMock(ActivityRepository::class);
        $repo->method('find')->with(8)->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $svc = $this->makeService($repo, $em);
        $this->assertSame(['id' => 8, 'name' => 'New'], $svc->update(8, ['name' => ' New ']));
        $this->assertSame('New', $existing->getName());
    }

    public function testDeleteBehavior(): void
    {
        $repo = $this->createMock(ActivityRepository::class);
        $repo->method('find')->with(1)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertFalse($svc->delete(1));

        $a = (new Activity())->setName('X');
        $this->setEntityId($a, 4);
        $repo2 = $this->createMock(ActivityRepository::class);
        $repo2->method('find')->with(4)->willReturn($a);
        $repo2->expects($this->once())->method('remove')->with($a, true);
        $svc2 = $this->makeService($repo2);
        $this->assertTrue($svc2->delete(4));
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        if ($ref->hasProperty('id')) {
            $prop = $ref->getProperty('id');
            $prop->setAccessible(true);
            $prop->setValue($entity, $id);
        }
    }
}
