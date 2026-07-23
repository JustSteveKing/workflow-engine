<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

/**
 * A step that defines its own retry backoff, overriding the package default.
 */
interface HasRetryBackoff
{
    /**
     * The number of seconds to wait before the given retry attempt.
     *
     * @param  int  $attempt  The attempt number that just failed (1-based)
     */
    public function retryBackoff(int $attempt): int;
}
