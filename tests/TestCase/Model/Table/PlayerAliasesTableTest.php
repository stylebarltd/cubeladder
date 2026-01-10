<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PlayerAliasesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\PlayerAliasesTable Test Case
 */
class PlayerAliasesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\PlayerAliasesTable
     */
    protected $PlayerAliases;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.PlayerAliases',
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
        $config = $this->getTableLocator()->exists('PlayerAliases') ? [] : ['className' => PlayerAliasesTable::class];
        $this->PlayerAliases = $this->getTableLocator()->get('PlayerAliases', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->PlayerAliases);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\PlayerAliasesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\PlayerAliasesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
