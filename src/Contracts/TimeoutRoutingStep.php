<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

/**
 * An awaiting step whose timeout is a deadline rather than a fatal error.
 *
 * By default a step that reaches its timeoutSeconds() fails the instance: the
 * signal never came, so the workflow cannot continue. That suits a wait which
 * is meant to succeed. It does not suit a wait which is meant to expire — an
 * invoice waiting on payment, where fifteen days of silence should send a
 * reminder and keep waiting, not kill the renewal.
 *
 * A step implementing this interface routes to another step in the sequence
 * instead of failing, through the same jump goto uses. Waiting for a signal
 * and waiting for a deadline can then be one step rather than an await here
 * and a scheduled command somewhere else reaching in to push it along.
 */
interface TimeoutRoutingStep
{
    /**
     * The step class to continue from when the wait expires.
     *
     * Must appear in the instance's pinned step sequence; the engine fails the
     * instance with an explanatory reason if it does not, rather than leaving
     * it parked on a signal that is never coming.
     *
     * @return class-string<WorkflowStep>
     */
    public function timeoutTo(): string;
}
