<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a new user by prompting for email and password',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        // Ask for email with validation and uniqueness check
        $emailQuestion = new Question('E-Mail: ');
        $emailQuestion->setValidator(function (?string $answer) {
            $email = is_string($answer) ? trim($answer) : '';
            if ($email === '') {
                throw new \RuntimeException('E-Mail darf nicht leer sein.');
            }
            if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
            }
            return strtolower($email);
        });
        $emailQuestion->setMaxAttempts(3);

        $email = $helper->ask($input, $output, $emailQuestion);

        // Uniqueness check
        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Ein Benutzer mit der E-Mail "%s" existiert bereits.', $email));
            return Command::FAILURE;
        }

        // Ask for password (twice)
        $pwdQuestion = new Question('Passwort: ');
        $pwdQuestion->setHidden(true);
        $pwdQuestion->setHiddenFallback(false);
        $pwdQuestion->setValidator(function (?string $answer) {
            $pwd = (string) $answer;
            if ($pwd === '') {
                throw new \RuntimeException('Das Passwort darf nicht leer sein.');
            }
            if (strlen($pwd) < 8) {
                throw new \RuntimeException('Das Passwort muss mindestens 8 Zeichen lang sein.');
            }
            return $pwd;
        });
        $pwdQuestion->setMaxAttempts(3);

        $password1 = $helper->ask($input, $output, $pwdQuestion);

        $pwdRepeatQuestion = new Question('Passwort (Wiederholung): ');
        $pwdRepeatQuestion->setHidden(true);
        $pwdRepeatQuestion->setHiddenFallback(false);
        $pwdRepeatQuestion->setValidator(function (?string $answer) {
            $pwd = (string) $answer;
            if ($pwd === '') {
                throw new \RuntimeException('Die Wiederholung darf nicht leer sein.');
            }
            return $pwd;
        });
        $pwdRepeatQuestion->setMaxAttempts(3);

        $password2 = $helper->ask($input, $output, $pwdRepeatQuestion);

        if ($password1 !== $password2) {
            $io->error('Die Passwörter stimmen nicht überein.');
            return Command::FAILURE;
        }

        // Create and persist user
        $user = new User();
        $user->setEmail($email);
        $hashed = $this->passwordHasher->hashPassword($user, $password1);
        $user->setPassword($hashed);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Benutzer "%s" wurde erfolgreich angelegt (ID: %d).', $user->getEmail(), $user->getId()));

        return Command::SUCCESS;
    }
}
