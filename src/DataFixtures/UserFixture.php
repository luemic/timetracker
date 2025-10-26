<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Loads a single default user for tests and local development.
 *
 * Credentials:
 *  - E-Mail: test@example.com
 *  - Password: test12345
 */
class UserFixture extends Fixture
{
    public const REF_TEST_USER = 'user_test_example_com';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // If a user with the same email already exists, skip to keep fixture idempotent
        $repo = $manager->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => 'test@example.com']);
        if ($existing instanceof User) {
            $this->addReference(self::REF_TEST_USER, $existing);
            return;
        }

        $user = new User();
        $user->setEmail('test@example.com');
        $hash = $this->passwordHasher->hashPassword($user, 'test12345');
        $user->setPassword($hash);
        $manager->persist($user);
        $manager->flush();

        $this->addReference(self::REF_TEST_USER, $user);
    }
}
