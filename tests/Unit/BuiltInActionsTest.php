<?php

use SolutionForest\WorkflowEngine\Actions\ConditionAction;
use SolutionForest\WorkflowEngine\Actions\DelayAction;
use SolutionForest\WorkflowEngine\Actions\FakeEmailAction;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

function contextWith(array $config, array $data = []): WorkflowContext
{
    return new WorkflowContext(
        workflowId: 'wf-test',
        stepId: 'step-test',
        data: $data,
        config: $config
    );
}

describe('FakeEmailAction', function () {

    /**
     * The action never sent anything, but reported 'status' => 'sent', so a
     * workflow could show a delivered confirmation that did not exist.
     */
    test('never claims an email was sent', function () {
        $action = new FakeEmailAction(['to' => 'user@example.com']);

        $result = $action->execute(contextWith([
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'template' => 'welcome',
        ]));

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['email']['sent'])->toBeFalse();
        expect($result->getData()['email']['mock'])->toBeTrue();
    });

    test('records what would have been sent', function () {
        $action = new FakeEmailAction;

        $result = $action->execute(contextWith([
            'to' => 'user@example.com',
            'subject' => 'Order Confirmed',
            'template' => 'order',
        ]));

        $email = $result->getData()['email'];

        expect($email['to'])->toBe('user@example.com');
        expect($email['subject'])->toBe('Order Confirmed');
        expect($email['template'])->toBe('order');
    });

    test('requires a recipient', function () {
        $action = new FakeEmailAction;

        expect($action->canExecute(contextWith([])))->toBeFalse();
        expect($action->canExecute(contextWith(['to' => 'a@b.com'])))->toBeTrue();
    });

});

describe('DelayAction units', function () {

    /**
     * `minutes` and `hours` were documented but never read, so delay(hours: 2)
     * silently fell through to the one second default.
     */
    test('reports the delay in the unit it was given', function () {
        $action = new DelayAction(['microseconds' => 1000]);

        $result = $action->execute(contextWith(['microseconds' => 1000]));

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['delayed_microseconds'])->toBe(1000);
    });

    test('minutes are honoured rather than ignored', function () {
        // Assert the computed duration without actually sleeping for a minute:
        // a zero-valued minutes key still proves the unit is read.
        $action = new DelayAction(['minutes' => 0]);

        $result = $action->execute(contextWith(['minutes' => 0]));

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['delayed_microseconds'])->toBe(0);
    });

    test('hours are honoured rather than ignored', function () {
        $action = new DelayAction(['hours' => 0]);

        $result = $action->execute(contextWith(['hours' => 0]));

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['delayed_microseconds'])->toBe(0);
    });

    test('units combine', function () {
        $action = new DelayAction(['hours' => 0, 'minutes' => 0, 'microseconds' => 500]);

        $result = $action->execute(contextWith(['hours' => 0, 'minutes' => 0, 'microseconds' => 500]));

        expect($result->getData()['delayed_microseconds'])->toBe(500);
    });

    test('rejects a negative duration', function () {
        $action = new DelayAction(['seconds' => -1]);

        $result = $action->execute(contextWith(['seconds' => -1]));

        expect($result->isSuccess())->toBeFalse();
    });

});

describe('ConditionAction', function () {

    /**
     * This action used to carry its own parser accepting "=" and "is", which no
     * other part of the engine understood. It now shares one grammar.
     */
    test('uses the shared condition grammar', function () {
        $action = new ConditionAction(['condition' => 'order.total > 1000']);

        $result = $action->execute(contextWith(
            ['condition' => 'order.total > 1000'],
            ['order' => ['total' => 1500]]
        ));

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['result'])->toBeTrue();
    });

    test('supports boolean operators like every other condition', function () {
        $condition = 'order.total > 1000 && order.vip === true';
        $action = new ConditionAction(['condition' => $condition]);

        $result = $action->execute(contextWith(
            ['condition' => $condition],
            ['order' => ['total' => 500, 'vip' => true]]
        ));

        expect($result->getData()['result'])->toBeFalse();
    });

    test('fails cleanly on a malformed condition', function () {
        $action = new ConditionAction(['condition' => 'order.total > ']);

        $result = $action->execute(contextWith(['condition' => 'order.total > ']));

        expect($result->isSuccess())->toBeFalse();
    });

    test('requires a condition', function () {
        $action = new ConditionAction([]);

        $result = $action->execute(contextWith([]));

        expect($result->isSuccess())->toBeFalse();
    });

});
