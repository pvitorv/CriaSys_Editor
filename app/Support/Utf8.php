<?php

namespace App\Support;

class Utf8
{
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $fromLatin1 = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
        if ($fromLatin1 !== false && mb_check_encoding($fromLatin1, 'UTF-8')) {
            return $fromLatin1;
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $clean !== false ? $clean : '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function cleanArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = self::clean($value);
            } elseif (is_array($value)) {
                $data[$key] = self::cleanArray($value);
            }
        }

        return $data;
    }
}
