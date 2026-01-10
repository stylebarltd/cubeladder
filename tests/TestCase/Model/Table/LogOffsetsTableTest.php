<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\LogOffsetsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\LogOffsetsTable Test Case
 */
class LogOffsetsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\LogOffsetsTable
     */
    protected $LogOffsets;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.LogOffsets',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('LogOffsets') ? [] : ['className' => LogOffsetsTable::class];
        $this->LogOffsets = $this->getTableLocator()->get('LogOffsets', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->LogOffsets);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\LogOffsetsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\LogOffsetsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
