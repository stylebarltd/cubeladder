<?php
use Migrations\BaseMigration;

class CreateRadarRounds extends BaseMigration
{
    /**
     * Radar (/radar, Radar service): one row per round of the map quiz. The
     * questions (map, piece centre, choices, when each was shown, the answer)
     * are drawn at the start and kept in `questions` (JSON); the page never
     * learns the answer before it has answered. Guests play too (player_id
     * null), only recognised players are on the board.
     */
    public function change(): void
    {
        $this->table('radar_rounds', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('token', 'char', ['limit' => 32])
            ->addColumn('player_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime')
            ->addColumn('finished_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('score', 'integer', ['null' => true, 'default' => null])
            ->addColumn('correct', 'integer', ['null' => true, 'default' => null])
            ->addColumn('avg_ms', 'integer', ['null' => true, 'default' => null])
            ->addColumn('questions', 'text')
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['finished_at', 'score'])
            ->addIndex(['player_id'])
            ->create();
    }
}
