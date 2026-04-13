<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

final class ConfigDefaultsTest extends TestCase
{
    public function test_audit_discovery_defaults_to_false(): void
    {
        new Application(dirname(__DIR__, 2));

        if (! function_exists('env')) {
            function env(string $key, mixed $default = null): mixed
            {
                return $default;
            }
        }

        /** @var array<string,mixed> $config */
        $config = require dirname(__DIR__, 2).'/config/logbook.php';
        $this->assertArrayHasKey('audit_logs', $config);
        $this->assertArrayHasKey('discover_events', $config['audit_logs']);
        $this->assertFalse((bool) $config['audit_logs']['discover_events']);
    }
}
