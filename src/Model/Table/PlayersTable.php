<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Players Model
 *
 * @property \App\Model\Table\AchievementsTable&\Cake\ORM\Association\HasMany $Achievements
 * @property \App\Model\Table\PlayerAliasesTable&\Cake\ORM\Association\HasMany $PlayerAliases
 * @property \App\Model\Table\PlayerStatsPerGameTable&\Cake\ORM\Association\HasMany $PlayerStatsPerGame
 *
 * @method \App\Model\Entity\Player newEmptyEntity()
 * @method \App\Model\Entity\Player newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Player> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Player get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Player findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Player patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Player> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Player|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Player saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Player>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Player>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Player>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Player> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Player>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Player>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Player>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Player> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PlayersTable extends Table
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

        $this->setTable('players');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Achievements', [
            'foreignKey' => 'player_id',
        ]);
        $this->hasMany('PlayerAliases', [
            'foreignKey' => 'player_id',
        ]);
        $this->hasMany('PlayerStatsPerGame', [
            'foreignKey' => 'player_id',
        ]);
        $this->addBehavior('GeoIp', [
            'dbPath' => CONFIG . 'GeoLite2-City.mmdb'
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
            ->maxLength('name', 64)
            ->allowEmptyString('name');

        $validator
            ->scalar('picture')
            ->maxLength('picture', 255)
            ->allowEmptyString('picture');

        $validator
            ->scalar('pubkey')
            ->maxLength('pubkey', 128)
            ->allowEmptyString('pubkey')
            ->add('pubkey', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->dateTime('first_seen')
            ->allowEmptyDateTime('first_seen');

        $validator
            ->dateTime('last_seen')
            ->allowEmptyDateTime('last_seen');

        $validator
            ->scalar('ip')
            ->maxLength('ip', 45)
            ->allowEmptyString('ip');

        $validator
            ->scalar('country')
            ->maxLength('country', 2)
            ->allowEmptyString('country');

        $validator
            ->boolean('track')
            ->allowEmptyString('track');

        $validator
            ->boolean('locked')
            ->allowEmptyString('locked');

        $validator
            ->integer('likes')
            ->allowEmptyString('likes');

        $validator
            ->integer('views')
            ->allowEmptyString('views');

        $validator
            ->decimal('latitude')
            ->allowEmptyString('latitude');

        $validator
            ->decimal('longitude')
            ->allowEmptyString('longitude');

        $validator
            ->integer('geo_accuracy')
            ->allowEmptyString('geo_accuracy');

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
        $rules->add($rules->isUnique(['pubkey'], ['allowMultipleNulls' => true]), ['errorField' => 'pubkey']);

        return $rules;
    }
}
