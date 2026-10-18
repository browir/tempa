import { reactive, watch } from 'vue';

/**
 * Preferensi per perangkat (localStorage `tempa:prefs`). Bukan data latihan, jadi tidak perlu ke server.
 * restSeconds: lama istirahat bawaan setelah set ditandai selesai (0 = timer mati).
 */
const KEY = 'tempa:prefs';
const DEFAULTS = { restSeconds: 90 };

function read() {
    try {
        return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(KEY) || '{}') };
    } catch {
        return { ...DEFAULTS };
    }
}

const prefs = reactive(read());

watch(prefs, (value) => {
    try {
        localStorage.setItem(KEY, JSON.stringify(value));
    } catch {
        // Penyimpanan diblokir: preferensi berlaku selama tab terbuka.
    }
});

export function usePrefs() {
    return prefs;
}
