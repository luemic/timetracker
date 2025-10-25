<?php

namespace App\Service;

use App\Entity\Project;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly CustomerRepository $customers,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array<int, array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string}> */
    public function list(): array
    {
        $items = $this->projects->findBy([], ['id' => 'ASC']);
        return array_map(fn(Project $p) => $this->toArray($p), $items);
    }

    public function get(int $id): ?array
    {
        $p = $this->projects->find($id);
        return $p ? $this->toArray($p) : null;
    }

    /**
     * @param array{
     *   name?:string,
     *   customerId?:int,
     *   externalTicketUrl?:?string,
     *   externalTicketLogin?:?string,
     *   externalTicketCredentials?:?string
     * } $data
     */
    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $customerId = $data['customerId'] ?? null;
        if ($name === '' || !is_numeric($customerId)) {
            throw new \InvalidArgumentException('Fields "name" and numeric "customerId" are required');
        }
        $customer = $this->customers->find((int)$customerId);
        if (!$customer) {
            throw new \RuntimeException('Customer not found');
        }
        $p = (new Project())
            ->setName($name)
            ->setCustomer($customer)
            ->setExternalTicketUrl($data['externalTicketUrl'] ?? null)
            ->setExternalTicketLogin($data['externalTicketLogin'] ?? null)
            ->setExternalTicketCredentials($data['externalTicketCredentials'] ?? null);
        $this->projects->save($p, true);
        return $this->toArray($p);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): ?array
    {
        $p = $this->projects->find($id);
        if (!$p) return null;
        if (array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Field "name" must not be empty');
            }
            $p->setName($name);
        }
        if (array_key_exists('customerId', $data)) {
            $cid = $data['customerId'];
            if (!is_numeric($cid)) {
                throw new \InvalidArgumentException('Field "customerId" must be numeric');
            }
            $customer = $this->customers->find((int)$cid);
            if (!$customer) {
                throw new \RuntimeException('Customer not found');
            }
            $p->setCustomer($customer);
        }
        if (array_key_exists('externalTicketUrl', $data)) { $p->setExternalTicketUrl($data['externalTicketUrl']); }
        if (array_key_exists('externalTicketLogin', $data)) { $p->setExternalTicketLogin($data['externalTicketLogin']); }
        if (array_key_exists('externalTicketCredentials', $data)) { $p->setExternalTicketCredentials($data['externalTicketCredentials']); }
        $this->em->flush();
        return $this->toArray($p);
    }

    public function delete(int $id): bool
    {
        $p = $this->projects->find($id);
        if (!$p) return false;
        $this->projects->remove($p, true);
        return true;
    }

    private function toArray(Project $p): array
    {
        return [
            'id' => $p->getId() ?? 0,
            'name' => $p->getName(),
            'customerId' => $p->getCustomer()?->getId() ?? 0,
            'externalTicketUrl' => $p->getExternalTicketUrl(),
            'externalTicketLogin' => $p->getExternalTicketLogin(),
            'externalTicketCredentials' => $p->getExternalTicketCredentials(),
        ];
    }
}
