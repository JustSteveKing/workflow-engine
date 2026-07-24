<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowAdvanceCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowInstancesCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowListCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowPruneCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowRetryCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowShowCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowSignalCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowStartCommand;
use JustSteveKing\WorkflowEngine\Console\Commands\WorkflowTickCommand;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowRepositoryContract;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Repositories\EloquentWorkflowRepository;

final class WorkflowEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflow-engine.php', 'workflow-engine');

        $this->app->singleton(WorkflowRegistry::class, fn(): WorkflowRegistry => new WorkflowRegistry());

        $this->app->bind(WorkflowRepositoryContract::class, EloquentWorkflowRepository::class);

        $this->app->singleton(WorkflowEngine::class, fn(Application $app): WorkflowEngine => new WorkflowEngine(
            repository: $app->make(WorkflowRepositoryContract::class),
            registry: $app->make(WorkflowRegistry::class),
            container: $app,
            events: $app->make(Dispatcher::class),
            bus: $app->make(BusDispatcher::class),
            config: $app->make(Repository::class),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'workflow-engine-migrations');

            $this->publishes([
                __DIR__ . '/../config/workflow-engine.php' => $this->app->configPath('workflow-engine.php'),
            ], 'workflow-engine-config');

            $this->commands([
                WorkflowListCommand::class,
                WorkflowShowCommand::class,
                WorkflowInstancesCommand::class,
                WorkflowStartCommand::class,
                WorkflowSignalCommand::class,
                WorkflowAdvanceCommand::class,
                WorkflowRetryCommand::class,
                WorkflowTickCommand::class,
                WorkflowPruneCommand::class,
            ]);
        }
    }
}
