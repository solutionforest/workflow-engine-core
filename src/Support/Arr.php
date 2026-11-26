<?php

namespace SolutionForest\WorkflowEngine\Support;

final class Arr
{
    /**
     * Get a value from a nested array using dot notation.
     *
     * @param array<string, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_array($array) || ! array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Determine whether a nested key exists, using dot notation.
     *
     * Distinguishes "the key is absent" from "the key holds null", which
     * `get()` alone cannot express.
     *
     * @param array<string, mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        $current = $array;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    /**
     * Whether every key of the array is a string (vacuously true when empty).
     *
     * @param array<mixed> $array
     *
     * @phpstan-assert-if-true array<string, mixed> $array
     */
    public static function isStringKeyed(array $array): bool
    {
        foreach (array_keys($array) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether every value of the array is a string (vacuously true when empty).
     *
     * @param array<mixed> $array
     *
     * @phpstan-assert-if-true array<string> $array
     */
    public static function isStringArray(array $array): bool
    {
        foreach ($array as $value) {
            if (! is_string($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Describe an arbitrary value for an error message: strings verbatim,
     * other scalars as PHP literals, everything else by type name.
     */
    public static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        return get_debug_type($value);
    }

    /**
     * Get the class "basename" of a class string (without namespace).
     */
    public static function classBasename(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * Set a value in a nested array using dot notation.
     *
     * @param array<string, mixed> $array
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
