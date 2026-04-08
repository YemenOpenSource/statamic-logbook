<?php

namespace EmranAlhaddad\StatamicLogbook;

use Illuminate\Support\Facades\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Log\Events\MessageLogged;
use Monolog\Level;
use Statamic\Facades\Utility;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

use EmranAlhaddad\StatamicLogbook\Console\InstallCommand;
use EmranAlhaddad\StatamicLogbook\Console\PruneCommand;
use EmranAlhaddad\StatamicLogbook\Http\Controllers\LogbookUtilityController;
use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogbookRequestContext;
use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;
use EmranAlhaddad\StatamicLogbook\SystemLogs\DbSystemLogHandler;

class LogbookServiceProvider extends AddonServiceProvider
{
    protected static bool $booted = false;
    protected static bool $systemLogsHooked = false;

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__ . '/../config/logbook.php', 'logbook');

        $this->app->singleton(AuditRecorder::class);
        $this->app->singleton(ChangeDetector::class);
    }

    public function boot(): void
    {
        parent::boot();
        $this->bootLogbook();
    }

    public function bootAddon(): void
    {
        $this->bootLogbook();
    }

    protected function bootLogbook(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-logbook');

        $this->publishes([
            __DIR__ . '/../config/logbook.php' => config_path('logbook.php'),
        ], 'logbook-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PruneCommand::class,
            ]);
        }

        $this->registerCpMiddleware();

        if (config('logbook.audit_logs.enabled', true) && class_exists(\Statamic\Statamic::class)) {
            (new StatamicAuditSubscriber(
                recorder: $this->app->make(AuditRecorder::class),
                detector: $this->app->make(ChangeDetector::class),
            ))->subscribe();
        }

        $this->registerSystemLogs();
        $this->registerPermissions();
        $this->bootCpUtility();
    }

    protected function registerSystemLogs(): void
    {
        if (self::$systemLogsHooked || !config('logbook.system_logs.enabled', true)) {
            return;
        }

        self::$systemLogsHooked = true;

        $levelName = (string) config('logbook.system_logs.level', 'debug');
        $bubble = (bool) config('logbook.system_logs.bubble', true);

        try {
            $level = Level::fromName($levelName);
        } catch (\Throwable $e) {
            $level = Level::Debug;
        }

        Event::listen(MessageLogged::class, function (MessageLogged $event) use ($level, $bubble) {
            if ($this->shouldSkipSystemLogEvent($event)) {
                return;
            }

            $handler = new DbSystemLogHandler(
                level: $level,
                bubble: $bubble,
                channel: $event->channel ?? 'logbook'
            );

            $handler->recordMessage(
                level: (string) $event->level,
                message: (string) $event->message,
                context: is_array($event->context) ? $event->context : []
            );
        });
    }

    protected function shouldSkipSystemLogEvent(MessageLogged $event): bool
    {
        $channel = (string) ($event->channel ?? '');
        $message = (string) $event->message;

        $ignoredChannels = array_map('strtolower', (array) config('logbook.system_logs.ignore_channels', [
            'deprecations',
        ]));
        if ($channel !== '' && in_array(strtolower($channel), $ignoredChannels, true)) {
            return true;
        }

        foreach ((array) config('logbook.system_logs.ignore_message_contains', []) as $needle) {
            if (is_string($needle) && $needle !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function registerPermissions(): void
    {
        Permission::register('view logbook')->label('View Logbook');
        Permission::register('export logbook')->label('Export Logbook');
    }

    protected function registerCpMiddleware(): void
    {
        if (!class_exists(LogbookRequestContext::class)) return;

        try {
            Router::pushMiddlewareToGroup('statamic.cp', LogbookRequestContext::class);
        } catch (\Throwable $e) {
        }
    }

    protected function bootCpUtility(): void
    {
        Utility::extend(function () {
            Utility::register('logbook')
                ->title('Logbook')
                ->navTitle('Logbook')
                ->description('System logs + user audit logs')
                ->icon($this->svgIcon('logbook'))
                ->action(LogbookUtilityController::class)
                ->routes(function ($router) {
                    $router->get('/system', [LogbookUtilityController::class, 'system'])
                        ->name('system')
                        ->middleware('can:view logbook');

                    $router->get('/audit', [LogbookUtilityController::class, 'audit'])
                        ->name('audit')
                        ->middleware('can:view logbook');

                    $router->get('/system/export.csv', [LogbookUtilityController::class, 'exportSystemCsv'])
                        ->name('system.export')
                        ->middleware('can:export logbook');

                    $router->get('/audit/export.csv', [LogbookUtilityController::class, 'exportAuditCsv'])
                        ->name('audit.export')
                        ->middleware('can:export logbook');
                });
        });
    }


    protected function svgIcon(string $name): string
    {
        $path = __DIR__ . '/../resources/svg/' . $name . '.svg';
        return file_exists($path) ? file_get_contents($path) : '';
    }
}
