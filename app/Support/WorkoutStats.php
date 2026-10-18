<?php

namespace App\Support;

/**
 * Hitungan domain murni. Menerima set berupa model atau array (kunci: kind, weight_g, reps, done_at).
 */
class WorkoutStats
{
    /** Jenis set yang dihitung ke volume; pemanasan tidak. */
    public const VOLUME_KINDS = ['normal', 'drop', 'failure'];

    /** Volume dalam gram·rep = Σ(berat × reps) set selesai non-pemanasan. */
    public static function volume(iterable $sets): int
    {
        $total = 0;
        foreach ($sets as $set) {
            if (! self::counts($set)) {
                continue;
            }
            $total += (int) data_get($set, 'weight_g') * (int) data_get($set, 'reps');
        }

        return $total;
    }

    /** Set yang dihitung: sudah selesai, bukan pemanasan, ada berat & reps. */
    public static function counts(mixed $set): bool
    {
        return data_get($set, 'done_at') !== null
            && in_array(data_get($set, 'kind', 'normal'), self::VOLUME_KINDS, true)
            && (int) data_get($set, 'weight_g') > 0
            && (int) data_get($set, 'reps') > 0;
    }

    /**
     * Estimasi 1RM (Epley): berat × (1 + reps / 30), hanya untuk reps 1–12 (di luar itu tidak andal).
     * Hasil dalam gram, dibulatkan.
     */
    public static function estimatedOneRepMax(?int $weightG, ?int $reps): ?int
    {
        if (! $weightG || ! $reps || $reps < 1 || $reps > 12) {
            return null;
        }
        if ($reps === 1) {
            return $weightG;
        }

        return (int) round($weightG * (1 + $reps / 30));
    }
}
