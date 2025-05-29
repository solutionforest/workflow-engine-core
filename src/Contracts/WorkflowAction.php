<?php

namespace SolutionForest\WorkflowEngine\Contracts;

use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;

/**
 * Interface for implementing workflow actions.
 *
 * WorkflowAction defines the contract for all workflow step implementations.
 * Actions are the core business logic units that perform specific tasks within
 * a workflow execution. Each action should be focused, testable, and idempotent
 * when possible.
 *
 * ## Implementation Guidelines
 *
 * 1. **Single Responsibility**: Each action should perform one specific task
 * 2. **Error Handling**: Use proper exception handling and meaningful error messages
 * 3. **Idempotency**: Design actions to be safely re-executable when possible
 * 4. **Resource Cleanup**: Properly handle resources and cleanup in case of failures
 * 5. **Logging**: Include appropriate logging for debugging and monitoring
 *
 * ## Usage Examples
 *
 * ### Basic Action Implementation
 * ```php
 * class SendEmailAction implements WorkflowAction
 * {
 *     public function execute(WorkflowContext $context): ActionResult
 *     {
 *         $config = $context->getConfig();
 *         $emailService = app(EmailService::class);
 *
 *         try {
 *             $result = $emailService->send($config['to'], $config['subject'], $config['body']);
 *
 *             return ActionResult::success(['message_id' => $result->getId()]);
 *         } catch (EmailException $e) {
 *             throw StepExecutionException::actionFailed(
 *                 $context->getStepId(),
 *                 $e,
 *                 ['email_config' => $config]
 *             );
 *         }
 *     }
 *
 *     public function canExecute(WorkflowContext $context): bool
 *     {
 *         $config = $context->getConfig();
 *         return !empty($config['to']) && !empty($config['subject']);
 *     }
 * }
 * ```
 *
 * ### Conditional Action
 * ```php
 * class PremiumFeatureAction implements WorkflowAction
 * {
 *     public function canExecute(WorkflowContext $context): bool
 *     {
 *         $userData = data_get($context->data, 'user');
 *         return $userData['plan'] === 'premium' && $userData['active'] === true;
 *     }
 * }
 * ```
 *
 * @see BaseAction For a convenient base implementation
 * @see ActionResult For action execution results
 * @see StepExecutionException For action error handling
 */
interface WorkflowAction
{
    /**
     * Execute the workflow action with the provided context.
     *
     * This is the core method where the action's business logic is implemented.
     * It should perform the intended operation and return an appropriate result.
     *
     * @param  WorkflowContext  $context  The workflow execution context
     * @return ActionResult The result of the action execution
     *
     * @throws StepExecutionException When the action fails to execute properly
     *
     * @example
     * ```php
     * public function execute(WorkflowContext $context): ActionResult
     * {
     *     $config = $context->getConfig();
     *     $data = $context->getData();
     *
     *     // Perform action logic
     *     $result = $this->performOperation($config, $data);
     *
     *     return ActionResult::success(['result' => $result]);
     * }
     * ```
     */
    public function execute(WorkflowContext $context): ActionResult;

    /**
     * Check if this action can be executed with the given context.
     *
     * This method allows for pre-execution validation and conditional logic.
     * It should check prerequisites, validate configuration, and ensure the
     * action can be safely executed.
     *
     * @param  WorkflowContext  $context  The workflow execution context
     * @return bool True if the action can be executed, false otherwise
     *
     * @example
     * ```php
     * public function canExecute(WorkflowContext $context): bool
     * {
     *     $config = $context->getConfig();
     *
     *     // Check required configuration
     *     if (empty($config['api_key'])) {
     *         return false;
     *     }
     *
     *     // Check data prerequisites
     *     $data = $context->getData();
     *     return isset($data['user']['id']);
     * }
     * ```
     */
    public function canExecute(WorkflowContext $context): bool;

    /**
     * Get the human-readable display name for this action.
     *
     * Used for workflow visualization, debugging, and user interfaces.
     * Should be descriptive and concise.
     *
     * @return string The action display name
     *
     * @example
     * ```php
     * public function getName(): string
     * {
     *     return 'Send Welcome Email';
     * }
     * ```
     */
    public function getName(): string;

    /**
     * Get a detailed description of what this action does.
     *
     * Used for documentation, workflow visualization, and debugging.
     * Should explain the action's purpose and behavior clearly.
     *
     * @return string The action description
     *
     * @example
     * ```php
     * public function getDescription(): string
     * {
     *     return 'Sends a personalized welcome email to the user using the configured template';
     * }
     * ```
     */
    public function getDescription(): string;
}
