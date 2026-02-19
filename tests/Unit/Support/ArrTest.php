<?php

use SolutionForest\WorkflowEngine\Support\Arr;

describe('Arr', function () {
    describe('get', function () {
        test('returns value for simple key', function () {
            $data = ['name' => 'John', 'age' => 30];
            expect(Arr::get($data, 'name'))->toBe('John');
        });

        test('returns value for dot notation key', function () {
            $data = ['user' => ['profile' => ['name' => 'John']]];
            expect(Arr::get($data, 'user.profile.name'))->toBe('John');
        });

        test('returns default for missing key', function () {
            expect(Arr::get([], 'missing', 'default'))->toBe('default');
        });

        test('returns default for missing nested key', function () {
            $data = ['user' => ['name' => 'John']];
            expect(Arr::get($data, 'user.email', 'none'))->toBe('none');
        });

        test('returns null as default when no default provided', function () {
            expect(Arr::get([], 'missing'))->toBeNull();
        });

        test('handles deeply nested values', function () {
            $data = ['a' => ['b' => ['c' => ['d' => 'deep']]]];
            expect(Arr::get($data, 'a.b.c.d'))->toBe('deep');
        });

        test('returns full array value for intermediate key', function () {
            $data = ['user' => ['name' => 'John', 'age' => 30]];
            expect(Arr::get($data, 'user'))->toBe(['name' => 'John', 'age' => 30]);
        });
    });

    describe('set', function () {
        test('sets simple key', function () {
            $data = [];
            Arr::set($data, 'name', 'John');
            expect($data)->toBe(['name' => 'John']);
        });

        test('sets nested key with dot notation', function () {
            $data = [];
            Arr::set($data, 'user.name', 'John');
            expect($data)->toBe(['user' => ['name' => 'John']]);
        });

        test('sets deeply nested key', function () {
            $data = [];
            Arr::set($data, 'a.b.c', 'value');
            expect($data)->toBe(['a' => ['b' => ['c' => 'value']]]);
        });

        test('overwrites existing value', function () {
            $data = ['name' => 'John'];
            Arr::set($data, 'name', 'Jane');
            expect($data['name'])->toBe('Jane');
        });
    });
});
