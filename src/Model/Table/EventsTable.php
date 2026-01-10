<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Events Model
 *
 * @property \App\Model\Table\GamesTable&\Cake\ORM\Association\BelongsTo $Games
 * @property \App\Model\Table\PlayersTable&\Cake\ORM\Association\BelongsTo $Actors
 * @property \App\Model\Table\PlayersTable&\Cake\ORM\Association\BelongsTo $Targets
 *
 * @method \App\Model\Entity\Event newEmptyEntity()
 * @method \App\Model\Entity\Event newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Event> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Event get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Event findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Event patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Event> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Event|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Event saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Event>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Event> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Event>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Event>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Event> deleteManyOrFail(iterable $entities, array $options = [])
 */
class EventsTable extends Table
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

        $this->setTable('events');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Games', [
            'foreignKey' => 'game_id',
        ]);
        $this->belongsTo('Actors', [
            'foreignKey' => 'actor_id',
            'className' => 'Players',
        ]);
        $this->belongsTo('Targets', [
            'foreignKey' => 'target_id',
            'className' => 'Players',
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
            ->allowEmptyString('game_id');

        $validator
            ->dateTime('event_time')
            ->allowEmptyDateTime('event_time');

        $validator
            ->scalar('event_hash')
            ->maxLength('event_hash', 32)
            ->requirePresence('event_hash', 'create')
            ->notEmptyString('event_hash')
            ->add('event_hash', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('type')
            ->maxLength('type', 32)
            ->allowEmptyString('type');

        $validator
            ->scalar('raw')
            ->allowEmptyString('raw');

        $validator
            ->uuid('actor_id')
            ->allowEmptyString('actor_id');

        $validator
            ->uuid('target_id')
            ->allowEmptyString('target_id');

        $validator
            ->scalar('details')
            ->maxLength('details', 4294967295)
            ->allowEmptyString('details');

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
        $rules->add($rules->isUnique(['event_hash']), ['errorField' => 'event_hash']);
        $rules->add($rules->existsIn(['game_id'], 'Games'), ['errorField' => 'game_id']);
        $rules->add($rules->existsIn(['actor_id'], 'Actors'), ['errorField' => 'actor_id']);
        $rules->add($rules->existsIn(['target_id'], 'Targets'), ['errorField' => 'target_id']);

        return $rules;
    }
}
