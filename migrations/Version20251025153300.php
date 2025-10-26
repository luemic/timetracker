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

    /**
     * On MySQL/MariaDB, mixing DDL (ALTER TABLE) and DML (UPDATE) inside a single transaction can lead to
     * visibility issues during migration execution. Disable transactional execution for this migration so that
     * DDL is applied immediately before subsequent statements run.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!($schema->hasTable('time_booking') && $schema->hasTable('users'))) {
            return; // nothing to do
        }

        // 1) Ensure the column exists and is nullable initially
        $tb = $schema->getTable('time_booking');
        if (!$tb->hasColumn('user_id')) {
            $this->addSql('ALTER TABLE time_booking ADD COLUMN IF NOT EXISTS user_id INT NULL');
        }

        // 2) Ensure fallback user exists (idempotent)
        // Use an INSERT .. SELECT .. WHERE NOT EXISTS which is portable across MySQL/MariaDB
        $this->addSql("INSERT INTO users (email, roles, password)
            SELECT 'system@local', '[]', ''
            WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'system@local')");

        // 3) Backfill existing rows with the fallback user's id (only where user_id is NULL or 0)
        // Use scalar subquery to avoid needing to know the id at PHP time.
        $this->addSql("UPDATE time_booking
            SET user_id = (SELECT id FROM users WHERE email = 'system@local' LIMIT 1)
            WHERE user_id IS NULL OR user_id = 0");

        // 4) Create index on user_id if missing
        $sm1 = $this->connection->createSchemaManager();
        $current1 = $sm1->introspectSchema();
        $tb1 = $current1->getTable('time_booking');
        if (!$tb1->hasIndex('idx_tb_user_id')) {
            // Some MySQL variants do not support IF NOT EXISTS for CREATE INDEX, so guard via introspection only
            $this->addSql('CREATE INDEX idx_tb_user_id ON time_booking (user_id)');
        }

        // 5) Add FK if missing
        $current = $sm1->introspectSchema();
        $tbNow = $current->getTable('time_booking');
        if (!$tbNow->hasForeignKey('fk_tb_user')) {
            $this->addSql('ALTER TABLE time_booking ADD CONSTRAINT fk_tb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE NO ACTION');
        }

        // 6) Make the column NOT NULL
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
