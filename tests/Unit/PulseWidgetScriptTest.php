<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PulseWidgetScriptTest extends TestCase
{
    public function test_pulse_widget_filter_listener_is_bound_once(): void
    {
        $path = dirname(__DIR__, 2).'/resources/views/cp/widgets/logbook_pulse.blade.php';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('window.__logbookPulseFilterBound', $contents);
        $this->assertStringContainsString('if (window.__logbookPulseFilterBound) return;', $contents);
    }
}
