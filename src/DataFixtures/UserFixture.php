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
        $repo = $manager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => 'test@example.com']);

        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail('test@example.com');
            $manager->persist($user);
        }

        // Always ensure the password matches the expected test credentials using the same hasher
        $hashed = $this->passwordHasher->hashPassword($user, 'test12345');
        $user->setPassword($hashed);

        $manager->flush();

        $this->addReference(self::REF_TEST_USER, $user);
    }
}
