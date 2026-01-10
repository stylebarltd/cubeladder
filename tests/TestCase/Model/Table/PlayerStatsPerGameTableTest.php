<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PlayerStatsPerGameTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\PlayerStatsPerGameTable Test Case
 */
class PlayerStatsPerGameTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\PlayerStatsPerGameTable
     */
    protected $PlayerStatsPerGame;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.PlayerStatsPerGame',
        'app.Games',
        'app.Players',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('PlayerStatsPerGame') ? [] : ['className' => PlayerStatsPerGameTable::class];
        $this->PlayerStatsPerGame = $this->getTableLocator()->get('PlayerStatsPerGame', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->PlayerStatsPerGame);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\PlayerStatsPerGameTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\PlayerStatsPerGameTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
