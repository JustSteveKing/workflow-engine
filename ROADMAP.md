# Roadmap

Ship before **Laravel Wales, September 2026**.

The talk *Waiting is a Feature* (`../talks/waiting-is-a-feature/slides.md`) is
written against the package as it will be on the day, not as it is at `v1.0.0`.
Everything below is a gap between what a slide states as fact and what the code
does now. Each item names the slide it backs, so if an item gets cut, the slide
gets cut with it.

Items 1–5 are load-bearing: without them a slide is wrong in front of a room, or
wrong for the first person who installs the package afterwards.

## 1. `workflow:show` must accept an aggregate id

**Deck says:** `php artisan workflow:show 4471`, under the heading "What is order
4471 waiting for?" — one command, one answer.
**Package does:** `{id : The workflow instance id}`. 4471 is the aggregate; the
instance is a ULID. Today the real path is two commands —
`workflow:instances --aggregate=4471`, then `workflow:show <ulid>`.

Add an aggregate lookup: `workflow:show order:4471`, or an `--aggregate` option,
or try the instance id and fall back. Needs `WorkflowRepository::findByAggregate()`
and a decision about what happens when one aggregate has several instances (list
them and exit, most likely — silently picking the newest is the kind of thing
that bites during a demo).

## 2. Timeout deadlines must be durable

**Deck says:** "Durable. Survives deploys, restarts, and a flushed Redis" and
"Bounded. Everything that waits must be able to give up" (the spec slide), plus
`ManualReviewStep`'s 24 hours, plus `Times out 2026-09-18 09:14:02 (in 17h 39m)`
on the show output.
**Package does:** the deadline exists only as a delay on a queued
`TimeoutWorkflowStep`. There is no column for it. `workflow:tick` rescues
`status = sleeping` past `wake_at` and nothing else, so an *awaiting* instance
whose timeout job is lost to a queue flush waits forever.

That is exactly "the lost sleeper" — the failure mode the talk promises to kill,
reproduced inside the fix. Add a `timeout_at` column set when a step parks on
`await` with a `timeoutSeconds()`, and extend `workflow:tick` to sweep
`status = awaiting AND timeout_at <= now()`, dispatching
`TimeoutWorkflowStep($id, $stepIndex)`. The existing step-index guard already
makes a duplicate dispatch a no-op.

Sleeps are already durable (`wake_at` + tick). This makes timeouts match.

## 3. Record when an instance started waiting

**Deck says:** `Since 2026-09-17 09:14:02 (6h 21m ago)`.
**Package does:** no such field. `updated_at` is not it — anything else touching
the row resets the clock, and "how long has this been stuck" is the one number
the ops-persona slide sells.

Add `awaiting_since` (set on park, cleared on resume). Cheap, and it's what makes
a "stuck for more than four hours" dashboard a `WHERE` clause instead of an
event-log replay.

## 4. `workflow:show` output should match the slide

**Deck says:**

```txt
  Workflow     fulfil_order
  Instance     01JD9K2M7QX4ZR8V3NPYW6C5TB
  Aggregate    order:4471
  Status       awaiting
  Step         4 of 8   ManualReviewStep
  Awaiting     review_decision
  Since        2026-09-17 09:14:02   (6h 21m ago)
  Times out    2026-09-18 09:14:02   (in 17h 39m)
```

**Package does:** `order / 4471`, `4 / 8 (attempts: 1)`, no current step class on
the cursor line (it's only in the `Steps` list below), no `Since`, no
`Times out`, and a `Started / Completed / Failed` row the deck doesn't show.

Depends on 2 and 3 for the two timestamps. Put the current step class on the
cursor line and humanise both directions ("6h 21m ago" / "in 17h 39m").

## 5. A consumed signal should still show that it was buffered

**Deck says:** `payment_captured   09:13:58   stripe_webhook   buffered`, on an
instance that has since advanced to step 4 — and the race slide four slides later
calls back to "that `buffered` flag".
**Package does:** the signal table prints `BUFFERED` only when `consumed_at` is
null, so a consumed signal can never display it. The deck's row is a state the
current output cannot produce.

Buffered-ness is currently inferred from a null `consumed_at` and is destroyed
the moment the signal is consumed. Record it: a `buffered_at` (or a boolean set
at insert), and render `buffered → consumed 09:13:58`. It's also genuinely
useful — "how often does the webhook beat us" is a real operational question.

## 6. Decide what `timeoutSeconds()` means

**Deck says**, narrating the step contract: "two knobs — how long this step is
allowed to take, and how many attempts it gets."
**Package does:** reads `timeoutSeconds()` only in the await-parking branch
(`WorkflowEngine.php:205`). It bounds how long a step may *wait*, not how long it
may *run*.

Either add an execution timeout or reword the slide. Rewording is probably right
— a running step's wall clock is the worker's business, and `maxAttempts()`
already covers a step that dies — but decide before the talk, because it's an
obvious question from the floor.

## 7. Make `examples/OrderFulfilment.php` the deck's workflow

Today: three steps, named `order_fulfilment`. The deck: eight steps, named
`fulfil_order`, with `RiskGateStep`, `ManualReviewStep`, `ReviewDecisionStep` and
`DeliveryFollowUpStep` carrying the `goto`, the human wait and the 48-hour sleep.

The example is the only runnable version of the thing on screen. Someone who
liked the talk will open that file first, and it should be the same code.
`ExamplesTest` requires parameterless step constructors — the deck's
`ChargeCustomerStep` takes a gateway, so it needs the usual comment-out-the-side-
effect treatment.

## 8. Show the webhook round trip end to end

The deck's "after" webhook finds its instance via
`$event->metadata['order_id']`, but `ChargeCustomerStep::handle()` never sends
metadata when it authorises. The loop doesn't close on screen.

Fix in the example and the docs: pass `metadata: ['order_id' => ...]` at
authorise time, so "how does the webhook know which aggregate this is?" has a
visible answer. Add it to `docs/signals.md`.

## 9. Write the walkthrough

The closing slide points at
`juststeveking.com/articles/building-an-order-fulfilment-workflow`. It doesn't
exist. It should be the eight-step fulfilment example, end to end, published
before the talk — it's the only URL on the final slide besides the package name.

## 10. `workflow:instances` should answer the stopped-system question

The show slide's presenter note sells `workflow:instances --status=awaiting` as
"everything currently stopped in your entire system — a genuinely new question
you couldn't previously ask". Worth checking the columns actually carry
`awaiting_signal` and the waiting duration once 3 lands, or the answer is a list
of ULIDs.

## 11. Document wiring `StepTimedOut`

Presenter note: "`StepTimedOut` is the one to wire up first. It is the alert you
never had." `docs/events.md` should have that listener written out.

---

**Release shape.** Items 1–5 add columns and change command output on top of a
tagged `v1.0.0`. Do the three schema items (2, 3, 5) in one migration and one
pass through the thread in `AGENTS.md` (migration → `Models\WorkflowInstance` →
`Domain\WorkflowInstance` → `EloquentWorkflowRepository` → `WorkflowSchemaTest`),
then ship `v1.1.0` with an `UPGRADE.md` note. Confirm the package is installable
from Packagist as `composer require juststeveking/workflow-engine` — the deck
puts that line on screen.
