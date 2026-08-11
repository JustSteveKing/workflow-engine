# Configuration

Publish the config file with `php artisan vendor:publish --tag=workflow-engine-config`. It lands at `config/workflow-engine.php`:

```php
return [
    // Connection and queue the engine's jobs run on. null = application default.
    'queue' => [
        'connection' => env('WORKFLOW_ENGINE_QUEUE_CONNECTION'),
        'name' => env('WORKFLOW_ENGINE_QUEUE'),
    ],

    // Retry delay: exponential from "backoff" seconds, capped at "max_backoff".
    // 0 = immediate retries. A step can override this via CustomRetryBackoff.
    'retry' => [
        'backoff' => (int) env('WORKFLOW_ENGINE_RETRY_BACKOFF', 5),
        'max_backoff' => (int) env('WORKFLOW_ENGINE_RETRY_MAX_BACKOFF', 300),
    ],

    // Consume a signal delivered before its step is awaiting, instead of throwing.
    // Prevents lost webhooks from a fast provider.
    'signals' => [
        'buffer_early' => (bool) env('WORKFLOW_ENGINE_BUFFER_EARLY_SIGNALS', true),
    ],
];
```

## Keys

| Key | Env | Default | Meaning |
|---|---|---|---|
| `queue.connection` | `WORKFLOW_ENGINE_QUEUE_CONNECTION` | `null` | Connection the engine's jobs dispatch onto. `null` uses the application default. Give the engine its own connection in production so advance jobs do not queue behind slow work. |
| `queue.name` | `WORKFLOW_ENGINE_QUEUE` | `null` | Queue name for the engine's jobs. `null` uses the default queue. |
| `retry.backoff` | `WORKFLOW_ENGINE_RETRY_BACKOFF` | `5` | Base seconds for the exponential retry delay. `0` retries immediately. See [timeouts and retries](timeouts-and-retries.md). |
| `retry.max_backoff` | `WORKFLOW_ENGINE_RETRY_MAX_BACKOFF` | `300` | Cap on the retry delay in seconds. |
| `signals.buffer_early` | `WORKFLOW_ENGINE_BUFFER_EARLY_SIGNALS` | `true` | Buffer a signal delivered before its step is awaiting, instead of throwing. See [signals](signals.md#early-signal-buffering). |

## Related

- [Running on the queue](running-on-the-queue.md), the `queue` keys in context.
- [Timeouts and retries](timeouts-and-retries.md), the `retry` keys in context.
- [Signals](signals.md), the `signals.buffer_early` behaviour in full.
