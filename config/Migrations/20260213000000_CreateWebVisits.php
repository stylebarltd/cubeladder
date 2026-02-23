<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateWebVisits extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('web_visits', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
        ]);



        $table
            ->addColumn('id', 'char', [
                'limit' => 36,
                'null' => false,
                'collation' => 'utf8mb4_general_ci',
            ])

            ->addColumn('ip_address', 'string', [
                'limit' => 45,
                'null' => false,
            ])

            ->addColumn('user_agent', 'text', [
                'null' => true,
            ])

            ->addColumn('is_bot', 'boolean', [
                'default' => false,
                'null' => false,
            ])

            ->addColumn('path', 'string', [
                'limit' => 255,
                'null' => false,
            ])

            ->addColumn('method', 'string', [
                'limit' => 10,
                'null' => false,
            ])

            ->addColumn('referer', 'text', [
                'null' => true,
            ])

            ->addColumn('player_id', 'char', [
                'limit' => 36,
                'null' => true,
                'collation' => 'utf8mb4_general_ci',
            ])

            ->addColumn('created', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
            ])

            ->addIndex(['ip_address'])
            ->addIndex(['created'])
            ->addIndex(['player_id'])

            ->addForeignKey(
                'player_id',
                'players',
                'id',
                [
                    'delete' => 'SET_NULL',
                    'update' => 'NO_ACTION'
                ]
            )

            ->create();

    }
}
