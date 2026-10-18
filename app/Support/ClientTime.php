<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Waktu dari klien (ISO 8601, biasanya UTC "…Z") → zona waktu aplikasi.
 * Perlu dikonversi eksplisit: Eloquent menyimpan Carbon apa adanya tanpa mengubah zonanya,
 * sedangkan kolom timestamp Postgres/SQLite tidak menyimpan zona.
 */
class ClientTime
{
    public static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
    }
}
