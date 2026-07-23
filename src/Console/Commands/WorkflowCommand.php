<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use Illuminate\Console\Command;

abstract class WorkflowCommand extends Command
{
    protected function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        return is_scalar($value) ? (string) $value : '';
    }

    protected function stringArgumentOrNull(string $key): ?string
    {
        $value = $this->argument($key);

        return is_scalar($value) ? (string) $value : null;
    }

    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    protected function intOption(string $key, int $default): int
    {
        $value = $this->option($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Parse a JSON-object option into a string-keyed array. Returns false when
     * the value is present but not a valid JSON object.
     *
     * @return array<string, mixed>|false
     */
    protected function jsonObjectOption(string $key): array|false
    {
        $raw = $this->stringOption($key);

        if (null === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return false;
        }

        $result = [];
        foreach ($decoded as $property => $value) {
            $result[(string) $property] = $value;
        }

        return $result;
    }
}
