<?php

namespace EmranAlhaddad\StatamicLogbook\Audit;

use Illuminate\Support\Facades\Event;
use Statamic\Entries\Entry;

class StatamicAuditSubscriber
{
    /** @var array<string, array> */
    private static array $entryBefore = [];

    public function __construct(
        private AuditRecorder $recorder,
        private ChangeDetector $detector
    ) {}

    public function subscribe(): void
    {
        if (!config('logbook.audit_logs.enabled', true)) return;

        $events = (array) config('logbook.audit_logs.events', []);

        foreach ($events as $eventClass) {
            if (!is_string($eventClass) || !class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function ($event) use ($eventClass) {
                $this->handle($eventClass, $event);
            });
        }
    }

    private function handle(string $eventClass, object $event): void
    {
        // Entry diff support (best effort across versions)
        if ($this->isEntryEvent($event)) {
            $this->handleEntry($eventClass, $event);
            return;
        }

        // Everything else: record minimal audit line (no diff yet)
        $this->recordGeneric($eventClass, $event);
    }

    private function isEntryEvent(object $event): bool
    {
        return property_exists($event, 'entry') && ($event->entry instanceof Entry);
    }

    private function handleEntry(string $eventClass, object $event): void
    {
        /** @var Entry $entry */
        $entry = $event->entry;

        // Capture "before" snapshot on EntrySaving (if included in config)
        if ($eventClass === \Statamic\Events\EntrySaving::class) {
            self::$entryBefore[$this->entryKey($entry)] = $this->entrySnapshot($entry);
            return;
        }

        // Deleted: record without changes
        if ($eventClass === \Statamic\Events\EntryDeleted::class) {
            $this->record([
                'action' => 'statamic.' . class_basename($eventClass),
                'subject_type' => 'entry',
                'subject_id' => (string) $entry->id(),
                'subject_handle' => (string) $entry->slug(),
                'subject_title' => (string) ($entry->get('title') ?? $entry->slug()),
                'changes' => null,
                'meta' => [
                    'collection' => $entry->collectionHandle(),
                    'site' => $entry->site()?->handle(),
                    'uri' => $entry->uri(),
                ],
            ]);
            return;
        }

        // Saved/Created/etc: record with diff if we have "before"
        $after = $this->entrySnapshot($entry);
        $before = self::$entryBefore[$this->entryKey($entry)] ?? [];

        $changes = empty($before)
            ? $this->createdChanges($after)
            : $this->detector->diff($before, $after);

        // If nothing changed (common on save), you can skip:
        if (empty($changes)) return;

        $this->record([
            'action' => 'statamic.' . class_basename($eventClass),
            'subject_type' => 'entry',
            'subject_id' => (string) $entry->id(),
            'subject_handle' => (string) $entry->slug(),
            'subject_title' => (string) ($entry->get('title') ?? $entry->slug()),
            'changes' => $changes,
            'meta' => [
                'collection' => $entry->collectionHandle(),
                'site' => $entry->site()?->handle(),
                'uri' => $entry->uri(),
            ],
        ]);
    }

    private function recordGeneric(string $eventClass, object $event): void
    {
        // best-effort subject inference (no diff yet)
        $subject = $this->inferSubject($event);

        $this->record([
            'action' => 'statamic.' . class_basename($eventClass),
            'subject_type' => $subject['type'] ?? 'statamic',
            'subject_id' => $subject['id'] ?? null,
            'subject_handle' => $subject['handle'] ?? null,
            'subject_title' => $subject['title'] ?? null,
            'changes' => null,
            'meta' => [
                'event' => $eventClass,
            ],
        ]);
    }

    private function inferSubject(object $event): array
    {
        // Common Statamic event properties (best effort)
        foreach (['asset', 'term', 'taxonomy', 'nav', 'collection', 'user', 'globalSet', 'globals'] as $prop) {
            if (!property_exists($event, $prop) || !$event->$prop) continue;
            $obj = $event->$prop;

            // Try common methods
            $id = method_exists($obj, 'id') ? (string) $obj->id() : (method_exists($obj, 'handle') ? (string) $obj->handle() : null);
            $handle = method_exists($obj, 'handle') ? (string) $obj->handle() : null;
            $title = method_exists($obj, 'title') ? (string) $obj->title() : (method_exists($obj, 'get') ? (string) ($obj->get('title') ?? '') : null);

            return [
                'type' => $prop,
                'id' => $id,
                'handle' => $handle,
                'title' => $title ?: null,
            ];
        }

        return ['type' => 'statamic'];
    }

    private function record(array $payload): void
    {
        $user = function_exists('auth') ? auth()->user() : null;
        $req  = function_exists('request') ? request() : null;

        $payload['user_id']    = $user ? (string) ($user->id ?? null) : null;
        $payload['user_email'] = $user ? ($user->email ?? null) : null;
        $payload['ip']         = $req?->ip();
        $payload['user_agent'] = $req?->userAgent();

        $this->recorder->record($payload);
    }

    private function entrySnapshot(Entry $entry): array
    {
        $data = $entry->data()?->all() ?? [];
        $data['title'] = $entry->get('title');
        $data['slug'] = $entry->slug();

        return $this->normalize($data);
    }

    private function normalize(array $data): array
    {
        return json_decode(json_encode($data, JSON_UNESCAPED_UNICODE), true) ?: [];
    }

    private function entryKey(Entry $entry): string
    {
        return (string) $entry->id() . '|' . (string) ($entry->site()?->handle());
    }

    private function createdChanges(array $after): array
    {
        $changes = [];
        foreach ($after as $k => $v) {
            $changes[$k] = ['from' => null, 'to' => $v];
        }
        return $changes;
    }
}
