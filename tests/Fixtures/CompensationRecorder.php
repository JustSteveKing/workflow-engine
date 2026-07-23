<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

final class CompensationRecorder
{
    /** @var list<string> */
    public static array $order = [];

    public static function reset(): void
    {
        self::$order = [];
    }

    public static function record(string $label): void
    {
        self::$order[] = $label;
    }
}
