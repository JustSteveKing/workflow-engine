<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * A step whose timeout depends on the instance it is running for.
 *
 * timeoutSeconds() takes no arguments, so it can express "an hour after this
 * step parks" but not "three days after the date this particular order was
 * promised". Deadlines in a real workflow are usually the second kind: an
 * invoice chased relative to its own due date, a trial ending on the date the
 * customer signed up, a hold released when the reservation it belongs to
 * expires. Without the context, that has to be computed outside the engine by
 * something that queries for parked instances and pushes them along.
 *
 * A step implementing this returns the delay for the instance in front of it.
 * It is checked before timeoutSeconds(), which stays the simple case.
 */
interface ContextualTimeout
{
    /**
     * Seconds to wait before this step's timeout fires, for this instance.
     *
     * Measured from the moment the step parks, so a deadline held in the
     * context is expressed as the remaining time to it. A deadline already
     * past should return 0 rather than a negative number; the engine clamps
     * either way. Return null to wait indefinitely, as timeoutSeconds() does.
     */
    public function timeoutSecondsFor(WorkflowContext $context): ?int;
}
