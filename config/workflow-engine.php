<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The connection and queue name the engine's jobs (AdvanceWorkflow,
    | TimeoutWorkflowStep, CompensateWorkflow) are dispatched onto. Leave null
    | to use the application's default connection / queue.
    |
    */
    'queue' => [
        'connection' => env('WORKFLOW_ENGINE_QUEUE_CONNECTION'),
        'name' => env('WORKFLOW_ENGINE_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry backoff
    |--------------------------------------------------------------------------
    |
    | When a step fails and has retry budget remaining (see maxAttempts()), the
    | retry is delayed. The delay grows exponentially with the attempt number,
    | starting from "backoff" seconds and capped at "max_backoff" seconds. A
    | step may override this by implementing HasRetryBackoff. Set "backoff" to 0
    | for immediate retries.
    |
    */
    'retry' => [
        'backoff' => (int) env('WORKFLOW_ENGINE_RETRY_BACKOFF', 5),
        'max_backoff' => (int) env('WORKFLOW_ENGINE_RETRY_MAX_BACKOFF', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signals
    |--------------------------------------------------------------------------
    |
    | When enabled, a signal delivered before its step is awaiting is buffered
    | and consumed the moment the step parks. This prevents a fast external
    | provider (e.g. a webhook that beats the queued advance job) from losing a
    | signal. When disabled, delivering a signal an instance is not awaiting
    | throws InvalidSignalException.
    |
    */
    'signals' => [
        'buffer_early' => (bool) env('WORKFLOW_ENGINE_BUFFER_EARLY_SIGNALS', true),
    ],
];
