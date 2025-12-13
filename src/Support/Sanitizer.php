<?php

namespace EmranAlhaddad\StatamicLogbook\Support;

class Sanitizer
{
    public static function maskArray(array $data): array
    {
        $keys = array_map('strtolower', (array) config('logbook.privacy.mask_keys', []));
        $mask = (string) config('logbook.privacy.mask_value', '[REDACTED]');

        return self::walk($data, $keys, $mask);
    }

    protected static function walk($value, array $keys, string $mask)
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = [];

        foreach ($value as $k => $v) {
            $kLower = is_string($k) ? strtolower($k) : $k;

            if (is_string($kLower) && in_array($kLower, $keys, true)) {
                $out[$k] = $mask;
                continue;
            }

            $out[$k] = is_array($v) ? self::walk($v, $keys, $mask) : $v;
        }

        return $out;
    }
}
