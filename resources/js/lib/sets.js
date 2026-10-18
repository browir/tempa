import { formatDuration, formatKg, formatKm, formatSpeed } from '@/lib/units';

/** Jenis set. Pemanasan tidak diberi nomor dan tidak dihitung volume. */
export const SET_KINDS = [
    { value: 'warmup', label: 'Pemanasan', badge: 'P' },
    { value: 'normal', label: 'Normal', badge: null },
    { value: 'drop', label: 'Drop', badge: 'D' },
    { value: 'failure', label: 'Gagal', badge: 'G' },
];

/** Isian per jenis gerakan (lihat CLAUDE.md §5). step dalam satuan simpan (gram/detik/meter). */
export function fieldsFor(exerciseKind) {
    switch (exerciseKind) {
        case 'bodyweight':
            return [
                { key: 'reps', label: 'Reps', type: 'int', step: 1 },
                { key: 'weight_g', label: 'Beban +', unit: 'kg', type: 'kg', step: 2500, optional: true },
            ];
        case 'time':
            return [{ key: 'duration_s', label: 'Durasi', type: 'duration', step: 15 }];
        case 'cardio':
            return [
                { key: 'distance_m', label: 'Jarak', unit: 'km', type: 'km', step: 1000 },
                { key: 'duration_s', label: 'Durasi', type: 'duration', step: 60 },
            ];
        default:
            return [
                { key: 'weight_g', label: 'Berat', unit: 'kg', type: 'kg', step: 2500 },
                { key: 'reps', label: 'Reps', type: 'int', step: 1 },
            ];
    }
}

/** Nilai satu isian untuk sel tabel. */
export function formatField(field, value) {
    if (value == null) return '';
    if (field.type === 'kg') return formatKg(value);
    if (field.type === 'km') return formatKm(value);
    if (field.type === 'duration') return formatDuration(value);
    return String(value);
}

/** Ringkasan satu set: "60 × 8", "+10 kg × 8", "1:30", "24,6 km · 58:12". */
export function describeSet(exerciseKind, set) {
    if (!set) return '';
    switch (exerciseKind) {
        case 'bodyweight':
            if (set.reps == null) return '';
            return set.weight_g ? `+${formatKg(set.weight_g)} kg × ${set.reps}` : `${set.reps} reps`;
        case 'time':
            return set.duration_s != null ? formatDuration(set.duration_s) : '';
        case 'cardio': {
            const parts = [];
            if (set.distance_m != null) parts.push(`${formatKm(set.distance_m)} km`);
            if (set.duration_s != null) parts.push(formatDuration(set.duration_s));
            return parts.join(' · ');
        }
        default:
            if (set.weight_g == null && set.reps == null) return '';
            return `${formatKg(set.weight_g ?? 0)} × ${set.reps ?? 0}`;
    }
}

/** Kecepatan rata-rata kardio, km/jam. */
export function cardioSpeed(set) {
    return formatSpeed(set?.distance_m, set?.duration_s);
}

/** Set sudah punya angka utama yang cukup untuk ditandai selesai. */
export function isFilled(exerciseKind, set) {
    return fieldsFor(exerciseKind)
        .filter((f) => !f.optional)
        .every((f) => set[f.key] != null && set[f.key] > 0);
}

/** Label nomor set: pemanasan "P", drop "D", gagal "G", selain itu nomor urut set kerja. */
export function setBadges(sets) {
    let n = 0;
    return sets.map((s) => {
        const kind = SET_KINDS.find((k) => k.value === s.kind);
        if (kind?.badge) return kind.badge;
        n += 1;
        return String(n);
    });
}

/* ---------- Statistik (cermin App\Support\WorkoutStats) ---------- */

const VOLUME_KINDS = ['normal', 'drop', 'failure'];

export function setCounts(set) {
    return set.done_at != null && VOLUME_KINDS.includes(set.kind) && set.weight_g > 0 && set.reps > 0;
}

/** Volume gram·rep. */
export function volume(sets) {
    return sets.reduce((sum, s) => (setCounts(s) ? sum + s.weight_g * s.reps : sum), 0);
}

/* ---------- Ketik di keypad ↔ nilai simpan ---------- */

/** Nilai simpan → teks yang sedang diketik. Durasi diketik sebagai digit h mm ss (mis. "13820" = 1:38:20). */
export function toText(field, value) {
    if (value == null) return '';
    if (field.type === 'kg' || field.type === 'km') return String(+(value / 1000).toFixed(3)).replace('.', ',');
    if (field.type === 'duration') {
        const h = Math.floor(value / 3600);
        const m = Math.floor((value % 3600) / 60);
        const s = value % 60;
        return String(h * 10000 + m * 100 + s);
    }
    return String(value);
}

/** Teks ketikan → nilai simpan (null bila kosong). */
export function fromText(field, text) {
    if (text === '' || text == null) return null;
    if (field.type === 'kg' || field.type === 'km') {
        const n = Number(String(text).replace(',', '.'));
        return Number.isFinite(n) ? Math.round(n * 1000) : null;
    }
    if (field.type === 'duration') {
        const digits = String(text).padStart(1, '0');
        const s = Number(digits.slice(-2));
        const m = Number(digits.slice(-4, -2) || 0);
        const h = Number(digits.slice(0, -4) || 0);
        return h * 3600 + m * 60 + s;
    }
    const n = parseInt(text, 10);
    return Number.isFinite(n) ? n : null;
}

/** Tampilan teks ketikan durasi: "530" → "5:30", "13820" → "1:38:20". */
export function durationTextDisplay(text) {
    const digits = String(text || '').padStart(3, '0');
    const s = digits.slice(-2);
    const m = digits.slice(-4, -2);
    const h = digits.slice(0, -4);
    return h ? `${Number(h)}:${m.padStart(2, '0')}:${s}` : `${Number(m)}:${s}`;
}
