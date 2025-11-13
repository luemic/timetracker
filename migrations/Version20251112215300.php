<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add nullable worklog_id column to time_booking to store external ticket worklog IDs.
 */
final class Version20251112215300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable worklog_id (varchar 128) to time_booking table.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('time_booking')) {
            $tb = $schema->getTable('time_booking');
            if (!$tb->hasColumn('worklog_id')) {
                $tb->addColumn('worklog_id', 'string', ['length' => 128, 'notnull' => false]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('time_booking')) {
            $tb = $schema->getTable('time_booking');
            if ($tb->hasColumn('worklog_id')) {
                $tb->dropColumn('worklog_id');
            }
        }
    }
}
