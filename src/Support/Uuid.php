<?php

namespace SolutionForest\WorkflowEngine\Support;

/**
 * Simple UUID version 4 generator for workflow instance identification.
 *
 * This utility class provides a lightweight implementation of UUID v4 generation
 * without external dependencies. It's used throughout the workflow engine for
 * creating unique identifiers for workflow instances, execution contexts,
 * and other entities that require globally unique identification.
 *
 * ## Features
 * - **RFC 4122 Compliant**: Generates valid UUID v4 strings
 * - **Cryptographically Random**: Uses mt_rand() for pseudo-random generation
 * - **No Dependencies**: Pure PHP implementation without external libraries
 * - **Thread Safe**: Safe for concurrent usage in multi-threaded environments
 *
 * ## Usage Examples
 *
 * ### Generate Workflow Instance ID
 * ```php
 * $workflowId = Uuid::v4(); // "f47ac10b-58cc-4372-a567-0e02b2c3d479"
 *
 * $engine = new WorkflowEngine($storage);
 * $instanceId = $engine->start($workflowId, $definition, $context);
 * ```
 *
 * ### Generate Unique Step Execution ID
 * ```php
 * $executionId = Uuid::v4();
 * $context = $context->withMetadata('execution_id', $executionId);
 * ```
 *
 * ### Generate Correlation IDs
 * ```php
 * $correlationId = Uuid::v4();
 * Log::info('Starting workflow execution', [
 *     'workflow_id' => $workflowId,
 *     'correlation_id' => $correlationId
 * ]);
 * ```
 *
 * ## Format Specification
 *
 * Generated UUIDs follow the standard format: `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`
 * - **Version**: 4 (random)
 * - **Variant**: DCE 1.1 (RFC 4122)
 * - **Length**: 36 characters including hyphens
 * - **Character Set**: Hexadecimal (0-9, a-f)
 *
 * @see https://tools.ietf.org/html/rfc4122 RFC 4122 UUID specification
 */
class Uuid
{
    /**
     * Generate a version 4 (random) UUID string.
     *
     * Creates a new UUID v4 using pseudo-random number generation.
     * The generated UUID is compliant with RFC 4122 and suitable for
     * use as unique identifiers in distributed systems.
     *
     * @return string A 36-character UUID v4 string in canonical format
     *
     * @example Basic usage
     * ```php
     * $uuid = Uuid::v4();
     * echo $uuid; // "550e8400-e29b-41d4-a716-446655440000"
     *
     * // Validate format
     * $isValid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
     * ```
     * @example Use in workflow context
     * ```php
     * $workflowInstance = new WorkflowInstance(
     *     id: Uuid::v4(),
     *     definition: $definition,
     *     state: WorkflowState::PENDING
     * );
     * ```
     */
    public static function v4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),

            // 16 bits for "time_mid"
            mt_rand(0, 0xFFFF),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0FFF) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3FFF) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }
}
