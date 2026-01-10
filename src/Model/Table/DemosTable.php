<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Demos Model
 *
 * @property \App\Model\Table\GamesTable&\Cake\ORM\Association\BelongsTo $Games
 *
 * @method \App\Model\Entity\Demo newEmptyEntity()
 * @method \App\Model\Entity\Demo newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Demo> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Demo get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Demo findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Demo patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Demo> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Demo|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Demo saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Demo>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Demo>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Demo>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Demo> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Demo>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Demo>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Demo>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Demo> deleteManyOrFail(iterable $entities, array $options = [])
 */
class DemosTable extends Table
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

        $this->setTable('demos');
        $this->setDisplayField('filename');
        $this->setPrimaryKey('id');

        $this->belongsTo('Games', [
            'foreignKey' => 'game_id',
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
            ->integer('game_id')
            ->allowEmptyString('game_id');

        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->requirePresence('filename', 'create')
            ->notEmptyString('filename');

        $validator
            ->integer('sequence')
            ->allowEmptyString('sequence');

        $validator
            ->dateTime('created_at')
            ->allowEmptyDateTime('created_at');

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
        $rules->add($rules->existsIn(['game_id'], 'Games'), ['errorField' => 'game_id']);

        return $rules;
    }
}
