<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\CustomerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CustomerServiceTest extends TestCase
{
    private function makeService(
        CustomerRepository $repo = null,
        EntityManagerInterface $em = null,
    ): CustomerService {
        $repo ??= $this->createMock(CustomerRepository::class);
        $em ??= $this->createMock(EntityManagerInterface::class);
        return new CustomerService($repo, $em);
    }

    public function testListMapsEntities(): void
    {
        $c1 = (new Customer())->setName('Alice');
        $c2 = (new Customer())->setName('Bob');
        // set private id via reflection for stable output
        $this->setEntityId($c1, 1);
        $this->setEntityId($c2, 2);

        $repo = $this->createMock(CustomerRepository::class);
        $repo->expects($this->once())
            ->method('findBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn([$c1, $c2]);

        $svc = $this->makeService($repo);
        $out = $svc->list();

        $this->assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ], $out);
    }

    public function testGetReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(123)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->get(123));
    }

    public function testGetReturnsMappedArrayWhenFound(): void
    {
        $c = (new Customer())->setName('ACME');
        $this->setEntityId($c, 10);
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(10)->willReturn($c);
        $svc = $this->makeService($repo);
        $this->assertSame(['id' => 10, 'name' => 'ACME'], $svc->get(10));
    }

    public function testCreateRequiresName(): void
    {
        $svc = $this->makeService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "name" is required');
        $svc->create(['name' => '   ']);
    }

    public function testCreatePersistsAndReturnsData(): void
    {
        $captured = null;
        $repo = $this->createMock(CustomerRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($entity) use (&$captured) {
                $captured = $entity;
                return $entity instanceof Customer && $entity->getName() === 'NewCo';
            }), true);

        $svc = $this->makeService($repo);
        $result = $svc->create(['name' => ' NewCo ']);
        // After save(), ID might still be null in unit test; service falls back to 0
        $this->assertSame(['id' => 0, 'name' => 'NewCo'], $result);
        $this->assertInstanceOf(Customer::class, $captured);
    }

    public function testUpdateReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(5)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->update(5, ['name' => 'X']));
    }

    public function testUpdateValidatesNonEmptyNameWhenProvided(): void
    {
        $existing = (new Customer())->setName('Old');
        $this->setEntityId($existing, 7);
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(7)->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        // flush should not be called when exception thrown
        $em->expects($this->never())->method('flush');

        $svc = $this->makeService($repo, $em);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "name" must not be empty');
        $svc->update(7, ['name' => '   ']);
    }

    public function testUpdateFlushesAndReturnsUpdatedData(): void
    {
        $existing = (new Customer())->setName('Old');
        $this->setEntityId($existing, 3);
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(3)->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $svc = $this->makeService($repo, $em);
        $result = $svc->update(3, ['name' => ' New ']);
        $this->assertSame(['id' => 3, 'name' => 'New'], $result);
        $this->assertSame('New', $existing->getName());
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(9)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertFalse($svc->delete(9));
    }

    public function testDeleteRemovesAndReturnsTrue(): void
    {
        $existing = (new Customer())->setName('Del');
        $this->setEntityId($existing, 11);
        $repo = $this->createMock(CustomerRepository::class);
        $repo->method('find')->with(11)->willReturn($existing);
        $repo->expects($this->once())->method('remove')->with($existing, true);
        $svc = $this->makeService($repo);
        $this->assertTrue($svc->delete(11));
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
