<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-references',
    description: 'Migrate all foreign keys from admin/staff to user',
)]
class MigrateMessageReferencesCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Find ALL foreign keys that reference admin or staff tables
            $foreignKeys = $this->connection->fetchAllAssociative("
                SELECT 
                    TABLE_NAME,
                    CONSTRAINT_NAME,
                    REFERENCED_TABLE_NAME,
                    COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND REFERENCED_TABLE_NAME IN ('admin', 'staff')
            ");

            if (empty($foreignKeys)) {
                $io->warning('No foreign keys found referencing admin or staff tables');
            }

            // Drop all foreign key constraints
            foreach ($foreignKeys as $fk) {
                $tableName = $fk['TABLE_NAME'];
                $constraintName = $fk['CONSTRAINT_NAME'];
                $columnName = $fk['COLUMN_NAME'];
                $referencedTable = $fk['REFERENCED_TABLE_NAME'];
                
                $io->info("Dropping FK: {$constraintName} on table {$tableName} (column: {$columnName} -> {$referencedTable})");
                
                $this->connection->executeStatement(
                    "ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`"
                );
                
                $io->success("✓ Dropped foreign key: {$constraintName}");
            }

            // Now safely drop the admin and staff tables
            $this->connection->executeStatement("DROP TABLE IF EXISTS admin");
            $io->success('✓ Dropped admin table');
            
            $this->connection->executeStatement("DROP TABLE IF EXISTS staff");
            $io->success('✓ Dropped staff table');

            $io->success('All migrations completed successfully!');
            $io->note('You may need to update your entity relationships to point to User instead of Admin/Staff');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}