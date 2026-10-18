import { useEventListener } from '@vueuse/core';
import { watch } from 'vue';

/**
 * Jaga layar tetap menyala selama `enabled` bernilai true (sesi latihan aktif).
 * Peramban melepas kunci saat tab tersembunyi, jadi dipasang ulang saat terlihat lagi.
 */
export function useWakeLock(enabled) {
    let sentinel = null;

    async function acquire() {
        if (!('wakeLock' in navigator) || sentinel || document.visibilityState !== 'visible') return;
        try {
            sentinel = await navigator.wakeLock.request('screen');
            sentinel.addEventListener('release', () => (sentinel = null));
        } catch {
            // Tidak didukung / ditolak (mis. mode hemat baterai): sesi tetap jalan biasa.
        }
    }

    async function release() {
        try {
            await sentinel?.release();
        } catch {
            // Sudah terlepas.
        }
        sentinel = null;
    }

    watch(enabled, (on) => (on ? acquire() : release()), { immediate: true });
    useEventListener(document, 'visibilitychange', () => enabled.value && acquire());
}
