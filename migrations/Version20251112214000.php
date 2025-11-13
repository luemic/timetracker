<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add name column to ticket_system table.
 */
final class Version20251112214000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add non-null name column (varchar 255) to ticket_system.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('ticket_system')) {
            $table = $schema->getTable('ticket_system');
            if (!$table->hasColumn('name')) {
                // Add with default empty string to satisfy NOT NULL
                $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true, 'default' => '']);
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('ticket_system')) {
            $table = $schema->getTable('ticket_system');
            if ($table->hasColumn('name')) {
                $table->dropColumn('name');
            }
        }
    }
}
