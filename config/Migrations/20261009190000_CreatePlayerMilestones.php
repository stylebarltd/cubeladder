<?php
use Migrations\BaseMigration;

class CreatePlayerMilestones extends BaseMigration
{
    /**
     * Player milestones (bin/cake CalculateMilestones, rebuilt after every
     * import): one row per reached tier with the game it was reached in,
     * and each player's current totals for the progress bars.
     */
    public function change(): void
    {
        $this->table('player_milestones', ['id' => false, 'primary_key' => ['player_id', 'milestone', 'tier']])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('milestone', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('tier', 'integer', ['null' => false])
            ->addColumn('threshold', 'integer', ['null' => false])
            ->addColumn('reached_at', 'datetime', ['null' => false])
            ->addColumn('game_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('detail', 'string', ['limit' => 64, 'null' => true])
            ->addIndex(['reached_at'])
            ->create();

        $this->table('player_milestone_progress', ['id' => false, 'primary_key' => ['player_id']])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('progress', 'text', ['null' => false])
            ->addColumn('calculated_at', 'datetime', ['null' => false])
            ->create();
    }
}
