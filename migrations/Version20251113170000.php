<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add budget fields to project: budget_type, budget, hourly_rate
 */
final class Version20251113170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns budget_type (string, default none), budget (decimal 10,2 nullable), hourly_rate (decimal 10,2 nullable) to project table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('project')) {
            $t = $schema->getTable('project');
            if (!$t->hasColumn('budget_type')) {
                $t->addColumn('budget_type', 'string', ['length' => 32, 'default' => 'none', 'notnull' => true]);
            }
            if (!$t->hasColumn('budget')) {
                $t->addColumn('budget', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
            }
            if (!$t->hasColumn('hourly_rate')) {
                $t->addColumn('hourly_rate', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('project')) {
            $t = $schema->getTable('project');
            if ($t->hasColumn('hourly_rate')) {
                $t->dropColumn('hourly_rate');
            }
            if ($t->hasColumn('budget')) {
                $t->dropColumn('budget');
            }
            if ($t->hasColumn('budget_type')) {
                $t->dropColumn('budget_type');
            }
        }
    }
}
