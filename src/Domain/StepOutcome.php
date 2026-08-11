<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

/**
 * The outcome a step reports back to the engine, driving what the engine does
 * next: advance, wait for a signal, fail, jump to another step, or sleep.
 */
enum StepOutcome: string
{
    case Completed = 'completed';
    case Awaiting = 'awaiting';
    case Failed = 'failed';
    case Goto = 'goto';
    case Sleeping = 'sleeping';
}
