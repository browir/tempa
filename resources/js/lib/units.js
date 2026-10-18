// Angka disimpan sebagai integer satuan terkecil: berat gram, durasi detik, jarak meter.
// Konversi ke kg / menit / km hanya di tampilan.

const nf = (max, min = 0) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: min, maximumFractionDigits: max });
const kgFormat = nf(2);
const kmFormat = nf(2);
const speedFormat = nf(1, 1);

/** 62500 → "62,5" */
export function formatKg(grams) {
    if (grams == null) return '';
    return kgFormat.format(grams / 1000);
}

/** "62,5" / "62.5" / 62.5 → 62500 (null bila tidak valid). */
export function parseKg(value) {
    if (value == null || value === '') return null;
    const n = typeof value === 'number' ? value : Number(String(value).trim().replace(',', '.'));
    return Number.isFinite(n) && n >= 0 ? Math.round(n * 1000) : null;
}

/** 95 → "1:35", 3725 → "1:02:05" */
export function formatDuration(seconds) {
    if (seconds == null) return '';
    const s = Math.max(0, Math.round(seconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const pad = (n) => String(n).padStart(2, '0');
    return h ? `${h}:${pad(m)}:${pad(s % 60)}` : `${m}:${pad(s % 60)}`;
}

/** 24600 → "24,6" (km) */
export function formatKm(meters) {
    if (meters == null) return '';
    return kmFormat.format(meters / 1000);
}

/** Kecepatan rata-rata km/jam dari jarak & durasi — turunan, tidak disimpan. */
export function formatSpeed(meters, seconds) {
    if (!meters || !seconds) return '';
    return speedFormat.format(meters / 1000 / (seconds / 3600));
}

/** Tempo lari/jalan menit per km: 5:12 */
export function formatPace(meters, seconds) {
    if (!meters || !seconds) return '';
    return formatDuration(seconds / (meters / 1000));
}
