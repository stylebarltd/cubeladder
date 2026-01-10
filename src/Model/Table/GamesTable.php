<?php
declare(strict_types=1);

namespace App\Model\Table;

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
}
