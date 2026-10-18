import { computed, ref, watch } from 'vue';
import { pushToast } from '@/composables/useToast';
import { notify, requestNotify } from '@/lib/notify';

/**
 * Timer istirahat antar-set. Disimpan sebagai waktu akhir mutlak (`ends_at`), bukan hitung mundur,
 * jadi tetap benar walau tab tidur, layar mati sebentar, atau halaman dimuat ulang.
 * Saat habis: getar + notifikasi (bila diizinkan & halaman tidak terlihat) + toast.
 */
const KEY = 'tempa:rest';
const state = ref(read()); // { ends_at, total, label } | null
const now = ref(Date.now());
let interval = null;

function read() {
    try {
        const value = JSON.parse(localStorage.getItem(KEY) || 'null');
        return value?.ends_at ? value : null;
    } catch {
        return null;
    }
}

watch(state, (value) => {
    try {
        if (value) localStorage.setItem(KEY, JSON.stringify(value));
        else localStorage.removeItem(KEY);
    } catch {
        // Diblokir: timer tetap jalan selama tab terbuka.
    }
    ensureTicking();
});

function ensureTicking() {
    if (state.value && !interval) interval = setInterval(tick, 250);
    if (!state.value && interval) {
        clearInterval(interval);
        interval = null;
    }
}

function tick() {
    now.value = Date.now();
    if (state.value && now.value >= state.value.ends_at) complete();
}

function complete() {
    const finished = state.value;
    state.value = null;
    // Habis lama sekali saat aplikasi tertutup: jangan mengejutkan dengan getar.
    if (!finished || Date.now() - finished.ends_at > 60000) return;
    navigator.vibrate?.([220, 110, 220, 110, 220]);
    const body = finished.label ? `${finished.label} — saatnya set berikutnya` : 'Saatnya set berikutnya';
    if (document.visibilityState !== 'visible') notify('Istirahat selesai', body, 'tempa-rest');
    pushToast({ message: 'Istirahat selesai' });
}

/** Mulai (atau ulang) timer. Dipanggil dari ketukan ✓, jadi sekalian minta izin notifikasi sekali. */
export function startRest(seconds, label = '') {
    if (!seconds) return;
    now.value = Date.now();
    state.value = { ends_at: now.value + seconds * 1000, total: seconds, label };
    requestNotify();
}

export function adjustRest(delta) {
    if (!state.value) return;
    const ends = state.value.ends_at + delta * 1000;
    if (ends <= Date.now()) {
        state.value = null;
        return;
    }
    state.value = { ...state.value, ends_at: ends, total: Math.max(5, state.value.total + delta) };
}

export function stopRest() {
    state.value = null;
}

if (typeof document !== 'undefined') {
    // Kembali ke aplikasi: periksa segera (interval bisa tertahan saat tab di latar belakang).
    document.addEventListener('visibilitychange', tick);
    ensureTicking();
}

export function useRestTimer() {
    const remaining = computed(() => (state.value ? Math.max(0, Math.ceil((state.value.ends_at - now.value) / 1000)) : 0));
    return {
        rest: state,
        remaining,
        progress: computed(() => (state.value ? Math.min(1, 1 - remaining.value / state.value.total) : 0)),
    };
}
