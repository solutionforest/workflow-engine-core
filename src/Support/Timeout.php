<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

final class Timeout
{
    /**
     * Parse a timeout value to seconds.
     *
     * Accepts either an integer (seconds) or a string like '30s', '5m', '2h', '1d'.
     *
     * @param string|int $timeout Timeout value
     * @return int Timeout in seconds
     *
     * @throws InvalidWorkflowDefinitionException If format is invalid
     */
    public static function toSeconds(string|int $timeout): int
    {
        if (is_int($timeout)) {
            return $timeout;
        }

        // Pure numeric string
        if (is_numeric($timeout)) {
            return (int) $timeout;
        }

        if (! preg_match('/^(\d+)([smhd])$/', $timeout, $matches)) {
            throw InvalidWorkflowDefinitionException::invalidTimeout($timeout);
        }

        $value = (int) $matches[1];
        $unit = $matches[2];

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
        };
    }
}
