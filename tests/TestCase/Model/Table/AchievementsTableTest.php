<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AchievementsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AchievementsTable Test Case
 */
class AchievementsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AchievementsTable
     */
    protected $Achievements;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Achievements',
        'app.Players',
        'app.Maps',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Achievements') ? [] : ['className' => AchievementsTable::class];
        $this->Achievements = $this->getTableLocator()->get('Achievements', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Achievements);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\AchievementsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\AchievementsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
