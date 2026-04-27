<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PlayerStatsPerGame Model
 *
 * @property \App\Model\Table\GamesTable&\Cake\ORM\Association\BelongsTo $Games
 * @property \App\Model\Table\PlayersTable&\Cake\ORM\Association\BelongsTo $Players
 *
 * @method \App\Model\Entity\PlayerStatsPerGame newEmptyEntity()
 * @method \App\Model\Entity\PlayerStatsPerGame newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\PlayerStatsPerGame> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PlayerStatsPerGame get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\PlayerStatsPerGame findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\PlayerStatsPerGame patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\PlayerStatsPerGame> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PlayerStatsPerGame|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\PlayerStatsPerGame saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerStatsPerGame>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerStatsPerGame>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerStatsPerGame>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerStatsPerGame> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerStatsPerGame>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerStatsPerGame>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerStatsPerGame>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerStatsPerGame> deleteManyOrFail(iterable $entities, array $options = [])
 */
class PlayerStatsPerGameTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('player_stats_per_game');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Games', [
            'foreignKey' => 'game_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Players', [
            'foreignKey' => 'player_id',
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
            ->uuid('game_id')
            ->notEmptyString('game_id');

        $validator
            ->uuid('player_id')
            ->notEmptyString('player_id');

        $validator
            ->integer('kills')
            ->allowEmptyString('kills');

        $validator
            ->integer('teamkills')
            ->allowEmptyString('teamkills');

        $validator
            ->integer('deaths')
            ->allowEmptyString('deaths');

        $validator
            ->integer('headshot')
            ->allowEmptyString('headshot');

        $validator
            ->integer('busted')
            ->allowEmptyString('busted');

        $validator
            ->integer('shredded')
            ->allowEmptyString('shredded');

        $validator
            ->integer('peppered')
            ->allowEmptyString('peppered');

        $validator
            ->integer('sprayed')
            ->allowEmptyString('sprayed');

        $validator
            ->integer('punctured')
            ->allowEmptyString('punctured');

        $validator
            ->integer('splattered')
            ->allowEmptyString('splattered');

        $validator
            ->integer('slashed')
            ->allowEmptyString('slashed');

        $validator
            ->integer('gibbed')
            ->allowEmptyString('gibbed');

        $validator
            ->integer('suicided')
            ->allowEmptyString('suicided');

        $validator
            ->integer('picked_off')
            ->allowEmptyString('picked_off');

        $validator
            ->integer('stole_the_flag')
            ->allowEmptyString('stole_the_flag');

        $validator
            ->integer('lost_the_flag')
            ->allowEmptyString('lost_the_flag');

        $validator
            ->integer('returned_the_flag')
            ->allowEmptyString('returned_the_flag');

        $validator
            ->integer('scored_with_the_flag')
            ->allowEmptyString('scored_with_the_flag');

        $validator
            ->numeric('kd_ratio')
            ->allowEmptyString('kd_ratio');

        $validator
            ->numeric('total_score')
            ->allowEmptyString('total_score');

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
        $rules->add($rules->isUnique(['game_id', 'player_id']), ['errorField' => 'game_id']);
        $rules->add($rules->existsIn(['game_id'], 'Games'), ['errorField' => 'game_id']);
        $rules->add($rules->existsIn(['player_id'], 'Players'), ['errorField' => 'player_id']);

        return $rules;
    }
}
