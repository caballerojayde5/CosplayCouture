<?php

namespace App\Command;

use App\Entity\Costume;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-activity-log',
    description: 'Test activity logging by creating, updating, and deleting a costume',
)]
class TestActivityLogCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create a costume
        $costume = new Costume();
        $costume->setName('Test Costume');
        $costume->setDescription('Test Description');
        $costume->setPrice(100.0);
        $costume->setStock(10);

        $this->entityManager->persist($costume);
        $this->entityManager->flush();

        $io->success("Created costume with ID: {$costume->getId()}");

        // Update the costume
        $costume->setName('Updated Test Costume');
        $this->entityManager->flush();

        $io->success("Updated costume");

        // Delete the costume
        $this->entityManager->remove($costume);
        $this->entityManager->flush();

        $io->success("Deleted costume");

        return Command::SUCCESS;
    }
}
