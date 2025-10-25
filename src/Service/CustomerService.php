<?php

namespace App\Service;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing customers.
 * Provides CRUD operations and maps entities to simple arrays for the API layer.
 */
class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Return all customers sorted by ID ascending.
     *
     * @return array<int, array{id:int,name:string}>
     */
    public function list(): array
    {
        $items = $this->customers->findBy([], ['id' => 'ASC']);
        return array_map(
            fn(Customer $customer) => ['id' => $customer->getId() ?? 0, 'name' => $customer->getName()],
            $items
        );
    }

    /**
     * Get a customer by ID, mapped for JSON.
     */
    public function get(int $id): ?array
    {
        $customer = $this->customers->find($id);
        if (!$customer) {
            return null;
        }

        return ['id' => $customer->getId() ?? 0, 'name' => $customer->getName()];
    }

    /**
     * Create a customer from request data.
     *
     * @param array{name?:string} $data
     * @return array{id:int,name:string}
     */
    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Field "name" is required');
        }
        $customer = (new Customer())->setName($name);
        $this->customers->save($customer, true);
        return ['id' => $customer->getId() ?? 0, 'name' => $customer->getName()];
    }

    /**
     * Update a customer with partial data.
     *
     * @param array{name?:string} $data
     * @return array{id:int,name:string}|null
     */
    public function update(int $id, array $data): ?array
    {
        $customer = $this->customers->find($id);
        if (!$customer) {
            return null;
        }
        if (array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Field "name" must not be empty');
            }
            $customer->setName($name);
        }
        $this->em->flush();

        return ['id' => $customer->getId() ?? 0, 'name' => $customer->getName()];
    }

    /**
     * Delete a customer.
     */
    public function delete(int $id): bool
    {
        $customer = $this->customers->find($id);
        if (!$customer) {
            return false;
        }
        $this->customers->remove($customer, true);

        return true;
    }
}
