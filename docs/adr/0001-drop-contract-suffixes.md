# 0001. Drop the `Contract` suffix from core contract interfaces

- Status: accepted
- Date: 2026-08-10

## Context

The `src/Contracts/` directory carried three different naming conventions at once. Three interfaces used a `*Contract` suffix (`WorkflowStepContract`, `WorkflowDefinitionContract`, `WorkflowRepositoryContract`), two used a bare descriptive name (`CompensatingStep`, `VersionedWorkflowDefinition`), and one used a `Has*` prefix (`HasRetryBackoff`).

Two things bothered me. The suffixed names are marked as contracts twice, once by the `Contracts` namespace and again by the suffix, which Laravel's own `Illuminate\Contracts\*` interfaces do not do. And the split was applied inconsistently: `WorkflowDefinitionContract` and `VersionedWorkflowDefinition` both describe definitions, `WorkflowStepContract` and `CompensatingStep` both describe steps, yet only one of each pair carried the suffix.

There is a real distinction hiding in the set. Three interfaces are core contracts that a step, definition, or repository must satisfy. Three are optional capabilities that a step or definition may additionally implement. I want the names to make that distinction legible rather than accidental.

## Decision

Drop the `Contract` suffix from the three core contracts:

- `WorkflowStepContract` becomes `WorkflowStep`
- `WorkflowDefinitionContract` becomes `WorkflowDefinition`
- `WorkflowRepositoryContract` becomes `WorkflowRepository`

Keep the optional capability interfaces as bare, descriptive "modifier + noun" names, and bring the one outlier into line:

- `CompensatingStep` stays
- `VersionedWorkflowDefinition` stays
- `HasRetryBackoff` becomes `CustomRetryBackoff`

The result is one convention per tier: core contracts are the plain noun, capabilities are a descriptive qualifier on the noun.

## Consequences

- Every implementer changes its `implements` clause and imports: 27 step classes, 23 definitions, the Eloquent repository, and the two backoff steps. This is a breaking change for anyone who has written their own steps or definitions against the old names.
- The package is 0.x, so I am taking the break now rather than running a deprecation cycle. It will be recorded in `UPGRADE.md` and released under a version bump.
- The names now match the surrounding Laravel conventions, and a reader can tell a core contract from an optional capability by its shape.

## Alternatives considered

- **Suffix everything with `Contract`.** Consistent, but it doubles down on the double-marking that Laravel deliberately avoids, and it reads worse (`CompensatingStepContract`).
- **`Has*` prefix on all three capabilities** (`HasCompensation`, `HasRetryBackoff`, `HasVersion`). Uniform, but it renames five more implementers and drops the "Step" and "Definition" context from otherwise self-describing names. `Has*` is also more of an Eloquent and trait convention than a `Contracts` one.
- **Leave it alone.** The inconsistency is cosmetic today, but this is a public contract and the cost of changing it only goes up after 1.0.
