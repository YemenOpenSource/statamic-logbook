<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Support;

use Illuminate\Support\Facades\DB;

/**
 * Per-user preference store for the logbook CP utility.
 *
 * Why a dedicated table (logbook_user_prefs) in the **logbook** DB and
 * not the main project DB?
 *
 *   1. Self-contained addon. The addon already opens its own
 *      connection for logs; piggy-backing those settings on the host
 *      app's DB would force every install to grant this addon write
 *      access to their prod data. That's not a trade-off we want to
 *      impose — especially since some teams explicitly separate logs
 *      out to protect prod.
 *   2. Clean uninstall. Dropping the logbook DB removes everything the
 *      addon wrote. Putting prefs in the main DB orphans rows.
 *   3. Trivial volume. A per-user JSON blob is small enough that the
 *      extra connection cost is negligible.
 *
 * Storage shape
 * -------------
 * One row per user_id with a JSON `prefs` column holding the full blob
 * (density, saved filter presets, per-page default, pinned searches,
 * etc.). Reads/writes go through a scalar `key → value` API so callers
 * never need to know the on-disk shape.
 *
 * Fallback
 * --------
 * When the table doesn't exist yet (pre-upgrade install) or when the
 * logbook DB is unreachable, all public methods fail soft — get() hands
 * back the caller's default, set() returns false. The UI layer keeps
 * using localStorage as a zero-config fallback either way.
 */
class UserPrefsRepository
{
    /** @var string */
    public const TABLE = 'logbook_user_prefs';

    /** @var array<string, array<string, mixed>|null> Request-scoped cache keyed by user id. */
    protected static array $cache = [];

    /**
     * Fetch a single preference for a user. Returns $default on any
     * failure or missing key — this method never throws.
     */
    public static function get(string $userId, string $key, mixed $default = null): mixed
    {
        $blob = self::load($userId);
        if ($blob === null) {
            return $default;
        }
        return array_key_exists($key, $blob) ? $blob[$key] : $default;
    }

    /**
     * Store a single preference. Returns true on success, false if the
     * table isn't available or the write fails.
     */
    public static function set(string $userId, string $key, mixed $value): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }

        $conn = DbConnectionResolver::resolve();

        try {
            $blob = self::load($userId) ?? [];
            $blob[$key] = $value;
            $encoded = json_encode($blob, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return false;
            }

            DB::connection($conn)->table(self::TABLE)->updateOrInsert(
                ['user_id' => $userId],
                [
                    'prefs'      => $encoded,
                    'updated_at' => now(),
                ]
            );

            self::$cache[$userId] = $blob;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return every preference for a user as an associative array.
     * Empty array when no row exists or the table is unavailable.
     *
     * @return array<string, mixed>
     */
    public static function all(string $userId): array
    {
        return self::load($userId) ?? [];
    }

    /**
     * Remove a single key from a user's preference blob. Returns true
     * if the blob existed and was updated (even if the key wasn't
     * there). Returns false if the table is unavailable.
     */
    public static function forget(string $userId, string $key): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }

        $conn = DbConnectionResolver::resolve();

        try {
            $blob = self::load($userId);
            if ($blob === null) return true; // nothing to remove
            unset($blob[$key]);
            $encoded = json_encode($blob, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return false;
            }

            DB::connection($conn)->table(self::TABLE)->updateOrInsert(
                ['user_id' => $userId],
                [
                    'prefs'      => $encoded,
                    'updated_at' => now(),
                ]
            );

            self::$cache[$userId] = $blob;
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Internal: load + cache the full JSON blob for a user. Returns
     * null if the table is missing or the user has no row yet.
     *
     * @return array<string, mixed>|null
     */
    protected static function load(string $userId): ?array
    {
        $userId = trim($userId);
        if ($userId === '') {
            return null;
        }
        if (array_key_exists($userId, self::$cache)) {
            return self::$cache[$userId];
        }

        $conn = DbConnectionResolver::resolve();

        try {
            $raw = DB::connection($conn)
                ->table(self::TABLE)
                ->where('user_id', $userId)
                ->value('prefs');

            if ($raw === null) {
                return self::$cache[$userId] = [];
            }

            $decoded = json_decode((string) $raw, true);
            return self::$cache[$userId] = is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            // Table missing / DB down / malformed JSON — fall back silently.
            return self::$cache[$userId] = null;
        }
    }

    /**
     * Clear the request-scoped cache. Primarily useful in tests.
     */
    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
