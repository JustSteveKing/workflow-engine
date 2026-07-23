<?php

declare(strict_types=1);

use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;

it('registers and resolves workflow definitions by name', function (): void {
    $registry = new WorkflowRegistry();
    $registry->register(AutoWorkflowDefinition::class);

    expect($registry->has(AutoWorkflowDefinition::name()))->toBeTrue()
        ->and($registry->get(AutoWorkflowDefinition::name()))->toBe(AutoWorkflowDefinition::class)
        ->and($registry->has('nope'))->toBeFalse();
});

it('lists all registered names', function (): void {
    $registry = new WorkflowRegistry();
    $registry->register(AutoWorkflowDefinition::class);
    $registry->register(AwaitWorkflowDefinition::class);

    expect($registry->all())->toEqualCanonicalizing([
        AutoWorkflowDefinition::name(),
        AwaitWorkflowDefinition::name(),
    ]);
});

it('throws when resolving an unknown workflow', function (): void {
    $registry = new WorkflowRegistry();

    expect(fn() => $registry->get('unknown'))->toThrow(InvalidArgumentException::class);
});
