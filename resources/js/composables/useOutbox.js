import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import { pushToast } from '@/composables/useToast';

/**
 * Antrean perubahan yang belum sampai ke server (sesi, gerakan, set). Disimpan di localStorage
 * dan dikirim berurutan; server menolak duplikat lewat client_id, jadi kirim ulang selalu aman.
 *
 * Item: { id, key, route, params, method, payload, summary: { title }, reload, created_at, error? }
 * - reload: muat ulang props halaman setelah terkirim (mis. sesi selesai → riwayat berubah).
 * - key: satu entitas = satu kunci (mis. "set:<uuid>"). Perubahan baru menggantikan item lama yang
 *   belum terkirim dan pindah ke belakang antrean, jadi menekan stepper 10× hanya mengirim nilai akhir.
 *   Pindah ke belakang aman: induknya (sesi/gerakan) selalu dibuat lebih dulu.
 * - Antrean berhenti di item yang ditolak (422/404) agar item sesudahnya tidak dikirim tanpa induknya.
 */
const KEY = 'tempa:outbox';
const items = ref(read());
const sending = ref(false);
const needsLogin = ref(false);
let inFlightId = null;
let timer = null;

function read() {
    try {
        const list = JSON.parse(localStorage.getItem(KEY) || '[]');
        return Array.isArray(list) ? list.filter((i) => i && i.route) : [];
    } catch {
        return [];
    }
}

function write() {
    try {
        localStorage.setItem(KEY, JSON.stringify(items.value));
    } catch {
        // Penyimpanan penuh/diblokir: antrean tetap hidup selama tab terbuka.
    }
}

export function newClientId() {
    if (globalThis.crypto?.randomUUID) return crypto.randomUUID();
    // Cadangan untuk konteks tidak aman (mis. http lewat IP lokal).
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

/** Masukkan ke antrean lalu jadwalkan pengiriman. */
export function enqueue({ key, routeName, params = {}, method = 'POST', payload = {}, summary = {}, reload = false }) {
    const k = key ?? payload.client_id ?? null;
    items.value = [
        ...items.value.filter((i) => !(k && i.key === k && i.id !== inFlightId)),
        { id: newClientId(), key: k, route: routeName, params, method, payload, summary, reload, created_at: Date.now() },
    ];
    write();
    scheduleFlush();
}

/** Batalkan item yang belum terkirim (yang sedang dikirim dibiarkan selesai). */
export function cancelPending(predicate) {
    items.value = items.value.filter((i) => i.id === inFlightId || !predicate(i));
    write();
}

export function removeFromOutbox(id) {
    items.value = items.value.filter((i) => i.id !== id);
    write();
}

function scheduleFlush(delay = 350) {
    clearTimeout(timer);
    timer = setTimeout(flushOutbox, delay);
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** Kirim antrean dari depan. Mengembalikan jumlah yang berhasil. */
export async function flushOutbox() {
    if (sending.value || !items.value.length || !navigator.onLine) return 0;
    sending.value = true;
    let sent = 0;
    let delayed = 0; // sempat tertahan (offline/galat) → beri tahu saat akhirnya terkirim
    let reload = false;

    try {
        while (items.value.length) {
            const item = items.value[0];
            if (item.error) break;

            inFlightId = item.id;
            let response;
            try {
                response = await fetch(route(item.route, item.params), {
                    method: item.method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: item.method === 'DELETE' ? undefined : JSON.stringify(item.payload),
                });
            } catch {
                break; // jaringan putus lagi: coba nanti
            }

            if (response.status === 401 || response.status === 419) {
                needsLogin.value = true; // sesi login habis: kirim setelah masuk lagi
                break;
            }

            // Menghapus sesuatu yang memang sudah tidak ada = berhasil.
            const ok = response.ok || (item.method === 'DELETE' && response.status === 404);
            if (!ok && (response.status === 422 || response.status === 404)) {
                const body = await response.json().catch(() => ({}));
                const message =
                    response.status === 404
                        ? 'Tidak ditemukan di server'
                        : (Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? 'Data tidak valid');
                items.value = items.value.map((i) => (i.id === item.id ? { ...i, error: message } : i));
                write();
                break;
            }
            if (!ok) break; // galat server: coba nanti

            removeFromOutbox(item.id);
            sent++;
            if (Date.now() - item.created_at > 8000) delayed++;
            if (item.reload) reload = true;
        }
    } finally {
        inFlightId = null;
        sending.value = false;
    }

    if (sent) needsLogin.value = false;
    if (delayed) pushToast({ message: delayed === 1 ? 'Perubahan offline terkirim' : `${delayed} perubahan offline terkirim` });
    if (reload || delayed) router.reload();
    return sent;
}

let started = false;

/** Dipanggil sekali saat aplikasi mulai: kirim saat online kembali / saat dibuka. */
export function startOutbox() {
    if (started || typeof window === 'undefined') return;
    started = true;
    window.addEventListener('online', () => scheduleFlush(0));
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && scheduleFlush(0));
    // Data sempat menunggu karena galat server sementara: coba lagi berkala.
    setInterval(() => items.value.length && !items.value[0].error && flushOutbox(), 30000);
    setTimeout(flushOutbox, 1500);
}

export function useOutbox() {
    return { items, sending, needsLogin };
}
