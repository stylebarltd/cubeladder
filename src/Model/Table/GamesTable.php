<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Game;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Games Model
 *
 * @property \App\Model\Table\MapsTable&\Cake\ORM\Association\BelongsTo $Maps
 * @property \App\Model\Table\EventsTable&\Cake\ORM\Association\HasMany $Events
 * @property \App\Model\Table\PlayerStatsPerGameTable&\Cake\ORM\Association\HasMany $PlayerStatsPerGame
 *
 * @method \App\Model\Entity\Game newEmptyEntity()
 * @method \App\Model\Entity\Game newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Game> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Game get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Game findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Game patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Game> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Game|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Game saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Game>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Game>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Game>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Game> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Game>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Game>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Game>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Game> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class GamesTable extends Table
{
    public const TEAM_MODES = ['ctf', 'tdm', 'htf', 'tktf', 'tosok', 'tlss', 'tsurv', 'tpf'];
    public const FLAG_MODES = ['ctf', 'htf', 'tktf'];
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('games');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Maps', [
            'foreignKey' => 'map_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Events', [
            'foreignKey' => 'game_id',
        ]);
        $this->hasMany('PlayerStatsPerGame', [
            'foreignKey' => 'game_id',
        ]);

        $this->belongsToMany('Players', [
            'through' => 'PlayerStatsPerGame',
            'foreignKey' => 'game_id',
            'targetForeignKey' => 'player_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('unique_key')
            ->maxLength('unique_key', 255)
            ->allowEmptyString('unique_key')
            ->add('unique_key', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('mode')
            ->maxLength('mode', 64)
            ->allowEmptyString('mode');

        $validator
            ->uuid('map_id')
            ->notEmptyString('map_id');

        $validator
            ->dateTime('started_at')
            ->allowEmptyDateTime('started_at');

        $validator
            ->dateTime('ended_at')
            ->allowEmptyDateTime('ended_at');

        $validator
            ->integer('duration_minutes')
            ->allowEmptyString('duration_minutes');

        $validator
            ->scalar('server_name')
            ->maxLength('server_name', 64)
            ->allowEmptyString('server_name');

        $validator
            ->scalar('raw')
            ->maxLength('raw', 4294967295)
            ->allowEmptyString('raw');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['unique_key'], ['allowMultipleNulls' => true]), ['errorField' => 'unique_key']);
        $rules->add($rules->existsIn(['map_id'], 'Maps'), ['errorField' => 'map_id']);

        return $rules;
    }

    /**
     * Scoreboard of a game (needs player_stats_per_game with player
     * contained): tracked players by points, and for team games with known
     * final scores the CLA / RVSF split like the in-game scoreboard.
     *
     * @return array{rows: array, teams: ?array, winner: ?string, flagMode: bool, unassigned: array, rated: bool}
     */
    public function scoreboard(Game $game): array
    {
        $stats = collection($game->player_stats_per_game ?? [])
            ->filter(fn($s) => $s->player && (int)$s->player->track === 1)
            ->sortBy('total_score', SORT_DESC)
            ->toList();

        // CTF rating of each player in this game (bin/cake CalculateRatings)
        $ratings = [];
        if ($game->mode === 'ctf') {
            $ratings = $this->getConnection()
                ->execute('SELECT player_id, rating FROM player_game_ratings WHERE game_id = ?', [$game->id])
                ->fetchAll('assoc');
            $ratings = array_column($ratings, 'rating', 'player_id');
        }

        $rows = [];
        foreach ($stats as $i => $stat) {
            $rows[] = [
                'player' => $stat->player,
                'team' => $stat->team,
                'is_mvp' => $i === 0,
                'flags' => (int)$stat->scored_with_the_flag,
                'kills' => (int)$stat->kills,
                'deaths' => (int)$stat->deaths,
                'kd_ratio' => (float)$stat->kd_ratio,
                'score' => (int)$stat->total_score,
                'minutes' => $stat->minutes_played,
                'rating' => isset($ratings[$stat->player_id]) ? (float)$ratings[$stat->player_id] : null,
            ];
        }

        $flagMode = in_array($game->mode, self::FLAG_MODES, true);
        $board = ['rows' => $rows, 'teams' => null, 'winner' => null, 'flagMode' => $flagMode, 'unassigned' => [], 'rated' => $ratings !== []];

        $teamScores = $game->team_scores ? json_decode($game->team_scores, true) : null;
        if (!$teamScores || !in_array($game->mode, self::TEAM_MODES, true)) {
            return $board;
        }

        foreach (['CLA', 'RVSF'] as $team) {
            $teamRows = array_values(array_filter($rows, fn($r) => $r['team'] === $team));
            $board['teams'][$team] = [
                'score' => ($teamScores[$team] ?? []) + ['players' => 0, 'frags' => 0, 'flags' => null],
                'deaths' => array_sum(array_column($teamRows, 'deaths')),
                'rows' => $teamRows,
            ];
        }
        $points = fn($t) => [$flagMode ? (int)$t['score']['flags'] : 0, (int)$t['score']['frags']];
        $cmp = $points($board['teams']['CLA']) <=> $points($board['teams']['RVSF']);
        $board['winner'] = $cmp > 0 ? 'CLA' : ($cmp < 0 ? 'RVSF' : null);
        $board['unassigned'] = array_values(array_filter($rows, fn($r) => !in_array($r['team'], ['CLA', 'RVSF'], true)));

        return $board;
    }
}
