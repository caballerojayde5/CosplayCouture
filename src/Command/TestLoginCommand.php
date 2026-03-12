<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-login',
    description: 'Test if a user can be found and password verified',
)]
class TestLoginCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'User email');
        $this->addArgument('password', InputArgument::REQUIRED, 'User password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("User not found with email: {$email}");
            return Command::FAILURE;
        }

        $io->success("User found: {$user->getEmail()}");
        $io->info("User class: " . get_class($user));
        $io->info("User roles: " . json_encode($user->getRoles()));
        $io->info("User type: " . $user->getType());
        $io->info("Stored password hash: " . substr($user->getPassword(), 0, 20) . "...");

        if ($this->passwordHasher->isPasswordValid($user, $password)) {
            $io->success("Password is valid!");
            return Command::SUCCESS;
        } else {
            $io->error("Password is INVALID!");
            return Command::FAILURE;
        }
    }
}
