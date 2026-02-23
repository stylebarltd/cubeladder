<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * WebVisits Model
 *
 * @property \App\Model\Table\PlayersTable&\Cake\ORM\Association\BelongsTo $Players
 *
 * @method \App\Model\Entity\WebVisit newEmptyEntity()
 * @method \App\Model\Entity\WebVisit newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\WebVisit> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\WebVisit get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\WebVisit findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\WebVisit patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\WebVisit> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\WebVisit|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\WebVisit saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\WebVisit>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WebVisit>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WebVisit>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WebVisit> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WebVisit>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WebVisit>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\WebVisit>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\WebVisit> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class WebVisitsTable extends Table
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

        $this->setTable('web_visits');
        $this->setDisplayField('ip_address');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Players', [
            'foreignKey' => 'player_id',
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
            ->scalar('ip_address')
            ->maxLength('ip_address', 45)
            ->requirePresence('ip_address', 'create')
            ->notEmptyString('ip_address');

        $validator
            ->scalar('country_iso')
            ->maxLength('country_iso', 2)
            ->allowEmptyString('country_iso');

        $validator
            ->scalar('user_agent')
            ->allowEmptyString('user_agent');

        $validator
            ->boolean('is_bot')
            ->notEmptyString('is_bot');

        $validator
            ->scalar('path')
            ->maxLength('path', 255)
            ->requirePresence('path', 'create')
            ->notEmptyString('path');

        $validator
            ->scalar('method')
            ->maxLength('method', 10)
            ->requirePresence('method', 'create')
            ->notEmptyString('method');

        $validator
            ->scalar('referer')
            ->allowEmptyString('referer');

        $validator
            ->uuid('player_id')
            ->allowEmptyString('player_id');

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
        $rules->add($rules->existsIn(['player_id'], 'Players'), ['errorField' => 'player_id']);

        return $rules;
    }
}
