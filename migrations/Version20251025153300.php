<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add user relation to time_booking (each booking belongs to a user).
 * This migration is safe for existing data:
 *  - Adds time_booking.user_id as NULLable first
 *  - Ensures a fallback user exists (system@local)
 *  - Backfills existing rows
 *  - Adds index and FK, then makes the column NOT NULL
 */
final class Version20251025153300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_id to time_booking with FK to users (CASCADE), backfill existing rows, then set NOT NULL.';
    }

    public function up(Schema $schema): void
    {
        if (!($schema->hasTable('time_booking') && $schema->hasTable('users'))) {
            return; // nothing to do
        }

        // 1) Ensure the column exists and is nullable initially
        $tb = $schema->getTable('time_booking');
        if (!$tb->hasColumn('user_id')) {
            $tb->addColumn('user_id', 'integer', ['notnull' => false]);
        }
        if (!$tb->hasIndex('idx_tb_user_id')) {
            $tb->addIndex(['user_id'], 'idx_tb_user_id');
        }

        // 2) Create or find a fallback user and backfill existing rows
        $connection = $this->connection;
        $fallbackEmail = 'system@local';
        $fallbackId = $connection->fetchOne("SELECT id FROM users WHERE email = ? LIMIT 1", [$fallbackEmail]);
        if (!$fallbackId) {
            // Insert minimal user (password empty string; adjust later via CLI if needed)
            $connection->executeStatement(
                "INSERT INTO users (email, roles, password) VALUES (?, '[]', '')",
                [$fallbackEmail]
            );
            $fallbackId = (int) $connection->lastInsertId();
        }

        // Set user_id for all existing time_booking rows where it's NULL or 0 (in case column was created NOT NULL before)
        $connection->executeStatement("UPDATE time_booking SET user_id = ? WHERE user_id IS NULL OR user_id = 0", [$fallbackId]);

        // 3) Add FK if missing
        $schemaManager = $connection->createSchemaManager();
        $current = $schemaManager->introspectSchema();
        $tbNow = $current->getTable('time_booking');
        if (!$tbNow->hasForeignKey('fk_tb_user')) {
            $this->addSql('ALTER TABLE time_booking ADD CONSTRAINT fk_tb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE NO ACTION');
        }

        // 4) Make the column NOT NULL
        $this->addSql('ALTER TABLE time_booking MODIFY user_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('time_booking')) {
            $tb = $schema->getTable('time_booking');
            if ($tb->hasForeignKey('fk_tb_user')) {
                $tb->removeForeignKey('fk_tb_user');
            }
            if ($tb->hasIndex('idx_tb_user_id')) {
                $tb->dropIndex('idx_tb_user_id');
            }
            if ($tb->hasColumn('user_id')) {
                $tb->dropColumn('user_id');
            }
        }
    }
}
