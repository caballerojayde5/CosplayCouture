<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-user-roles',
    description: 'Fix user roles based on type column',
)]
class FixUserRolesCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Update admin users
            $adminCount = $this->connection->executeStatement(
                "UPDATE user SET roles = ? WHERE type = ?",
                ['["ROLE_ADMIN"]', 'admin']
            );
            
            $io->success("Updated {$adminCount} admin users");

            // Update staff users
            $staffCount = $this->connection->executeStatement(
                "UPDATE user SET roles = ? WHERE type = ?",
                ['["ROLE_STAFF"]', 'staff']
            );
            
            $io->success("Updated {$staffCount} staff users");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}