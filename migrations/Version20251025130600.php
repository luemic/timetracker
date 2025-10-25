<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema for core entities.
 */
final class Version20251025130600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables: users, customer, project, activity, project_activity, time_booking with relations and constraints.';
    }

    public function up(Schema $schema): void
    {
        // users
        if (!$schema->hasTable('users')) {
            $users = $schema->createTable('users');
            $users->addColumn('id', 'integer', ['autoincrement' => true]);
            $users->addColumn('email', 'string', ['length' => 180]);
            $users->addColumn('roles', 'json');
            $users->addColumn('password', 'string', ['length' => 255]);
            $users->setPrimaryKey(['id']);
            $users->addUniqueIndex(['email'], 'uniq_users_email');
        }

        // customer
        if (!$schema->hasTable('customer')) {
            $customer = $schema->createTable('customer');
            $customer->addColumn('id', 'integer', ['autoincrement' => true]);
            $customer->addColumn('name', 'string', ['length' => 255]);
            $customer->setPrimaryKey(['id']);
        }

        // activity
        if (!$schema->hasTable('activity')) {
            $activity = $schema->createTable('activity');
            $activity->addColumn('id', 'integer', ['autoincrement' => true]);
            $activity->addColumn('name', 'string', ['length' => 255]);
            $activity->setPrimaryKey(['id']);
        }

        // project
        if (!$schema->hasTable('project')) {
            $project = $schema->createTable('project');
            $project->addColumn('id', 'integer', ['autoincrement' => true]);
            $project->addColumn('name', 'string', ['length' => 255]);
            $project->addColumn('customer_id', 'integer', ['notnull' => true]);
            $project->addColumn('external_ticket_url', 'string', ['length' => 2048, 'notnull' => false]);
            $project->addColumn('external_ticket_login', 'string', ['length' => 255, 'notnull' => false]);
            $project->addColumn('external_ticket_credentials', 'string', ['length' => 4096, 'notnull' => false]);
            $project->setPrimaryKey(['id']);
            $project->addIndex(['customer_id'], 'idx_project_customer_id');
        }

        // project_activity
        if (!$schema->hasTable('project_activity')) {
            $pa = $schema->createTable('project_activity');
            $pa->addColumn('id', 'integer', ['autoincrement' => true]);
            $pa->addColumn('project_id', 'integer', ['notnull' => true]);
            $pa->addColumn('activity_id', 'integer', ['notnull' => true]);
            $pa->addColumn('factor', 'float');
            $pa->setPrimaryKey(['id']);
            $pa->addIndex(['project_id'], 'idx_pa_project_id');
            $pa->addIndex(['activity_id'], 'idx_pa_activity_id');
            $pa->addUniqueIndex(['project_id', 'activity_id'], 'uniq_project_activity');
        }

        // time_booking
        if (!$schema->hasTable('time_booking')) {
            $tb = $schema->createTable('time_booking');
            $tb->addColumn('id', 'integer', ['autoincrement' => true]);
            $tb->addColumn('project_id', 'integer', ['notnull' => true]);
            $tb->addColumn('activity_id', 'integer', ['notnull' => false]);
            $tb->addColumn('started_at', 'datetime_immutable');
            $tb->addColumn('ended_at', 'datetime_immutable');
            $tb->addColumn('ticket_number', 'string', ['length' => 255]);
            $tb->addColumn('duration_minutes', 'integer');
            $tb->setPrimaryKey(['id']);
            $tb->addIndex(['project_id'], 'idx_tb_project_id');
            $tb->addIndex(['activity_id'], 'idx_tb_activity_id');
        }

        // Foreign keys
        $schemaManager = $this->connection->createSchemaManager();
        $currentSchema = $schemaManager->introspectSchema();

        // project.customer_id -> customer.id (no cascade)
        if ($schema->hasTable('project') && $schema->hasTable('customer')) {
            $project = $schema->getTable('project');
            if (!$project->hasForeignKey('fk_project_customer')) {
                $project->addForeignKeyConstraint('customer', ['customer_id'], ['id'], ['onDelete' => 'NO ACTION', 'onUpdate' => 'NO ACTION'], 'fk_project_customer');
            }
        }

        // project_activity.project_id -> project.id (CASCADE)
        if ($schema->hasTable('project_activity') && $schema->hasTable('project')) {
            $pa = $schema->getTable('project_activity');
            if (!$pa->hasForeignKey('fk_pa_project')) {
                $pa->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION'], 'fk_pa_project');
            }
        }

        // project_activity.activity_id -> activity.id (CASCADE)
        if ($schema->hasTable('project_activity') && $schema->hasTable('activity')) {
            $pa = $schema->getTable('project_activity');
            if (!$pa->hasForeignKey('fk_pa_activity')) {
                $pa->addForeignKeyConstraint('activity', ['activity_id'], ['id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION'], 'fk_pa_activity');
            }
        }

        // time_booking.project_id -> project.id (CASCADE)
        if ($schema->hasTable('time_booking') && $schema->hasTable('project')) {
            $tb = $schema->getTable('time_booking');
            if (!$tb->hasForeignKey('fk_tb_project')) {
                $tb->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION'], 'fk_tb_project');
            }
        }

        // time_booking.activity_id -> activity.id (SET NULL)
        if ($schema->hasTable('time_booking') && $schema->hasTable('activity')) {
            $tb = $schema->getTable('time_booking');
            if (!$tb->hasForeignKey('fk_tb_activity')) {
                $tb->addForeignKeyConstraint('activity', ['activity_id'], ['id'], ['onDelete' => 'SET NULL', 'onUpdate' => 'NO ACTION'], 'fk_tb_activity');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Drop FKs first (safe even if absent)
        if ($schema->hasTable('project_activity')) {
            $pa = $schema->getTable('project_activity');
            if ($pa->hasForeignKey('fk_pa_project')) {
                $pa->removeForeignKey('fk_pa_project');
            }
            if ($pa->hasForeignKey('fk_pa_activity')) {
                $pa->removeForeignKey('fk_pa_activity');
            }
        }
        if ($schema->hasTable('time_booking')) {
            $tb = $schema->getTable('time_booking');
            if ($tb->hasForeignKey('fk_tb_project')) {
                $tb->removeForeignKey('fk_tb_project');
            }
            if ($tb->hasForeignKey('fk_tb_activity')) {
                $tb->removeForeignKey('fk_tb_activity');
            }
        }
        if ($schema->hasTable('project')) {
            $project = $schema->getTable('project');
            if ($project->hasForeignKey('fk_project_customer')) {
                $project->removeForeignKey('fk_project_customer');
            }
        }

        // Drop tables in reverse dependency order
        foreach (['time_booking', 'project_activity', 'project', 'activity', 'customer', 'users'] as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }
    }
}
