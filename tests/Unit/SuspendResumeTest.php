<?php

use SolutionForest\WorkflowEngine\Actions\DelayAction;
use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\Wait;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\WorkflowDefinition;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Events\StepCompletedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowCompletedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowFailedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowResumedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowSuspendedEvent;
use SolutionForest\WorkflowEngine\Exceptions\ConcurrentModificationException;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;
use SolutionForest\WorkflowEngine\Storage\InMemoryStorage;
use SolutionForest\WorkflowEngine\Tests\Support\SpyEventDispatcher;

/** Asks for approval: waits for the "decided" signal, or until `deadline` if configured. */
class AwaitApprovalAction implements WorkflowAction
{
    public static int $runs = 0;

    /** @param array<string, mixed> $config */
    public function __construct(public array $config = [], public mixed $logger = null) {}

    public function execute(WorkflowContext $context): ActionResult
    {
        self::$runs++;
        $deadline = $this->config['deadline'] ?? null;

        return ActionResult::suspend(
            'decided',
            is_string($deadline) ? new DateTimeImmutable($deadline) : null,
            ['asked' => true],
        );
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Await approval';
    }

    public function getDescription(): string
    {
        return 'Waits for a decision';
    }
}

/** @return array<string, mixed> */
function approvalFlow(?string $deadline = null): array
{
    return [
        'name' => 'purchase-approval',
        'version' => '1.0',
        'steps' => [
            ['id' => 'approve', 'action' => AwaitApprovalAction::class, 'config' => $deadline === null ? [] : ['deadline' => $deadline]],
            ['id' => 'order', 'action' => LogAction::class, 'config' => ['message' => 'ordering']],
            ['id' => 'reject', 'action' => LogAction::class, 'config' => ['message' => 'rejected']],
        ],
        'transitions' => [
            ['from' => 'approve', 'to' => 'order', 'condition' => 'steps.approve.payload.decision === "approved"'],
            ['from' => 'approve', 'to' => 'reject', 'condition' => 'steps.approve.payload.decision !== "approved"'],
        ],
    ];
}

beforeEach(function () {
    AwaitApprovalAction::$runs = 0;
    $this->events = new SpyEventDispatcher;
    $this->engine = new WorkflowEngine($this->storage, $this->events);
});

describe('suspend and signal', function () {
    test('a suspended step waits without finishing, and the next steps do not run', function () {
        $this->engine->start('po-1', approvalFlow(), ['amount' => 25_000]);
        $instance = $this->engine->getInstance('po-1');

        expect($instance->getState())->toBe(WorkflowState::WAITING)
            ->and($instance->getWait()?->stepId)->toBe('approve')
            ->and($instance->getWait()?->for)->toBe('decided')
            ->and($instance->getCompletedSteps())->toBe([])
            ->and($instance->getData()['asked'])->toBeTrue()
            ->and($this->events->countDispatched(WorkflowSuspendedEvent::class))->toBe(1)
            ->and($this->events->hasDispatched(WorkflowCompletedEvent::class))->toBeFalse();
    });

    test('the signal finishes the step, keeps its payload and takes the matching branch', function () {
        $this->engine->start('po-1', approvalFlow());

        $instance = $this->engine->signal('po-1', 'decided', ['decision' => 'approved', 'by' => 'ada']);

        expect($instance->getState())->toBe(WorkflowState::COMPLETED)
            ->and($instance->getCompletedSteps())->toBe(['approve', 'order'])
            ->and($instance->getData()['steps']['approve']['resumed_by'])->toBe('decided')
            ->and($instance->getData()['steps']['approve']['payload'])->toBe(['decision' => 'approved', 'by' => 'ada'])
            ->and($instance->getWait())->toBeNull()
            ->and(AwaitApprovalAction::$runs)->toBe(1)
            ->and($this->events->countDispatched(WorkflowResumedEvent::class))->toBe(1);

        $resumed = $this->events->getDispatched(WorkflowResumedEvent::class)[0];
        expect($resumed->signal)->toBe('decided')->and($resumed->step->getId())->toBe('approve');
        expect(array_map(fn (StepCompletedEvent $e) => $e->step->getId(), $this->events->getDispatched(StepCompletedEvent::class)))
            ->toBe(['approve', 'order']);
    });

    test('another payload takes the other branch', function () {
        $this->engine->start('po-1', approvalFlow());

        expect($this->engine->signal('po-1', 'decided', ['decision' => 'rejected'])->getCompletedSteps())->toBe(['approve', 'reject']);
    });

    test('refuses a signal the workflow does not wait for, and anything once it is done', function () {
        $this->engine->start('po-1', approvalFlow());

        expect(fn () => $this->engine->signal('po-1', 'paid'))->toThrow(InvalidWorkflowStateException::class, "waits for 'decided'");
        expect($this->engine->getInstance('po-1')->getState())->toBe(WorkflowState::WAITING);

        $this->engine->signal('po-1', 'decided', ['decision' => 'approved']);
        expect(fn () => $this->engine->signal('po-1', 'decided'))->toThrow(InvalidWorkflowStateException::class, 'not waiting');
    });

    test('nobody can send the timer signal', function () {
        $this->engine->start('po-1', approvalFlow());

        expect(fn () => $this->engine->signal('po-1', Wait::TIMER))->toThrow(InvalidArgumentException::class);
    });

    test('resume() does not run a suspended step a second time', function () {
        $this->engine->start('po-1', approvalFlow());

        expect(fn () => $this->engine->resume('po-1'))->toThrow(InvalidWorkflowStateException::class, 'use signal()');
        expect(AwaitApprovalAction::$runs)->toBe(1);
    });

    test('the wait survives storage: a fresh copy still knows what it waits for', function () {
        $this->engine->start('po-1', approvalFlow('2030-01-01T00:00:00+00:00'));

        $copy = $this->storage->load('po-1');
        $row = $copy->toArray();

        expect($copy)->not->toBe($this->storage->load('po-1'))
            ->and($copy->getWait()?->resumeAt?->format('Y-m-d'))->toBe('2030-01-01')
            ->and($row['wait']['for'])->toBe('decided')
            ->and($row['definition_fingerprint'])->toHaveLength(64);
    });
});

describe('timed waits', function () {
    test('resumeDue() leaves waits that are not due, and ends those that are', function () {
        $this->engine->start('po-1', approvalFlow('2030-01-01T00:00:00+00:00'));
        $this->engine->start('po-2', approvalFlow());

        expect($this->engine->resumeDue(new DateTimeImmutable('2029-12-31T23:59:00+00:00')))->toBe([]);

        expect($this->engine->resumeDue(new DateTimeImmutable('2030-01-01T00:00:01+00:00')))->toBe(['po-1']);
        $instance = $this->engine->getInstance('po-1');
        expect($instance->getState())->toBe(WorkflowState::COMPLETED)
            ->and($instance->getData()['steps']['approve']['resumed_by'])->toBe(Wait::TIMER)
            ->and($instance->getCompletedSteps())->toBe(['approve', 'reject']);

        // Without a deadline only the signal ends the wait.
        expect($this->engine->getInstance('po-2')->getState())->toBe(WorkflowState::WAITING);
    });

    test('a signal before the deadline wins, and the timer no longer applies', function () {
        $this->engine->start('po-1', approvalFlow('2030-01-01T00:00:00+00:00'));
        $this->engine->signal('po-1', 'decided', ['decision' => 'approved']);

        expect($this->engine->resumeDue(new DateTimeImmutable('2031-01-01')))->toBe([]);
        expect($this->engine->getInstance('po-1')->getCompletedSteps())->toBe(['approve', 'order']);
    });

    test('a delay step suspends instead of sleeping, and continues once due', function () {
        $start = microtime(true);
        $this->engine->start('d-1', [
            'name' => 'reminder',
            'steps' => [
                ['id' => 'wait', 'action' => DelayAction::class, 'config' => ['hours' => 2]],
                ['id' => 'remind', 'action' => LogAction::class, 'config' => ['message' => 'reminder']],
            ],
            'transitions' => [['from' => 'wait', 'to' => 'remind']],
        ]);

        expect(microtime(true) - $start)->toBeLessThan(0.5);
        expect($this->engine->getInstance('d-1')->getState())->toBe(WorkflowState::WAITING);
        expect($this->engine->resumeDue(new DateTimeImmutable('+1 hour')))->toBe([]);
        expect($this->engine->resumeDue(new DateTimeImmutable('+3 hours')))->toBe(['d-1']);
        expect($this->engine->getInstance('d-1')->getCompletedSteps())->toBe(['wait', 'remind']);
    });
});

describe('optimistic locking', function () {
    test('a save from a stale copy is refused and writes nothing', function () {
        $this->engine->start('po-1', approvalFlow());
        $stale = $this->storage->load('po-1');

        $this->engine->signal('po-1', 'decided', ['decision' => 'approved']);

        $stale->setErrorMessage('overwritten');
        expect(fn () => $this->storage->save($stale))->toThrow(ConcurrentModificationException::class);
        expect($this->engine->getInstance('po-1')->getState())->toBe(WorkflowState::COMPLETED)
            ->and($this->engine->getInstance('po-1')->getErrorMessage())->toBeNull();
    });

    test('two approvers at once: the second signal finds nothing to wait for', function () {
        $this->engine->start('po-1', approvalFlow());
        $first = $this->storage->load('po-1');
        $second = $this->storage->load('po-1');
        $executor = (fn () => $this->executor)->call($this->engine);

        $executor->resumeWait($first, 'decided', ['decision' => 'approved']);

        expect(fn () => $executor->resumeWait($second, 'decided', ['decision' => 'rejected']))
            ->toThrow(ConcurrentModificationException::class);
        expect($this->engine->getInstance('po-1')->getCompletedSteps())->toBe(['approve', 'order']);
    });

    test('revisions count saves, starting from one', function () {
        $storage = new InMemoryStorage;
        (new WorkflowEngine($storage))->start('po-1', approvalFlow());

        expect($storage->load('po-1')->getRevision())->toBeGreaterThan(1);
    });
});

describe('definition fingerprint', function () {
    test('is the same for the same content in any key order, and changes with the content', function () {
        $a = WorkflowDefinition::fromArray(approvalFlow());
        $reordered = approvalFlow();
        $reordered = array_reverse($reordered, true);
        $b = WorkflowDefinition::fromArray($reordered);
        $changed = approvalFlow();
        $changed['steps'][1]['config']['message'] = 'ordering now';

        expect($a->fingerprint())->toBe($b->fingerprint())
            ->and($a->fingerprint())->not->toBe(WorkflowDefinition::fromArray($changed)->fingerprint());
    });
});

class SlowAction implements WorkflowAction
{
    /** @param array<string, mixed> $config */
    public function __construct(public array $config = [], public mixed $logger = null) {}

    public function execute(WorkflowContext $context): ActionResult
    {
        usleep(1_200_000);

        return ActionResult::success(['slow' => true]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Slow';
    }

    public function getDescription(): string
    {
        return 'Takes 1.2 s';
    }
}

describe('step timeouts', function () {
    test('are not enforced with signals unless asked for', function () {
        $this->engine->start('t-1', ['name' => 'slow', 'steps' => [['id' => 'slow', 'action' => SlowAction::class, 'timeout' => '1s']]]);

        expect($this->engine->getInstance('t-1')->getState())->toBe(WorkflowState::COMPLETED);
    });

    test('are enforced with signalTimeouts in a CLI worker', function () {
        $engine = new WorkflowEngine(new InMemoryStorage, signalTimeouts: true);

        expect(fn () => $engine->start('t-1', ['name' => 'slow', 'steps' => [['id' => 'slow', 'action' => SlowAction::class, 'timeout' => '1s']]]))
            ->toThrow(StepExecutionException::class, "Step 'slow' failed: Step execution timed out after 1 seconds");
    })->skip(! function_exists('pcntl_alarm'), 'needs pcntl');

    test('a conflict while running is not recorded as a failure', function () {
        $this->engine->start('po-1', approvalFlow());
        $stale = $this->storage->load('po-1');
        $this->engine->signal('po-1', 'decided', ['decision' => 'approved']);
        $executor = (fn () => $this->executor)->call($this->engine);

        expect(fn () => $executor->resumeWait($stale, 'decided', ['decision' => 'rejected']))->toThrow(ConcurrentModificationException::class);
        expect($this->events->hasDispatched(WorkflowFailedEvent::class))->toBeFalse();
    });
});
