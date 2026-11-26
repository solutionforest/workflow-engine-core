<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;

/**
 * What a suspended workflow is waiting for: a named signal (an approval, a task being done),
 * a point in time, or both, whichever comes first.
 *
 * A step suspends by returning ActionResult::suspend(). The instance then stays WAITING with
 * this Wait until WorkflowEngine::signal() delivers the signal, or WorkflowEngine::resumeDue()
 * finds that resumeAt has passed. Either completes the step and the workflow carries on.
 */
final readonly class Wait
{
    /** The signal name of a wait that only a time ends. */
    public const string TIMER = 'timer';

    public function __construct(
        public string $stepId,
        public string $for,
        public ?\DateTimeImmutable $resumeAt,
        public \DateTimeImmutable $since,
    ) {
        if ($stepId === '' || $for === '') {
            throw new \InvalidArgumentException('A wait needs a step and what it waits for.');
        }
        if ($for === self::TIMER && $resumeAt === null) {
            throw new \InvalidArgumentException('A timer wait needs a time to resume at.');
        }
    }

    /** Whether the wait ends by time at $now. */
    public function isDue(\DateTimeInterface $now): bool
    {
        return $this->resumeAt !== null && $this->resumeAt <= $now;
    }

    /** @return array{step_id: string, for: string, resume_at: string|null, since: string} */
    public function toArray(): array
    {
        return [
            'step_id' => $this->stepId,
            'for' => $this->for,
            'resume_at' => $this->resumeAt?->format(\DateTimeInterface::ATOM),
            'since' => $this->since->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $stepId = $data['step_id'] ?? null;
        $for = $data['for'] ?? null;
        $resumeAt = $data['resume_at'] ?? null;
        $since = $data['since'] ?? null;
        if (! is_string($stepId) || ! is_string($for) || ! is_string($since) || ($resumeAt !== null && ! is_string($resumeAt))) {
            throw new InvalidWorkflowStateException(
                'Cannot restore a wait: step_id, for and since must be strings, resume_at a string or null.',
                WorkflowState::WAITING,
                WorkflowState::WAITING,
                '',
            );
        }

        return new self($stepId, $for, $resumeAt === null ? null : new \DateTimeImmutable($resumeAt), new \DateTimeImmutable($since));
    }
}
