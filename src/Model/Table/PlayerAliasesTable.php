<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PlayerAliases Model
 *
 * @property \App\Model\Table\PlayersTable&\Cake\ORM\Association\BelongsTo $Players
 *
 * @method \App\Model\Entity\PlayerAlias newEmptyEntity()
 * @method \App\Model\Entity\PlayerAlias newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\PlayerAlias> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PlayerAlias get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\PlayerAlias findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\PlayerAlias patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\PlayerAlias> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PlayerAlias|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\PlayerAlias saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerAlias>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerAlias>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerAlias>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerAlias> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerAlias>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerAlias>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PlayerAlias>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PlayerAlias> deleteManyOrFail(iterable $entities, array $options = [])
 */
class PlayerAliasesTable extends Table
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

        $this->setTable('player_aliases');
        $this->setDisplayField('alias');
        $this->setPrimaryKey('id');

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
            ->uuid('player_id')
            ->notEmptyString('player_id');

        $validator
            ->scalar('alias')
            ->maxLength('alias', 64)
            ->requirePresence('alias', 'create')
            ->notEmptyString('alias');

        $validator
            ->dateTime('first_seen')
            ->requirePresence('first_seen', 'create')
            ->notEmptyDateTime('first_seen');

        $validator
            ->dateTime('last_seen')
            ->requirePresence('last_seen', 'create')
            ->notEmptyDateTime('last_seen');

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
        $rules->add($rules->isUnique(['player_id', 'alias']), ['errorField' => 'player_id']);
        $rules->add($rules->existsIn(['player_id'], 'Players'), ['errorField' => 'player_id']);

        return $rules;
    }
}
