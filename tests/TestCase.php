<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests;

use Illuminate\Foundation\Application;
use JustSteveKing\WorkflowEngine\WorkflowEngineServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            WorkflowEngineServiceProvider::class,
        ];
    }
}
