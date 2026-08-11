# Definition versioning

At `start()`, the engine snapshots the definition's ordered step list onto the instance along with a version number.

This is the thing that makes the package safe to deploy. In-flight instances keep running against their snapshot even if you edit `steps()` afterwards, so a Tuesday deploy that inserts a step in the middle of a workflow does not corrupt the two hundred instances currently parked on a webhook. They finish on the shape they started with. New instances get the new shape.

Implement `VersionedWorkflowDefinition` to record an explicit `version()`, which defaults to `1`. It is stored on the instance and carried through for auditing.

## Related

- [Concepts](concepts.md), where the definition and its snapshot fit.
- [The state machine](the-state-machine.md), the lifecycle the snapshot drives.
