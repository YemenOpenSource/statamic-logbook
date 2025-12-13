<?php

namespace EmranAlhaddad\StatamicLogbook;

use Illuminate\Support\ServiceProvider;
use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;
use Illuminate\Support\Facades\Router;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogbookRequestContext;


class LogbookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }

        $this->registerCpMiddleware();
    }

    protected function registerCpMiddleware(): void
    {
        // Only if middleware exists
        if (! class_exists(LogbookRequestContext::class)) {
            return;
        }

        // Only register when CP logging is enabled (optional – if you have the flag)
        // if (!config('logbook.system_logs.enabled', true)) return;

        try {
            // Attach to Statamic Control Panel group (CP only)
            Router::pushMiddlewareToGroup('statamic.cp', LogbookRequestContext::class);
        } catch (\Throwable $e) {
            // Fail silently – don't break the app if a project has a different setup
            // (We can add a debug log later if needed)
        }
    }
}
