<?php

namespace SolutionForest\WorkflowEngine\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Tests\Support\InMemoryStorage;

class TestCase extends PHPUnitTestCase
{
    protected WorkflowEngine $engine;

    protected InMemoryStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new InMemoryStorage;
        $this->engine = new WorkflowEngine($this->storage);
    }

    protected function createSampleWorkflowDefinition(): array
    {
        return [
            'name' => 'Test Workflow',
            'version' => '1.0',
            'steps' => [
                [
                    'id' => 'step1',
                    'name' => 'First Step',
                    'action' => 'log',
                    'parameters' => [
                        'message' => 'Starting workflow',
                        'level' => 'info',
                    ],
                ],
                [
                    'id' => 'step2',
                    'name' => 'Second Step',
                    'action' => 'log',
                    'parameters' => [
                        'message' => 'Workflow in progress',
                        'level' => 'info',
                    ],
                ],
            ],
        ];
    }
}
