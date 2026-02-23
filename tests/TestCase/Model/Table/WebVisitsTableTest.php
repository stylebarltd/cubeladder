<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\WebVisitsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\WebVisitsTable Test Case
 */
class WebVisitsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\WebVisitsTable
     */
    protected $WebVisits;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.WebVisits',
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
        $config = $this->getTableLocator()->exists('WebVisits') ? [] : ['className' => WebVisitsTable::class];
        $this->WebVisits = $this->getTableLocator()->get('WebVisits', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->WebVisits);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\WebVisitsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\WebVisitsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
