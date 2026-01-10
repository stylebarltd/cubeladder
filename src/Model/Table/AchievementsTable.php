<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Achievements Model
 *
 * @property \App\Model\Table\PlayersTable&\Cake\ORM\Association\BelongsTo $Players
 * @property \App\Model\Table\MapsTable&\Cake\ORM\Association\BelongsTo $Maps
 *
 * @method \App\Model\Entity\Achievement newEmptyEntity()
 * @method \App\Model\Entity\Achievement newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Achievement> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Achievement get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Achievement findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Achievement patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Achievement> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Achievement|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Achievement saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Achievement>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Achievement>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Achievement>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Achievement> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Achievement>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Achievement>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Achievement>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Achievement> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class AchievementsTable extends Table
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

        $this->setTable('achievements');
        $this->setDisplayField('event_type');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Players', [
            'foreignKey' => 'player_id',
            'joinType' => 'INNER',
        ]);
        //have to use left otherwise we get only entries which have map_id not null
        $this->belongsTo('Maps', [
            'foreignKey' => 'map_id',
            'joinType' => 'LEFT',
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
            ->date('week_start')
            ->requirePresence('week_start', 'create')
            ->notEmptyDate('week_start');

        $validator
            ->date('week_end')
            ->requirePresence('week_end', 'create')
            ->notEmptyDate('week_end');

        $validator
            ->uuid('player_id')
            ->notEmptyString('player_id');

        $validator
            ->uuid('map_id')
            ->notEmptyString('map_id');

        $validator
            ->scalar('event_type')
            ->maxLength('event_type', 50)
            ->requirePresence('event_type', 'create')
            ->notEmptyString('event_type');

        $validator
            ->decimal('count')
            ->requirePresence('count', 'create')
            ->notEmptyString('count');

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
        $rules->add($rules->isUnique(['week_start', 'event_type', 'map_id']), ['errorField' => 'week_start']);
        $rules->add($rules->existsIn(['player_id'], 'Players'), ['errorField' => 'player_id']);
        $rules->add($rules->existsIn(['map_id'], 'Maps'), ['errorField' => 'map_id']);

        return $rules;
    }
}
