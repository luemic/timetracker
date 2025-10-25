<?php

namespace App\Service;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array<int, array{id:int,name:string}> */
    public function list(): array
    {
        $items = $this->customers->findBy([], ['id' => 'ASC']);
        return array_map(fn(Customer $c) => ['id' => $c->getId() ?? 0, 'name' => $c->getName()], $items);
    }

    public function get(int $id): ?array
    {
        $c = $this->customers->find($id);
        if (!$c) return null;
        return ['id' => $c->getId() ?? 0, 'name' => $c->getName()];
    }

    /** @param array{name?:string} $data */
    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Field "name" is required');
        }
        $c = (new Customer())->setName($name);
        $this->customers->save($c, true);
        return ['id' => $c->getId() ?? 0, 'name' => $c->getName()];
    }

    /** @param array{name?:string} $data */
    public function update(int $id, array $data): ?array
    {
        $c = $this->customers->find($id);
        if (!$c) return null;
        if (array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Field "name" must not be empty');
            }
            $c->setName($name);
        }
        $this->em->flush();
        return ['id' => $c->getId() ?? 0, 'name' => $c->getName()];
    }

    public function delete(int $id): bool
    {
        $c = $this->customers->find($id);
        if (!$c) return false;
        $this->customers->remove($c, true);
        return true;
    }
}
