<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Core;

/** What a step returned ActionResult::suspend() for: a signal name, a time, or both. */
final readonly class Suspension
{
    public function __construct(
        public string $for,
        public ?\DateTimeImmutable $resumeAt = null,
    ) {
        if ($for === '') {
            throw new \InvalidArgumentException('A suspended step must say what it waits for.');
        }
        if ($for === Wait::TIMER && $resumeAt === null) {
            throw new \InvalidArgumentException('A step that waits for the timer needs a time to resume at.');
        }
    }

    /** @return array{for: string, resume_at: string|null} */
    public function toArray(): array
    {
        return ['for' => $this->for, 'resume_at' => $this->resumeAt?->format(\DateTimeInterface::ATOM)];
    }
}
