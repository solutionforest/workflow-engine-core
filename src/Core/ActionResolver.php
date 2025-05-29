<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Actions\DelayAction;
use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

/**
 * Resolves action names to concrete action classes for workflow execution.
 *
 * ActionResolver provides a centralized mechanism for mapping action names
 * to their corresponding implementation classes. It supports multiple
 * resolution strategies including direct class references, predefined
 * action shortcuts, and automatic class name construction.
 *
 * ## Resolution Strategies
 * 1. **Direct Class Names**: Full class names are returned as-is if they exist
 * 2. **Predefined Shortcuts**: Built-in action names mapped to specific classes
 * 3. **Automatic Construction**: Automatic class name generation for common patterns
 * 4. **Custom Actions**: Support for user-defined action classes
 *
 * ## Usage Examples
 *
 * ### Built-in Actions
 * ```php
 * // Using predefined shortcuts
 * $logClass = ActionResolver::resolve('log'); // LogAction::class
 * $delayClass = ActionResolver::resolve('delay'); // DelayAction::class
 * ```
 *
 * ### Direct Class References
 * ```php
 * // Full class names
 * $class = ActionResolver::resolve(SendEmailAction::class);
 * $class = ActionResolver::resolve('App\\Actions\\CustomAction');
 * ```
 *
 * ### Automatic Resolution
 * ```php
 * // Automatic class name construction
 * $class = ActionResolver::resolve('sendEmail'); // SendEmailAction::class
 * $class = ActionResolver::resolve('processPayment'); // ProcessPaymentAction::class
 * ```
 *
 * ### Validation
 * ```php
 * // Check if action implements correct interface
 * if (ActionResolver::isValidAction($className)) {
 *     $action = new $className($config);
 * }
 * ```
 *
 * @see WorkflowAction For the interface that all actions must implement
 * @see Step For how actions are configured in workflow steps
 */
class ActionResolver
{
    /** @var array<string, class-string<WorkflowAction>> Predefined action mappings */
    private const ACTION_MAP = [
        'log' => LogAction::class,
        'delay' => DelayAction::class,
    ];

    /** @var array<string, string> Custom action mappings registered at runtime */
    private static array $customActions = [];

    /**
     * Resolve action name to concrete action class.
     *
     * This method attempts to resolve an action name using multiple strategies:
     * 1. Check if it's already a valid class name
     * 2. Look for predefined action shortcuts
     * 3. Check custom registered actions
     * 4. Attempt automatic class name construction
     *
     * @param string $actionName The action name or class to resolve
     * @return string The fully qualified action class name
     *
     * @throws InvalidWorkflowDefinitionException If the action cannot be resolved or is invalid
     *
     * @example Basic resolution
     * ```php
     * // Predefined actions
     * $class = ActionResolver::resolve('log'); // LogAction::class
     *
     * // Direct class names
     * $class = ActionResolver::resolve(MyCustomAction::class);
     *
     * // Automatic construction
     * $class = ActionResolver::resolve('sendEmail'); // SendEmailAction::class
     * ```
     */
    public static function resolve(string $actionName): string
    {
        // Strategy 1: If it's already a full class name, validate and return
        if (class_exists($actionName)) {
            if (! self::isValidAction($actionName)) {
                throw InvalidWorkflowDefinitionException::invalidActionClass(
                    $actionName,
                    WorkflowAction::class
                );
            }

            return $actionName;
        }

        // Strategy 2: Check predefined action map
        if (isset(self::ACTION_MAP[$actionName])) {
            return self::ACTION_MAP[$actionName];
        }

        // Strategy 3: Check custom registered actions
        if (isset(self::$customActions[$actionName])) {
            return self::$customActions[$actionName];
        }

        // Strategy 4: Try automatic class name construction
        $className = 'SolutionForest\\WorkflowEngine\\Actions\\'.self::normalizeActionName($actionName).'Action';
        if (class_exists($className)) {
            if (! self::isValidAction($className)) {
                throw InvalidWorkflowDefinitionException::invalidActionClass(
                    $className,
                    WorkflowAction::class
                );
            }

            return $className;
        }

        // Strategy 5: Try in global namespace for user-defined actions
        $globalClassName = self::normalizeActionName($actionName).'Action';
        if (class_exists($globalClassName)) {
            if (! self::isValidAction($globalClassName)) {
                throw InvalidWorkflowDefinitionException::invalidActionClass(
                    $globalClassName,
                    WorkflowAction::class
                );
            }

            return $globalClassName;
        }

        // If all strategies fail, throw descriptive exception
        throw InvalidWorkflowDefinitionException::actionNotFound($actionName, [
            'tried_classes' => [
                $className,
                $globalClassName,
            ],
            'predefined_actions' => array_keys(self::ACTION_MAP),
            'custom_actions' => array_keys(self::$customActions),
            'suggestion' => "Create a class named '{$className}' that implements WorkflowAction, or register a custom action with ActionResolver::register()",
        ]);
    }

    /**
     * Register a custom action mapping.
     *
     * Allows registration of custom action classes with short names for
     * easier reference in workflow definitions. Useful for application-specific
     * actions or third-party action libraries.
     *
     * @param string $name The short name for the action
     * @param string $className The fully qualified class name
     *
     * @throws InvalidWorkflowDefinitionException If the class doesn't implement WorkflowAction
     *
     * @example Custom action registration
     * ```php
     * // Register application-specific actions
     * ActionResolver::register('sendWelcomeEmail', App\Actions\SendWelcomeEmailAction::class);
     * ActionResolver::register('processStripePayment', App\Actions\StripePaymentAction::class);
     *
     * // Now use in workflow definitions
     * $builder->addStep('welcome', 'sendWelcomeEmail', ['template' => 'welcome']);
     * ```
     */
    public static function register(string $name, string $className): void
    {
        if (! class_exists($className)) {
            throw InvalidWorkflowDefinitionException::actionNotFound($className);
        }

        if (! self::isValidAction($className)) {
            throw InvalidWorkflowDefinitionException::invalidActionClass(
                $className,
                WorkflowAction::class
            );
        }

        self::$customActions[$name] = $className;
    }

    /**
     * Check if a class is a valid workflow action.
     *
     * Validates that a given class exists and implements the WorkflowAction interface.
     *
     * @param string $className The class name to validate
     * @return bool True if the class is a valid action, false otherwise
     *
     * @example Action validation
     * ```php
     * if (ActionResolver::isValidAction(MyAction::class)) {
     *     $action = new MyAction($config);
     * } else {
     *     throw new Exception('Invalid action class');
     * }
     * ```
     */
    public static function isValidAction(string $className): bool
    {
        if (! class_exists($className)) {
            return false;
        }

        return in_array(WorkflowAction::class, class_implements($className) ?: []);
    }

    /**
     * Get all available predefined actions.
     *
     * Returns a mapping of action names to their corresponding class names
     * for all predefined actions built into the workflow engine.
     *
     * @return array<string, class-string<WorkflowAction>> Predefined action mappings
     *
     * @example List available actions
     * ```php
     * $actions = ActionResolver::getAvailableActions();
     * foreach ($actions as $name => $class) {
     *     echo "Action '{$name}' maps to {$class}\n";
     * }
     * ```
     */
    public static function getAvailableActions(): array
    {
        return self::ACTION_MAP;
    }

    /**
     * Get all custom registered actions.
     *
     * Returns a mapping of custom action names to their corresponding class names
     * that have been registered via the register() method.
     *
     * @return array<string, string> Custom action mappings
     *
     * @example List custom actions
     * ```php
     * $customActions = ActionResolver::getCustomActions();
     * foreach ($customActions as $name => $class) {
     *     echo "Custom action '{$name}' maps to {$class}\n";
     * }
     * ```
     */
    public static function getCustomActions(): array
    {
        return self::$customActions;
    }

    /**
     * Clear all custom action registrations.
     *
     * Removes all custom action mappings. Useful for testing or when
     * you need to reset the action resolver state.
     *
     * @example Reset custom actions
     * ```php
     * // Register some actions
     * ActionResolver::register('custom1', CustomAction1::class);
     * ActionResolver::register('custom2', CustomAction2::class);
     *
     * // Clear all custom registrations
     * ActionResolver::clearCustomActions();
     *
     * // Now only predefined actions are available
     * ```
     */
    public static function clearCustomActions(): void
    {
        self::$customActions = [];
    }

    /**
     * Check if an action name is registered.
     *
     * @param string $actionName The action name to check
     * @return bool True if the action is available, false otherwise
     *
     * @example Check action availability
     * ```php
     * if (ActionResolver::has('log')) {
     *     // Action is available
     * }
     *
     * if (ActionResolver::has('myCustomAction')) {
     *     // Custom action is registered
     * }
     * ```
     */
    public static function has(string $actionName): bool
    {
        return isset(self::ACTION_MAP[$actionName]) ||
               isset(self::$customActions[$actionName]) ||
               class_exists($actionName);
    }

    /**
     * Normalize action names for automatic class name construction.
     *
     * Converts action names from various formats (camelCase, snake_case, kebab-case)
     * to PascalCase for class name construction.
     *
     * @param string $actionName The action name to normalize
     * @return string The normalized class name part
     *
     * @example Name normalization
     * ```php
     * ActionResolver::normalizeActionName('send_email'); // 'SendEmail'
     * ActionResolver::normalizeActionName('send-email'); // 'SendEmail'
     * ActionResolver::normalizeActionName('sendEmail'); // 'SendEmail'
     * ActionResolver::normalizeActionName('SEND_EMAIL'); // 'SendEmail'
     * ```
     */
    private static function normalizeActionName(string $actionName): string
    {
        // Convert to snake_case first, then to PascalCase
        $snakeCase = strtolower(preg_replace('/[A-Z]/', '_$0', $actionName));
        $snakeCase = str_replace(['-', ' '], '_', $snakeCase);
        $snakeCase = trim($snakeCase, '_');

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snakeCase)));
    }
}
