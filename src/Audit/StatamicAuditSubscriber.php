<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Audit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

/**
 * Subscribes to curated Statamic mutation events and records audit rows.
 *
 * Event list resolution happens in three layers, in precedence order
 * (later layers extend earlier ones):
 *
 *   1. {@see EventMap::curatedEvents()} — per-major default allow-list.
 *   2. `config('logbook.audit_logs.events')` — user-added allow-list.
 *   3. (optional) Discovery scan of `vendor/statamic/cms/src/Events`
 *      when `logbook.audit_logs.discover_events` is true.
 *
 * The resolved list is then filtered through:
 *
 *   - {@see class_exists()} — drops events that do not exist in the
 *     running Statamic major (safe across 3/4/5/6 without fatals).
 *   - `config('logbook.audit_logs.exclude_events')` merged with
 *     {@see EventMap::excludedEvents()} — hard block-list.
 *
 * The handler code path intentionally avoids a strict `use` import of
 * {@see \Statamic\Entries\Entry}, because that would trigger autoload
 * at first class reference. We use `instanceof` against the fully-
 * qualified name which PHP resolves without autoloading when the
 * operand is null / non-object — and guards around property access.
 */
class StatamicAuditSubscriber
{
    /** @var array<string, array> */
    private static array $entryBefore = [];

    public function __construct(
        private AuditRecorder $recorder,
        private ChangeDetector $detector
    ) {
    }

    /**
     * Wire event listeners for every resolved, existing event class.
     */
    public function subscribe(): void
    {
        if (! (bool) config('logbook.audit_logs.enabled', true)) {
            return;
        }

        $excludedMap = array_fill_keys($this->excludedEventClasses(), true);

        foreach ($this->eventsToListen() as $eventClass) {
            if (! is_string($eventClass) || $eventClass === '') {
                continue;
            }
            if (isset($excludedMap[$eventClass])) {
                continue;
            }
            if (! class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function ($event) use ($eventClass): void {
                $this->handle($eventClass, $event);
            });
        }
    }

    /**
     * Resolve the effective event allow-list for the running Statamic.
     *
     * Order:
     *   - curated defaults (unless `use_curated_defaults` is false)
     *   - user-configured additions
     *   - discovered events when `discover_events` is true
     *
     * @return list<string>
     */
    private function eventsToListen(): array
    {
        $useCurated = (bool) config('logbook.audit_logs.use_curated_defaults', true);

        $curated = $useCurated ? EventMap::curatedEvents() : [];

        $configured = array_values(array_filter(
            (array) config('logbook.audit_logs.events', []),
            static fn ($e): bool => is_string($e) && $e !== ''
        ));

        $discovered = (bool) config('logbook.audit_logs.discover_events', false)
            ? $this->discoverEvents()
            : [];

        return array_values(array_unique(array_merge($curated, $configured, $discovered)));
    }

    /**
     * Exclude list: EventMap's per-major exclude + user-configured extra.
     *
     * @return list<string>
     */
    private function excludedEventClasses(): array
    {
        $curated = EventMap::excludedEvents();

        $configured = array_values(array_filter(
            (array) config('logbook.audit_logs.exclude_events', []),
            static fn ($e): bool => is_string($e) && $e !== ''
        ));

        return array_values(array_unique(array_merge($curated, $configured)));
    }

    /**
     * Discover event classes by scanning the installed statamic/cms package.
     *
     * Only called when `logbook.audit_logs.discover_events` is true.
     *
     * @return list<string>
     */
    private function discoverEvents(): array
    {
        if (! function_exists('base_path')) {
            return [];
        }

        $vendorEventsDir = base_path('vendor/statamic/cms/src/Events');
        $events = [];

        if (! is_dir($vendorEventsDir)) {
            return [];
        }

        foreach (File::glob($vendorEventsDir . '/*.php') as $file) {
            $class = 'Statamic\\Events\\' . pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($class)) {
                $events[] = $class;
            }
        }

        return array_values(array_unique($events));
    }

    private function handle(string $eventClass, object $event): void
    {
        if ($this->isEntryEvent($event)) {
            $this->handleEntry($eventClass, $event);

            return;
        }

        $this->recordGeneric($eventClass, $event);
    }

    private function isEntryEvent(object $event): bool
    {
        if (! property_exists($event, 'entry')) {
            return false;
        }
        $entry = $event->entry ?? null;
        if (! is_object($entry)) {
            return false;
        }

        // When Statamic is installed, class_exists() will autoload
        // Entry once and subsequent calls are free. When it is NOT
        // installed (e.g. dead-code defensive path), autoload returns
        // false and we fall back to duck-typing against the shape the
        // rest of this method requires.
        return class_exists(\Statamic\Entries\Entry::class)
            ? $entry instanceof \Statamic\Entries\Entry
            : (method_exists($entry, 'id') && method_exists($entry, 'slug'));
    }

    private function handleEntry(string $eventClass, object $event): void
    {
        $entry = $event->entry;
        $entryKey = $this->entryKey($entry);

        // Capture the "before" snapshot on EntrySaving if the user opted into it.
        if ($eventClass === 'Statamic\\Events\\EntrySaving') {
            self::$entryBefore[$entryKey] = $this->entrySnapshot($entry);

            return;
        }

        // Deleted: record without a diff.
        if ($eventClass === 'Statamic\\Events\\EntryDeleted') {
            $this->record([
                'action' => 'statamic.entry.deleted',
                'subject_type' => 'entry',
                'subject_id' => (string) $entry->id(),
                'subject_handle' => (string) $entry->slug(),
                'subject_title' => (string) ($entry->get('title') ?? $entry->slug()),
                'changes' => null,
                'meta' => [
                    'raw_event' => class_basename($eventClass),
                    'operation' => 'deleted',
                    'collection' => method_exists($entry, 'collectionHandle') ? $entry->collectionHandle() : null,
                    'site' => method_exists($entry, 'site') ? $entry->site()?->handle() : null,
                    'uri' => method_exists($entry, 'uri') ? $entry->uri() : null,
                ],
            ]);
            unset(self::$entryBefore[$entryKey]);

            return;
        }

        // Saved / Created: diff against any captured "before" snapshot.
        $after = $this->entrySnapshot($entry);
        $before = self::$entryBefore[$entryKey] ?? [];
        unset(self::$entryBefore[$entryKey]);

        $changes = empty($before)
            ? $this->createdChanges($after)
            : $this->detector->diff($before, $after);

        if (empty($changes)) {
            return;
        }

        $operation = empty($before) ? 'created' : 'updated';

        $this->record([
            'action' => 'statamic.entry.' . $operation,
            'subject_type' => 'entry',
            'subject_id' => (string) $entry->id(),
            'subject_handle' => (string) $entry->slug(),
            'subject_title' => (string) ($entry->get('title') ?? $entry->slug()),
            'changes' => $changes,
            'meta' => [
                'raw_event' => class_basename($eventClass),
                'operation' => $operation,
                'collection' => method_exists($entry, 'collectionHandle') ? $entry->collectionHandle() : null,
                'site' => method_exists($entry, 'site') ? $entry->site()?->handle() : null,
                'uri' => method_exists($entry, 'uri') ? $entry->uri() : null,
            ],
        ]);
    }

    private function recordGeneric(string $eventClass, object $event): void
    {
        $subject = $this->inferSubject($event);
        $operation = $this->operationFromEventClass($eventClass);
        $subjectType = $subject['type'] ?? 'statamic';

        $this->record([
            'action' => 'statamic.' . $subjectType . '.' . $operation,
            'subject_type' => $subjectType,
            'subject_id' => $subject['id'] ?? null,
            'subject_handle' => $subject['handle'] ?? null,
            'subject_title' => $subject['title'] ?? null,
            'changes' => null,
            'meta' => [
                'raw_event' => class_basename($eventClass),
                'operation' => $operation,
                'event_class' => $eventClass,
            ],
        ]);
    }

    private function operationFromEventClass(string $eventClass): string
    {
        $name = class_basename($eventClass);
        if (str_ends_with($name, 'Deleted')) {
            return 'deleted';
        }
        if (str_ends_with($name, 'Created')) {
            return 'created';
        }
        if (str_ends_with($name, 'Saved')) {
            return 'updated';
        }
        if (str_ends_with($name, 'Saving')) {
            return 'updating';
        }

        return 'event';
    }

    /**
     * @return array{type:string, id:?string, handle:?string, title:?string}
     */
    private function inferSubject(object $event): array
    {
        foreach (['asset', 'term', 'taxonomy', 'nav', 'collection', 'user', 'globalSet', 'globals'] as $prop) {
            if (! property_exists($event, $prop)) {
                continue;
            }
            $obj = $event->$prop ?? null;
            if (! is_object($obj)) {
                continue;
            }

            $id = method_exists($obj, 'id')
                ? (string) $obj->id()
                : (method_exists($obj, 'handle') ? (string) $obj->handle() : null);
            $handle = method_exists($obj, 'handle') ? (string) $obj->handle() : null;
            $title = method_exists($obj, 'title')
                ? (string) $obj->title()
                : (method_exists($obj, 'get') ? (string) ($obj->get('title') ?? '') : null);

            return [
                'type' => $prop,
                'id' => $id,
                'handle' => $handle,
                'title' => $title !== '' ? $title : null,
            ];
        }

        return ['type' => 'statamic', 'id' => null, 'handle' => null, 'title' => null];
    }

    private function record(array $payload): void
    {
        $user = function_exists('auth') ? auth()->user() : null;
        $req = function_exists('request') ? request() : null;

        $payload['user_id'] = $user ? (string) ($user->id ?? null) : null;
        $payload['user_email'] = $user ? ($user->email ?? null) : null;
        $payload['ip'] = $req?->ip();
        $payload['user_agent'] = $req?->userAgent();

        $this->recorder->record($payload);
    }

    private function entrySnapshot(object $entry): array
    {
        $data = method_exists($entry, 'data') ? ($entry->data()?->all() ?? []) : [];
        if (method_exists($entry, 'get')) {
            $data['title'] = $entry->get('title');
        }
        if (method_exists($entry, 'slug')) {
            $data['slug'] = $entry->slug();
        }

        return $this->normalize($data);
    }

    private function normalize(array $data): array
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (! is_string($encoded)) {
            return [];
        }
        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function entryKey(object $entry): string
    {
        $id = method_exists($entry, 'id') ? (string) $entry->id() : '';
        $site = (method_exists($entry, 'site') && is_object($entry->site()))
            ? (string) ($entry->site()?->handle() ?? '')
            : '';

        return $id . '|' . $site;
    }

    /**
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function createdChanges(array $after): array
    {
        $changes = [];
        foreach ($after as $k => $v) {
            $changes[$k] = ['from' => null, 'to' => $v];
        }

        return $changes;
    }
}
