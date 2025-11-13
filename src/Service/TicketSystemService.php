<?php

namespace App\Service;

use App\Entity\TicketSystem;
use App\Repository\TicketSystemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing ticket systems (e.g., Jira credentials).
 */
class TicketSystemService
{
    public function __construct(
        private readonly TicketSystemRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array<int, array{id:int,type:string,name:string,username:string,secret:string,url: ?string}>
     */
    public function list(): array
    {
        $items = $this->repo->findBy([], ['id' => 'ASC']);
        return array_map(fn(TicketSystem $t) => $this->toArray($t), $items);
    }

    public function get(int $id): ?array
    {
        $t = $this->repo->find($id);
        return $t ? $this->toArray($t) : null;
    }

    /**
     * @param array{type?:string,name?:string,username?:string,secret?:string,url?:?string} $data
     * @return array{id:int,type:string,name:string,username:string,secret:string,url:?string}
     */
    public function create(array $data): array
    {
        $type = strtolower(trim((string)($data['type'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        $username = trim((string)($data['username'] ?? ''));
        $secret = (string)($data['secret'] ?? '');
        $url = isset($data['url']) && $data['url'] !== '' ? (string)$data['url'] : null;
        if ($type === '') { $type = 'jira'; }
        if ($username === '') { throw new \InvalidArgumentException('Field "username" is required'); }
        if ($secret === '') { throw new \InvalidArgumentException('Field "secret" is required'); }

        $ts = (new TicketSystem())
            ->setType($type)
            ->setName($name)
            ->setUsername($username)
            ->setSecret($secret)
            ->setUrl($url);
        $this->repo->save($ts, true);
        return $this->toArray($ts);
    }

    /**
     * @param array{type?:string,name?:string,username?:string,secret?:string,url?:?string} $data
     * @return array{id:int,type:string,name:string,username:string,secret:string,url:?string}|null
     */
    public function update(int $id, array $data): ?array
    {
        $ts = $this->repo->find($id);
        if (!$ts) { return null; }
        if (array_key_exists('type', $data)) {
            $type = trim((string)$data['type']);
            if ($type === '') { $type = 'jira'; }
            $ts->setType(strtolower($type));
        }
        if (array_key_exists('name', $data)) {
            $ts->setName(trim((string)$data['name']));
        }
        if (array_key_exists('username', $data)) {
            $username = trim((string)$data['username']);
            if ($username === '') { throw new \InvalidArgumentException('Field "username" must not be empty'); }
            $ts->setUsername($username);
        }
        if (array_key_exists('secret', $data)) {
            $secret = (string)$data['secret'];
            if ($secret === '') { throw new \InvalidArgumentException('Field "secret" must not be empty'); }
            $ts->setSecret($secret);
        }
        if (array_key_exists('url', $data)) {
            $url = $data['url'];
            $ts->setUrl($url === '' ? null : (string)$url);
        }
        $this->em->flush();
        return $this->toArray($ts);
    }

    public function delete(int $id): bool
    {
        $ts = $this->repo->find($id);
        if (!$ts) { return false; }
        $this->repo->remove($ts, true);
        return true;
    }

    /**
     * @return array{id:int,type:string,name:string,username:string,secret:string,url:?string}
     */
    private function toArray(TicketSystem $t): array
    {
        return [
            'id' => $t->getId() ?? 0,
            'type' => $t->getType(),
            'name' => $t->getName(),
            'username' => $t->getUsername(),
            'secret' => $t->getSecret(),
            'url' => $t->getUrl(),
        ];
    }
}
