# Exceptions

| Exception | Thrown when |
|---|---|
| `WorkflowNotFoundException` | An engine call is given an unknown instance ID. |
| `InvalidSignalException` | `signal()` targets a non-awaiting or mismatched instance while buffering is off, or the instance is terminal. |
| `InvalidArgumentException` | `start()` names an unregistered workflow. |
| `InvalidTransitionException` | The state machine rejected an illegal status change, from `JustSteveKing\WorkflowEngine\StateMachine\Exceptions`. This one means a bug. Legal flows never trigger it. |
| `RuntimeException` | A definition or step does not implement its contract, an `await` omits its signal, a `goto` targets a step outside the sequence, or `retry()` was called on an instance that has not failed. |

## Related

- [The state machine](the-state-machine.md), where `InvalidTransitionException` comes from.
- [Signals](signals.md), when `InvalidSignalException` is thrown.
