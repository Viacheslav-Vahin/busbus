<?php

namespace App\Support;

class WP
{
    // Акуратно розпарсити PHP-serialized рядок; якщо не serialized — повернути як є
    public static function maybeUnserialize($value) {
        if (!is_string($value)) return $value;
        $trim = trim($value);

        // грубий чек на serialized: починається з a:|s:|i:|b:|O:|C:|d:
        if (!preg_match('/^(a|s|i|b|O|C|d):/i', $trim)) {
            return $value;
        }
        $res = @unserialize($trim);
        return $res === false && $trim !== 'b:0;' ? $value : $res;
    }

    public static function cleanPhone(?string $phone): ?string {
        if (!$phone) return null;
        $p = preg_replace('/[^+0-9]/', '', $phone);
        return $p !== '' ? $p : null;
    }

    public static function toDateTime(?string $v): ?string {
        if (!$v) return null;
        $v = trim($v);
        // приклади формату: "2025-07-05 12:00" або "2025-05-28 19:06"
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
