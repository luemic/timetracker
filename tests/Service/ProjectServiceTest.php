<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\Project;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Service\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProjectServiceTest extends TestCase
{
    private function makeService(
        ProjectRepository $projects = null,
        CustomerRepository $customers = null,
        EntityManagerInterface $em = null,
    ): ProjectService {
        $projects ??= $this->createMock(ProjectRepository::class);
        $customers ??= $this->createMock(CustomerRepository::class);
        $em ??= $this->createMock(EntityManagerInterface::class);
        return new ProjectService($projects, $customers, $em);
    }

    public function testListMapsEntities(): void
    {
        [$p1, $p2] = $this->projectsWithIds();
        $repo = $this->createMock(ProjectRepository::class);
        $repo->expects($this->once())
            ->method('findBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn([$p1, $p2]);

        $svc = $this->makeService($repo);
        $out = $svc->list();
        $this->assertSame([
            ['id'=>1,'name'=>'P1','customerId'=>10,'externalTicketUrl'=>null,'externalTicketLogin'=>null,'externalTicketCredentials'=>null],
            ['id'=>2,'name'=>'P2','customerId'=>20,'externalTicketUrl'=>null,'externalTicketLogin'=>null,'externalTicketCredentials'=>null],
        ], $out);
    }

    public function testGetReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(ProjectRepository::class);
        $repo->method('find')->with(99)->willReturn(null);
        $svc = $this->makeService($repo);
        $this->assertNull($svc->get(99));
    }

    public function testGetReturnsMappedArray(): void
    {
        [$p] = $this->projectsWithIds();
        $repo = $this->createMock(ProjectRepository::class);
        $repo->method('find')->with(1)->willReturn($p);
        $svc = $this->makeService($repo);
        $this->assertSame([
            'id'=>1,'name'=>'P1','customerId'=>10,'externalTicketUrl'=>null,'externalTicketLogin'=>null,'externalTicketCredentials'=>null
        ], $svc->get(1));
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $svc = $this->makeService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fields "name" and numeric "customerId" are required');
        $svc->create(['name'=>'', 'customerId'=>null]);
    }

    public function testCreateFailsIfCustomerMissing(): void
    {
        $projects = $this->createMock(ProjectRepository::class);
        $customers = $this->createMock(CustomerRepository::class);
        $customers->method('find')->with(10)->willReturn(null);
        $svc = $this->makeService($projects, $customers);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer not found');
        $svc->create(['name'=>'Name', 'customerId'=>10]);
    }

    public function testCreateSavesAndReturnsData(): void
    {
        $projects = $this->createMock(ProjectRepository::class);
        $projects->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Project::class), true);
        $customers = $this->createMock(CustomerRepository::class);
        $customer = (new Customer());
        $this->setEntityId($customer, 10);
        $customers->method('find')->with(10)->willReturn($customer);

        $svc = $this->makeService($projects, $customers);
        $result = $svc->create([
            'name' => '  P  ',
            'customerId' => 10,
            'externalTicketUrl' => null,
            'externalTicketLogin' => null,
            'externalTicketCredentials' => null,
        ]);
        $this->assertSame([
            'id'=>0,'name'=>'P','customerId'=>10,'externalTicketUrl'=>null,'externalTicketLogin'=>null,'externalTicketCredentials'=>null
        ], $result);
    }

    public function testUpdateReturnsNullWhenNotFound(): void
    {
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(5)->willReturn(null);
        $svc = $this->makeService($projects);
        $this->assertNull($svc->update(5, ['name'=>'X']));
    }

    public function testUpdateValidatesNameWhenProvided(): void
    {
        [$p] = $this->projectsWithIds();
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $svc = $this->makeService($projects, null, $em);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "name" must not be empty');
        $svc->update(1, ['name'=>'   ']);
    }

    public function testUpdateCanChangeCustomer(): void
    {
        [$p] = $this->projectsWithIds();
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $customers = $this->createMock(CustomerRepository::class);
        $newCustomer = new Customer();
        $this->setEntityId($newCustomer, 77);
        $customers->method('find')->with(77)->willReturn($newCustomer);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $svc = $this->makeService($projects, $customers, $em);
        $out = $svc->update(1, ['customerId'=>77]);
        $this->assertSame(77, $out['customerId']);
    }

    public function testUpdateRejectsNonNumericCustomer(): void
    {
        [$p] = $this->projectsWithIds();
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $svc = $this->makeService($projects);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Field "customerId" must be numeric');
        $svc->update(1, ['customerId'=>'abc']);
    }

    public function testUpdateThrowsIfCustomerNotFound(): void
    {
        [$p] = $this->projectsWithIds();
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(1)->willReturn($p);
        $customers = $this->createMock(CustomerRepository::class);
        $customers->method('find')->with(77)->willReturn(null);
        $svc = $this->makeService($projects, $customers);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer not found');
        $svc->update(1, ['customerId'=>77]);
    }

    public function testDeleteBehavior(): void
    {
        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('find')->with(9)->willReturn(null);
        $svc = $this->makeService($projects);
        $this->assertFalse($svc->delete(9));

        [$p] = $this->projectsWithIds();
        $projects2 = $this->createMock(ProjectRepository::class);
        $projects2->method('find')->with(1)->willReturn($p);
        $projects2->expects($this->once())->method('remove')->with($p, true);
        $svc2 = $this->makeService($projects2);
        $this->assertTrue($svc2->delete(1));
    }

    private function projectsWithIds(): array
    {
        $c1 = new Customer(); $this->setEntityId($c1, 10);
        $c2 = new Customer(); $this->setEntityId($c2, 20);
        $p1 = (new Project())->setName('P1')->setCustomer($c1);
        $p2 = (new Project())->setName('P2')->setCustomer($c2);
        $this->setEntityId($p1, 1); $this->setEntityId($p2, 2);
        return [$p1, $p2];
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
