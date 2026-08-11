<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine;

use InvalidArgumentException;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateMachineContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\TransitionContract;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\StateMachine\Transitions\Await;
use JustSteveKing\WorkflowEngine\StateMachine\Transitions\Compensate;
use JustSteveKing\WorkflowEngine\StateMachine\Transitions\Complete;
use JustSteveKing\WorkflowEngine\StateMachine\Transitions\Fail;
use JustSteveKing\WorkflowEngine\StateMachine\Transitions\Proceed;
use JustSteveKing\WorkflowEngine\StateMachine\Transitions\Sleep;

/**
 * Adapts a WorkflowStatus to the state-machine package: exposes the current
 * state and the full table of legal status transitions for an instance.
 *
 * @internal
 */
final class WorkflowStateMachine implements StateMachineContract
{
    /**
     * The transitions are stateless and immutable, so they are cached and shared
     * rather than rebuilt on every status change.
     *
     * @var array<string, TransitionContract>
     */
    private static array $transitions;

    public function __construct(
        private readonly WorkflowStatus $current,
    ) {}

    /**
     * @return array<int, TransitionContract>
     */
    public function transitions(): array
    {
        return array_values(self::table());
    }

    public function currentState(): StateContract
    {
        return $this->current;
    }

    /**
     * The transition that leads to a given target status.
     */
    public static function transitionFor(WorkflowStatus $target): TransitionContract
    {
        return self::table()[$target->value]
            ?? throw new InvalidArgumentException('Pending is the initial state and cannot be transitioned to.');
    }

    /**
     * @return array<string, TransitionContract>
     */
    private static function table(): array
    {
        return self::$transitions ??= [
            WorkflowStatus::InProgress->value => new Proceed(),
            WorkflowStatus::Awaiting->value => new Await(),
            WorkflowStatus::Sleeping->value => new Sleep(),
            WorkflowStatus::Completed->value => new Complete(),
            WorkflowStatus::Compensating->value => new Compensate(),
            WorkflowStatus::Failed->value => new Fail(),
        ];
    }
}
