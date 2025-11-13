<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add nullable url column to ticket_system table.
 */
final class Version20251112213300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ticket_system.url (nullable, length 2048).';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('ticket_system')) {
            $ts = $schema->getTable('ticket_system');
            if (!$ts->hasColumn('url')) {
                $ts->addColumn('url', 'string', ['length' => 2048, 'notnull' => false]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('ticket_system')) {
            $ts = $schema->getTable('ticket_system');
            if ($ts->hasColumn('url')) {
                $ts->dropColumn('url');
            }
        }
    }
}
