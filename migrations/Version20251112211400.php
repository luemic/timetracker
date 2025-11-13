<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create table ticket_system and add optional 1:1 relation from project (ticket_system_id, SET NULL on delete).
 */
final class Version20251112211400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ticket_system table and add nullable project.ticket_system_id with FK (SET NULL) and unique index (1:1).';
    }

    public function up(Schema $schema): void
    {
        // Create ticket_system table if it does not exist
        if (!$schema->hasTable('ticket_system')) {
            $ts = $schema->createTable('ticket_system');
            $ts->addColumn('id', 'integer', ['autoincrement' => true]);
            $ts->addColumn('type', 'string', ['length' => 32]);
            $ts->addColumn('username', 'string', ['length' => 255]);
            $ts->addColumn('secret', 'string', ['length' => 4096]);
            $ts->setPrimaryKey(['id']);
        }

        // Ensure project table has ticket_system_id column (nullable)
        if ($schema->hasTable('project')) {
            $project = $schema->getTable('project');
            if (!$project->hasColumn('ticket_system_id')) {
                $project->addColumn('ticket_system_id', 'integer', ['notnull' => false]);
            }
            // Add unique index to enforce 1:1 when set
            if (!$project->hasIndex('uniq_project_ticket_system_id')) {
                $project->addUniqueIndex(['ticket_system_id'], 'uniq_project_ticket_system_id');
            }
        }

        // Add foreign key constraints where missing
        if ($schema->hasTable('project') && $schema->hasTable('ticket_system')) {
            $project = $schema->getTable('project');
            if (!$project->hasForeignKey('fk_project_ticket_system')) {
                $project->addForeignKeyConstraint('ticket_system', ['ticket_system_id'], ['id'], ['onDelete' => 'SET NULL', 'onUpdate' => 'NO ACTION'], 'fk_project_ticket_system');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Drop FK and column from project
        if ($schema->hasTable('project')) {
            $project = $schema->getTable('project');
            if ($project->hasForeignKey('fk_project_ticket_system')) {
                $project->removeForeignKey('fk_project_ticket_system');
            }
            if ($project->hasIndex('uniq_project_ticket_system_id')) {
                $project->dropIndex('uniq_project_ticket_system_id');
            }
            if ($project->hasColumn('ticket_system_id')) {
                $project->dropColumn('ticket_system_id');
            }
        }

        // Drop ticket_system table
        if ($schema->hasTable('ticket_system')) {
            $schema->dropTable('ticket_system');
        }
    }
}
