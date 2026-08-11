# Events

Every transition dispatches a domain event from `JustSteveKing\WorkflowEngine\Events`, fired after the database transaction commits. Listen for logging, metrics, notifications, or a dashboard.

| Event | Fired when |
|---|---|
| `WorkflowStarted` | An instance is created. |
| `StepCompleted` | A step advanced through complete, goto, sleep, or signal. |
| `StepFailed` | A step failed. `willRetry` tells you whether a retry follows. |
| `WorkflowAwaitingSignal` | A step parked on a signal. |
| `SignalReceived` | A signal was delivered. `buffered` tells you whether it was queued for later. |
| `StepTimedOut` | An awaiting step timed out. |
| `WorkflowSlept` | A step began sleeping, with `wakeAt`. |
| `WorkflowCompleted` | The workflow finished. |
| `WorkflowCompensating` | Saga rollback began. |
| `StepCompensationFailed` | A step's `compensate()` threw during rollback. |
| `WorkflowFailed` | The workflow failed, after any compensation. |
| `WorkflowRetried` | A failed instance was re-armed. |

If you build one dashboard off this package, build it off these events. They give you time-to-completion, stuck instance counts by step, and signal latency without touching the engine.

## Related

- [Running on the queue](running-on-the-queue.md), why events fire only after commit.
- [The state machine](the-state-machine.md), the transitions these events report.
