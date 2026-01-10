<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Maps Model
 *
 * @property \App\Model\Table\AchievementsTable&\Cake\ORM\Association\HasMany $Achievements
 * @property \App\Model\Table\GamesTable&\Cake\ORM\Association\HasMany $Games
 *
 * @method \App\Model\Entity\Map newEmptyEntity()
 * @method \App\Model\Entity\Map newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Map> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Map get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Map findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Map patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Map> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Map|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Map saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Map>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Map>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Map>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Map> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Map>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Map>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Map>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Map> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class MapsTable extends Table
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

        $this->setTable('maps');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Achievements', [
            'foreignKey' => 'map_id',
        ]);
        $this->hasMany('Games', [
            'foreignKey' => 'map_id',
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
            ->scalar('name')
            ->maxLength('name', 128)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('times_played')
            ->allowEmptyString('times_played');

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
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }
}
