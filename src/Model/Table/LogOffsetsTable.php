<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * LogOffsets Model
 *
 * @method \App\Model\Entity\LogOffset newEmptyEntity()
 * @method \App\Model\Entity\LogOffset newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\LogOffset> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\LogOffset get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\LogOffset findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\LogOffset patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\LogOffset> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\LogOffset|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\LogOffset saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\LogOffset>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LogOffset>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\LogOffset>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LogOffset> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\LogOffset>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LogOffset>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\LogOffset>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\LogOffset> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class LogOffsetsTable extends Table
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

        $this->setTable('log_offsets');
        $this->setDisplayField('server_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
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
            ->scalar('server_name')
            ->maxLength('server_name', 255)
            ->requirePresence('server_name', 'create')
            ->notEmptyString('server_name');

        $validator
            ->scalar('log_path')
            ->maxLength('log_path', 1024)
            ->requirePresence('log_path', 'create')
            ->notEmptyString('log_path');

        $validator
            ->notEmptyString('last_offset');

        $validator
            ->notEmptyString('inode');

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
        $rules->add($rules->isUnique(['server_name', 'log_path']), ['errorField' => 'server_name']);

        return $rules;
    }
}
